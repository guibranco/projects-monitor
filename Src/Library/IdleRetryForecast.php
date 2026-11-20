<?php

namespace GuiBranco\ProjectsMonitor\Library;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Projects when the webhooks idle-pull-requests worker will give each Idle
 * Pull Request its next Idle Retry, from the worker's last run.
 *
 * The worker (webhooks ADR-0010) is a daemon that sleeps 10 minutes after
 * each run, and each run retries exactly one pull request: the first DUE row
 * of GET /pull-requests/idle, whose order (webhooks Sql/0046) is round-robin
 * by owner, then repository: ownerLastIdleRetry, then
 * repositoryLastIdleRetry (never retried first, then the oldest), then
 * idleRetryCount, repositoryIdleRank, the repository's last pull request
 * update and updatedAt. The forecast replays that rule run by run: a WAITING
 * row joins the candidates once its waitingUntil has passed; a retry stamps
 * its run as the owner's and the repository's last Idle Retry (and the
 * repository's last update), so both go to the back of the line; a GAVE_UP
 * row is never retried. It is a projection, not a promise: a daemon restart
 * or an API-triggered run adds a run, and any new activity on a repository
 * reshuffles the queue.
 */
class IdleRetryForecast
{

    /**
     * Seconds between two runs of the worker (its daemon sleep, 600 s since
     * webhooks #1627).
     */
    public const RUN_INTERVAL_SECONDS = 600;

    /**
     * The webhooks application's date.timezone. Its API's timestamps carry
     * no offset and are in this zone, so the worker's run (an absolute
     * instant from HealthChecks.io) is converted into it before any
     * comparison, and the projections are emitted in it to sit next to
     * updatedAt and waitingUntil.
     */
    public const TIME_ZONE = 'Europe/Dublin';

    /**
     * Case-insensitive fragment of the worker's HealthChecks.io check name
     * ("Webhooks - Idle Pull Requests", following the webhooks README).
     */
    public const HEALTH_CHECK_NAME = 'idle';

    /**
     * Format of the emitted timestamps, the one the webhooks API uses.
     */
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    /**
     * Retries projected at most, as a guard on the size of the queue.
     */
    private const MAX_RETRIES = 500;

    /**
     * Unix timestamp of the worker's last run, null when unknown.
     *
     * @var integer|null
     */
    private $lastRunAt = null;

    /**
     * Seconds between two runs of the worker.
     *
     * @var integer
     */
    private $intervalSeconds;

    /**
     * The webhooks application's time zone.
     *
     * @var DateTimeZone
     */
    private $zone;


    /**
     * Builds the forecast from the worker's last run.
     *
     * @param string|null $lastRunAt       When the worker last ran, in any form
     *                                     DateTimeImmutable parses (an ISO 8601
     *                                     string with its offset, typically); null
     *                                     when unknown.
     * @param integer     $intervalSeconds Seconds between two runs of the worker.
     */
    public function __construct(?string $lastRunAt, int $intervalSeconds=self::RUN_INTERVAL_SECONDS)
    {
        $this->zone            = new DateTimeZone(self::TIME_ZONE);
        $this->intervalSeconds = max(1, $intervalSeconds);
        $this->lastRunAt       = $this->toTimestamp($lastRunAt);

    }//end __construct()


    /**
     * The worker's last and next run (its last run plus the interval it
     * sleeps), as the dashboards describe it.
     *
     * @return array
     */
    public function describeWorker(): array
    {
        $nextRunAt = null;
        if ($this->lastRunAt !== null) {
            $nextRunAt = ($this->lastRunAt + $this->intervalSeconds);
        }

        $worker = [];

        $worker['lastRunAt']       = $this->format($this->lastRunAt);
        $worker['nextRunAt']       = $this->format($nextRunAt);
        $worker['intervalSeconds'] = $this->intervalSeconds;
        $worker['timeZone']        = self::TIME_ZONE;

        return $worker;

    }//end describeWorker()


    /**
     * The rows as given, each with an expectedRetryAt: the projected time of
     * its next Idle Retry, or null when it will not get one (GAVE_UP) or the
     * projection is impossible (unknown last run, unreadable timestamps).
     *
     * @param array $rows Rows as returned by GET /pull-requests/idle, in its order.
     *
     * @return array
     */
    public function apply(array $rows): array
    {
        $expected = $this->simulate($rows);

        foreach ($rows as $index => $row) {
            if (is_array($row) === false) {
                continue;
            }

            $rows[$index]['expectedRetryAt'] = $this->format(($expected[$index] ?? null));
        }

        return $rows;

    }//end apply()


    /**
     * Replays the worker run by run: at each run the first DUE row in the
     * API's order gets the retry, and that run becomes its owner's and its
     * repository's last Idle Retry and its repository's last update.
     *
     * @param array $rows Rows as returned by GET /pull-requests/idle.
     *
     * @return array Row index => unix timestamp of its projected retry.
     */
    private function simulate(array $rows): array
    {
        $expected = [];
        if ($this->lastRunAt === null) {
            return $expected;
        }

        $queue   = $this->getQueue($rows);
        $state   = $this->getState($rows);
        $runAt   = ($this->lastRunAt + $this->intervalSeconds);
        $retries = 0;

        while ($queue !== [] && $retries < self::MAX_RETRIES) {
            $runAt  = $this->firstRunWithSomethingDue($queue, $runAt);
            $picked = $this->pick($queue, $state, $runAt);
            if ($picked === null) {
                break;
            }

            $expected[$picked] = $runAt;

            $owner      = $queue[$picked]['owner'];
            $repository = $queue[$picked]['repository'];

            $state['ownerLastRetry'][$owner]           = $runAt;
            $state['repositoryLastRetry'][$repository] = $runAt;
            $state['activity'][$repository]            = $runAt;
            unset($queue[$picked]);

            $runAt = ($runAt + $this->intervalSeconds);
            $retries++;
        }

        return $expected;

    }//end simulate()


    /**
     * The rows that can still get an Idle Retry, keyed by their index: DUE
     * rows are due at once, WAITING rows once their waitingUntil has passed.
     *
     * @param array $rows Rows as returned by GET /pull-requests/idle.
     *
     * @return array
     */
    private function getQueue(array $rows): array
    {
        $queue = [];

        foreach ($rows as $index => $row) {
            if (is_array($row) === false) {
                continue;
            }

            $dueAt = $this->getDueAt($row);
            if ($dueAt === null) {
                continue;
            }

            $entry = [];

            $entry['owner']      = $this->getOwner($row);
            $entry['repository'] = $this->getRepository($row);
            $entry['dueAt']      = $dueAt;
            $entry['retryCount'] = ((int) ($row['idleRetryCount'] ?? 0));
            $entry['rank']       = ((int) ($row['repositoryIdleRank'] ?? 0));
            $entry['updatedAt']  = ((int) $this->toTimestamp(($row['updatedAt'] ?? null)));

            $queue[$index] = $entry;
        }

        return $queue;

    }//end getQueue()


    /**
     * When a row becomes due for an Idle Retry: now for a DUE row, its
     * waitingUntil for a WAITING row, never (null) otherwise.
     *
     * @param array $row One row as returned by GET /pull-requests/idle.
     *
     * @return integer|null
     */
    private function getDueAt(array $row): ?int
    {
        $status = ($row['idleStatus'] ?? null);
        if ($status === 'DUE') {
            return 0;
        }

        if ($status === 'WAITING') {
            return $this->toTimestamp(($row['waitingUntil'] ?? null));
        }

        return null;

    }//end getDueAt()


    /**
     * The round-robin keys the API orders by, per owner and repository: when
     * any of the owner's (ownerLastRetry) and of the repository's
     * (repositoryLastRetry) pull requests last got an Idle Retry, null when
     * none ever did, and the repository's last pull request update
     * (activity, 0 when unknown).
     *
     * @param array $rows Rows as returned by GET /pull-requests/idle.
     *
     * @return array {ownerLastRetry: array, repositoryLastRetry: array, activity: array}
     */
    private function getState(array $rows): array
    {
        $state = [
                  'ownerLastRetry'      => [],
                  'repositoryLastRetry' => [],
                  'activity'            => [],
                 ];

        foreach ($rows as $row) {
            if (is_array($row) === false) {
                continue;
            }

            $owner      = $this->getOwner($row);
            $repository = $this->getRepository($row);

            if (array_key_exists($owner, $state['ownerLastRetry']) === false) {
                $state['ownerLastRetry'][$owner] = $this->toTimestamp(($row['ownerLastIdleRetry'] ?? null));
            }

            if (array_key_exists($repository, $state['activity']) === false) {
                $state['repositoryLastRetry'][$repository] = $this->toTimestamp(($row['repositoryLastIdleRetry'] ?? null));
                $state['activity'][$repository]            = ((int) $this->toTimestamp(($row['repositoryLastPullRequestUpdate'] ?? null)));
            }
        }

        return $state;

    }//end getState()


    /**
     * The run, at or after $runAt, by which at least one queued row is due.
     *
     * @param array   $queue The rows still waiting for a retry.
     * @param integer $runAt The run being considered.
     *
     * @return integer
     */
    private function firstRunWithSomethingDue(array $queue, int $runAt): int
    {
        $earliest = min(array_column($queue, 'dueAt'));
        if ($earliest <= $runAt) {
            return $runAt;
        }

        $runs = ((int) ceil((($earliest - $runAt) / $this->intervalSeconds)));

        return ($runAt + ($runs * $this->intervalSeconds));

    }//end firstRunWithSomethingDue()


    /**
     * The row the worker retries at $runAt: the first DUE row in the API's
     * order.
     *
     * @param array   $queue The rows still waiting for a retry.
     * @param array   $state The round-robin keys (see getState()).
     * @param integer $runAt The run.
     *
     * @return integer|null The index of the row, null when none is due.
     */
    private function pick(array $queue, array $state, int $runAt): ?int
    {
        $picked = null;

        foreach ($queue as $index => $entry) {
            if ($entry['dueAt'] > $runAt) {
                continue;
            }

            if ($picked === null || $this->precedes($entry, $queue[$picked], $state) === true) {
                $picked = $index;
            }
        }

        return $picked;

    }//end pick()


    /**
     * Whether $candidate comes before $current in the API's order (webhooks
     * Sql/0046): the owner retried longest ago (never first), then the
     * repository retried longest ago (never first), then the least retried
     * pull request, its rank in the repository, the repository quiet the
     * longest and the older pull request.
     *
     * @param array $candidate A due queue entry.
     * @param array $current   The due queue entry leading so far.
     * @param array $state     The round-robin keys (see getState()).
     *
     * @return boolean
     */
    private function precedes(array $candidate, array $current, array $state): bool
    {
        $keys = [
                 [
                  $this->nullsFirst(($state['ownerLastRetry'][$candidate['owner']] ?? null)),
                  $this->nullsFirst(($state['ownerLastRetry'][$current['owner']] ?? null)),
                 ],
                 [
                  $this->nullsFirst(($state['repositoryLastRetry'][$candidate['repository']] ?? null)),
                  $this->nullsFirst(($state['repositoryLastRetry'][$current['repository']] ?? null)),
                 ],
                 [
                  $candidate['retryCount'],
                  $current['retryCount'],
                 ],
                 [
                  $candidate['rank'],
                  $current['rank'],
                 ],
                 [
                  ($state['activity'][$candidate['repository']] ?? 0),
                  ($state['activity'][$current['repository']] ?? 0),
                 ],
                 [
                  $candidate['updatedAt'],
                  $current['updatedAt'],
                 ],
                ];

        foreach ($keys as $pair) {
            if ($pair[0] !== $pair[1]) {
                return $pair[0] < $pair[1];
            }
        }

        return false;

    }//end precedes()


    /**
     * A last-retry timestamp as a sort key where never (null) sorts first,
     * as the API's "IS NOT NULL" ordering does.
     *
     * @param integer|null $timestamp The unix timestamp, null for never.
     *
     * @return integer
     */
    private function nullsFirst(?int $timestamp): int
    {
        if ($timestamp === null) {
            return PHP_INT_MIN;
        }

        return $timestamp;

    }//end nullsFirst()


    /**
     * The row's owner.
     *
     * @param array $row One row as returned by GET /pull-requests/idle.
     *
     * @return string
     */
    private function getOwner(array $row): string
    {
        return (string) ($row['repositoryOwner'] ?? '');

    }//end getOwner()


    /**
     * The row's repository as "owner/name".
     *
     * @param array $row One row as returned by GET /pull-requests/idle.
     *
     * @return string
     */
    private function getRepository(array $row): string
    {
        return ($row['repositoryOwner'] ?? '').'/'.($row['repositoryName'] ?? '');

    }//end getRepository()


    /**
     * A timestamp from the API (no offset: read in the webhooks zone) or from
     * HealthChecks.io (with its offset) as a unix timestamp, null when absent
     * or unreadable.
     *
     * @param mixed $value The timestamp as given.
     *
     * @return integer|null
     */
    private function toTimestamp($value): ?int
    {
        if (is_string($value) === false || trim($value) === '') {
            return null;
        }

        try {
            $instant = new DateTimeImmutable($value, $this->zone);
        } catch (Exception $unreadable) {
            return null;
        }

        return $instant->getTimestamp();

    }//end toTimestamp()


    /**
     * A unix timestamp in the webhooks zone and format, null stays null.
     *
     * @param integer|null $timestamp The unix timestamp.
     *
     * @return string|null
     */
    private function format(?int $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        $instant = new DateTimeImmutable('@'.$timestamp);

        return $instant->setTimezone($this->zone)->format(self::TIMESTAMP_FORMAT);

    }//end format()


}//end class

<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\IdleRetryForecast;

/**
 * Tests the Idle Retry forecast: the worker's next run from its last one,
 * and each idle pull request's projected retry, replaying the webhooks
 * worker's rule (one retry per hourly run, the first DUE row in round-robin
 * order across repositories, a retried repository going to the back of the
 * line, WAITING rows joining once their backoff ends, GAVE_UP rows never).
 *
 * The last run is given as HealthChecks.io reports it, in UTC; the webhooks
 * API's timestamps are in Europe/Dublin, which is UTC+1 on 23 October 2026
 * and UTC+0 from 25 October 2026 (the clocks go back that Sunday).
 */
class IdleRetryForecastTest extends TestCase
{

    /**
     * The worker's last run as HealthChecks.io reports it: 11:05:12 in Dublin.
     */
    private const LAST_RUN = '2026-10-23T10:05:12+00:00';


    /**
     * Builds one idle row as the webhooks API returns it.
     *
     * @param string $repository The repository as "owner/name".
     * @param string $status     DUE, WAITING or GAVE_UP.
     * @param string $updatedAt  The pull request's last update.
     * @param string $activity   The repository's last pull request update.
     *
     * @return array
     */
    protected function getRow(string $repository, string $status, string $updatedAt, string $activity): array
    {
        $parts = explode('/', $repository);
        $row   = [];

        $row['repositoryOwner']                 = $parts[0];
        $row['repositoryName']                  = $parts[1];
        $row['repositoryLastPullRequestUpdate'] = $activity;
        $row['updatedAt']                       = $updatedAt;
        $row['idleStatus']                      = $status;
        $row['waitingUntil']                    = null;

        return $row;

    }//end getRow()


    /**
     * The expectedRetryAt of every row, in order.
     *
     * @param array $rows The rows after apply().
     *
     * @return array
     */
    protected function getExpected(array $rows): array
    {
        return array_column($rows, 'expectedRetryAt');

    }//end getExpected()


    /**
     * The next run is the last one plus an hour, both in the webhooks zone.
     *
     * @return void
     */
    public function testDescribesTheWorkerInTheWebhooksZone(): void
    {
        $forecast = new IdleRetryForecast(self::LAST_RUN);
        $worker   = $forecast->describeWorker();

        $this->assertSame('2026-10-23 11:05:12', $worker['lastRunAt']);
        $this->assertSame('2026-10-23 12:05:12', $worker['nextRunAt']);
        $this->assertSame(3600, $worker['intervalSeconds']);
        $this->assertSame('Europe/Dublin', $worker['timeZone']);

    }//end testDescribesTheWorkerInTheWebhooksZone()


    /**
     * Without a last run there is no projection, but the rows come back
     * intact with a null expectedRetryAt each.
     *
     * @return void
     */
    public function testWithoutALastRunNothingIsProjected(): void
    {
        $rows   = [$this->getRow('guibranco/a', 'DUE', '2026-10-05 17:51:21', '2026-10-10 08:30:00')];
        $rows[] = 'not a row';

        $forecast = new IdleRetryForecast(null);
        $worker   = $forecast->describeWorker();
        $applied  = $forecast->apply($rows);

        $this->assertNull($worker['lastRunAt']);
        $this->assertNull($worker['nextRunAt']);
        $this->assertCount(2, $applied);
        $this->assertNull($applied[0]['expectedRetryAt']);
        $this->assertSame('not a row', $applied[1]);
        $this->assertNull((new IdleRetryForecast('yesterday-ish'))->describeWorker()['lastRunAt']);

    }//end testWithoutALastRunNothingIsProjected()


    /**
     * One retry per run, round-robin: after repository A's first row is
     * retried at the next run, A's last update is that run, so repository C
     * (quiet since the 18th) goes before A's second row.
     *
     * @return void
     */
    public function testRetriesRoundRobinAcrossRepositories(): void
    {
        $rows = [];

        $rows[] = $this->getRow('guibranco/a', 'DUE', '2026-10-05 17:51:21', '2026-10-10 08:30:00');
        $rows[] = $this->getRow('guibranco/c', 'DUE', '2026-09-30 22:40:11', '2026-10-18 11:00:00');
        $rows[] = $this->getRow('guibranco/a', 'DUE', '2026-10-07 12:00:00', '2026-10-10 08:30:00');

        $expected = $this->getExpected((new IdleRetryForecast(self::LAST_RUN))->apply($rows));

        $this->assertSame('2026-10-23 12:05:12', $expected[0]);
        $this->assertSame('2026-10-23 13:05:12', $expected[1]);
        $this->assertSame('2026-10-23 14:05:12', $expected[2]);

    }//end testRetriesRoundRobinAcrossRepositories()


    /**
     * Among repositories with the same last update, the older pull request
     * goes first.
     *
     * @return void
     */
    public function testBreaksTiesByTheOlderPullRequest(): void
    {
        $rows = [];

        $rows[] = $this->getRow('guibranco/a', 'DUE', '2026-10-07 12:00:00', '2026-10-10 08:30:00');
        $rows[] = $this->getRow('guibranco/b', 'DUE', '2026-10-05 17:51:21', '2026-10-10 08:30:00');

        $expected = $this->getExpected((new IdleRetryForecast(self::LAST_RUN))->apply($rows));

        $this->assertSame('2026-10-23 13:05:12', $expected[0]);
        $this->assertSame('2026-10-23 12:05:12', $expected[1]);

    }//end testBreaksTiesByTheOlderPullRequest()


    /**
     * A WAITING row joins the queue at the first run after its backoff ends
     * and, its repository being the quietest, goes ahead of a DUE row of a
     * repository that was just retried.
     *
     * @return void
     */
    public function testAWaitingRowJoinsOnceItsBackoffEnds(): void
    {
        $rows = [];

        $rows[] = $this->getRow('guibranco/a', 'DUE', '2026-10-05 17:51:21', '2026-10-10 08:30:00');
        $rows[] = $this->getRow('guibranco/b', 'WAITING', '2026-10-15 09:12:03', '2026-10-01 00:00:00');
        $rows[] = $this->getRow('guibranco/a', 'DUE', '2026-10-07 12:00:00', '2026-10-10 08:30:00');

        $rows[1]['waitingUntil'] = '2026-10-23 12:30:00';

        $expected = $this->getExpected((new IdleRetryForecast(self::LAST_RUN))->apply($rows));

        $this->assertSame('2026-10-23 12:05:12', $expected[0]);
        $this->assertSame('2026-10-23 13:05:12', $expected[1]);
        $this->assertSame('2026-10-23 14:05:12', $expected[2]);

    }//end testAWaitingRowJoinsOnceItsBackoffEnds()


    /**
     * With nothing due, the projection skips straight to the first run after
     * the earliest backoff ends, across the change back to winter time.
     *
     * @return void
     */
    public function testSkipsIdleRunsUntilABackoffEnds(): void
    {
        $rows = [$this->getRow('guibranco/b', 'WAITING', '2026-10-21 09:00:00', '2026-10-21 09:00:00')];

        $rows[0]['waitingUntil'] = '2026-10-26 09:00:00';

        $expected = $this->getExpected((new IdleRetryForecast(self::LAST_RUN))->apply($rows));

        $this->assertSame('2026-10-26 09:05:12', $expected[0]);

    }//end testSkipsIdleRunsUntilABackoffEnds()


    /**
     * A GAVE_UP row, an unknown status and a WAITING row without a readable
     * backoff end are never retried; the rest of the queue is unaffected.
     *
     * @return void
     */
    public function testRowsThatNeverGetARetry(): void
    {
        $rows = [];

        $rows[] = $this->getRow('guibranco/a', 'GAVE_UP', '2026-09-30 22:40:11', '2026-10-18 11:00:00');
        $rows[] = $this->getRow('guibranco/b', 'SOMETHING_NEW', '2026-10-05 17:51:21', '2026-10-10 08:30:00');
        $rows[] = $this->getRow('guibranco/c', 'WAITING', '2026-10-15 09:12:03', '2026-10-15 09:12:03');
        $rows[] = $this->getRow('guibranco/d', 'DUE', '2026-10-05 17:51:21', '2026-10-10 08:30:00');

        $rows[2]['waitingUntil'] = 'soon';

        $expected = $this->getExpected((new IdleRetryForecast(self::LAST_RUN))->apply($rows));

        $this->assertNull($expected[0]);
        $this->assertNull($expected[1]);
        $this->assertNull($expected[2]);
        $this->assertSame('2026-10-23 12:05:12', $expected[3]);

    }//end testRowsThatNeverGetARetry()


    /**
     * The interval is configurable, and never below a second.
     *
     * @return void
     */
    public function testHonoursTheInterval(): void
    {
        $rows = [];

        $rows[] = $this->getRow('guibranco/a', 'DUE', '2026-10-05 17:51:21', '2026-10-10 08:30:00');
        $rows[] = $this->getRow('guibranco/b', 'DUE', '2026-10-05 17:51:21', '2026-10-11 08:30:00');

        $forecast = new IdleRetryForecast(self::LAST_RUN, 1800);
        $expected = $this->getExpected($forecast->apply($rows));

        $this->assertSame('2026-10-23 11:35:12', $forecast->describeWorker()['nextRunAt']);
        $this->assertSame('2026-10-23 11:35:12', $expected[0]);
        $this->assertSame('2026-10-23 12:05:12', $expected[1]);
        $this->assertSame(1, (new IdleRetryForecast(self::LAST_RUN, 0))->describeWorker()['intervalSeconds']);

    }//end testHonoursTheInterval()


}//end class

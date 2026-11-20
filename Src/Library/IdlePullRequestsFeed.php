<?php

namespace GuiBranco\ProjectsMonitor\Library;

use Throwable;

/**
 * The Idle Pull Requests feed as both dashboards consume it: the rows of the
 * webhooks API's GET /pull-requests/idle for the dashboard's own GitHub
 * accounts (GitHubAccounts), with the Idle Retry forecast applied, and the
 * worker's last and next run.
 *
 * The API returns every repository the bot is installed on, and the worker
 * retries across all of them, so the forecast runs over the whole queue
 * first and only then are other accounts' rows dropped: an own row's
 * projected retry still counts the turns the hidden rows take.
 *
 * The worker's last run is the last ping of its HealthChecks.io check (the
 * end of its last tracked run). When the check cannot be found or
 * HealthChecks.io is unavailable, the rows still come through, only without
 * a projection.
 */
class IdlePullRequestsFeed
{

    /**
     * Every followed and non-followed account's rows.
     */
    public const FILTER_ALL = 'all';

    /**
     * Only the own accounts' rows (GitHubAccounts), the default.
     */
    public const FILTER_MINE = 'mine';

    /**
     * The filters fetch() accepts.
     */
    public const FILTERS = [
                            self::FILTER_ALL,
                            self::FILTER_MINE,
                           ];

    /**
     * The webhooks API client.
     *
     * @var Webhooks|null
     */
    private $webhooks;

    /**
     * The HealthChecks.io client.
     *
     * @var HealthChecksIo|null
     */
    private $healthChecks;

    /**
     * The accounts whose repositories the dashboards show.
     *
     * @var GitHubAccounts
     */
    private $accounts;


    /**
     * Builds the feed on the given clients, or on fresh ones.
     *
     * @param Webhooks|null       $webhooks     The webhooks API client.
     * @param HealthChecksIo|null $healthChecks The HealthChecks.io client.
     * @param GitHubAccounts|null $accounts     The accounts whose repositories to show.
     */
    public function __construct(?Webhooks $webhooks=null, ?HealthChecksIo $healthChecks=null, ?GitHubAccounts $accounts=null)
    {
        $this->webhooks     = $webhooks;
        $this->healthChecks = $healthChecks;
        $this->accounts     = $accounts;
        if ($this->accounts === null) {
            $this->accounts = new GitHubAccounts();
        }

    }//end __construct()


    /**
     * The filter a request asked for, as fetch() takes it: "all" or "mine",
     * with anything else (missing, unknown, not a string) falling back to
     * "mine".
     *
     * @param mixed $value The raw value, e.g. the query string's filter.
     *
     * @return string One of FILTERS.
     */
    public function normalizeFilter($value): string
    {
        if (in_array($value, self::FILTERS, true) === true) {
            return $value;
        }

        return self::FILTER_MINE;

    }//end normalizeFilter()


    /**
     * The feed: the rows with an expectedRetryAt each, how many rows of
     * other accounts were left out, and the worker. With the "mine" filter
     * (the default, and any unknown value) only the own accounts' rows are
     * listed; with "all" every row the API returns is, and none is hidden.
     *
     * @param string $filter One of FILTERS.
     *
     * @return array {items: array, hidden: integer, worker: array}
     */
    public function fetch(string $filter=self::FILTER_MINE): array
    {
        $rows = $this->getWebhooks()->getPullRequestsIdle();
        if (is_array($rows) === false) {
            $rows = [];
        }

        $check    = $this->getWorkerCheck();
        $forecast = new IdleRetryForecast(($check['lastPing'] ?? null));
        $worker   = $forecast->describeWorker();

        $worker['check']       = ($check['name'] ?? null);
        $worker['checkStatus'] = ($check['status'] ?? null);

        $projected = $forecast->apply($rows);
        $own       = $projected;
        if ($filter !== self::FILTER_ALL) {
            $own = $this->getOwnRows($projected);
        }

        $feed = [];

        $feed['items']  = $own;
        $feed['hidden'] = (count($projected) - count($own));
        $feed['worker'] = $worker;

        return $feed;

    }//end fetch()


    /**
     * The rows whose repository belongs to one of the own accounts, in the
     * order given.
     *
     * @param array $rows Rows as returned by GET /pull-requests/idle.
     *
     * @return array
     */
    private function getOwnRows(array $rows): array
    {
        $own = [];

        foreach ($rows as $row) {
            if (is_array($row) === false) {
                continue;
            }

            if ($this->accounts->isOwn(($row['repositoryOwner'] ?? null)) === true) {
                $own[] = $row;
            }
        }

        return $own;

    }//end getOwnRows()


    /**
     * The worker's HealthChecks.io check, null when it cannot be read.
     *
     * @return array|null
     */
    private function getWorkerCheck(): ?array
    {
        try {
            return $this->getHealthChecks()->getCheckByName(IdleRetryForecast::HEALTH_CHECK_NAME);
        } catch (Throwable $unavailable) {
            return null;
        }

    }//end getWorkerCheck()


    /**
     * The webhooks API client, built on first use.
     *
     * @return Webhooks
     */
    private function getWebhooks(): Webhooks
    {
        if ($this->webhooks === null) {
            $this->webhooks = new Webhooks();
        }

        return $this->webhooks;

    }//end getWebhooks()


    /**
     * The HealthChecks.io client, built on first use (its constructor reads
     * the secrets file, so it is built inside the caller's try).
     *
     * @return HealthChecksIo
     */
    private function getHealthChecks(): HealthChecksIo
    {
        if ($this->healthChecks === null) {
            $this->healthChecks = new HealthChecksIo();
        }

        return $this->healthChecks;

    }//end getHealthChecks()


}//end class

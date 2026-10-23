<?php

namespace GuiBranco\ProjectsMonitor\Library;

use Throwable;

/**
 * The Idle Pull Requests feed as both dashboards consume it: the rows of the
 * webhooks API's GET /pull-requests/idle with the Idle Retry forecast
 * applied, and the worker's last and next run.
 *
 * The worker's last run is the last ping of its HealthChecks.io check (the
 * end of its last tracked run). When the check cannot be found or
 * HealthChecks.io is unavailable, the rows still come through, only without
 * a projection.
 */
class IdlePullRequestsFeed
{

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
     * Builds the feed on the given clients, or on fresh ones.
     *
     * @param Webhooks|null       $webhooks     The webhooks API client.
     * @param HealthChecksIo|null $healthChecks The HealthChecks.io client.
     */
    public function __construct(?Webhooks $webhooks=null, ?HealthChecksIo $healthChecks=null)
    {
        $this->webhooks     = $webhooks;
        $this->healthChecks = $healthChecks;

    }//end __construct()


    /**
     * The feed: the rows with an expectedRetryAt each, and the worker.
     *
     * @return array {items: array, worker: array}
     */
    public function fetch(): array
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

        $feed = [];

        $feed['items']  = $forecast->apply($rows);
        $feed['worker'] = $worker;

        return $feed;

    }//end fetch()


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

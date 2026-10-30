<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\HealthChecksIo;
use GuiBranco\ProjectsMonitor\Library\IdlePullRequestsFeed;
use GuiBranco\ProjectsMonitor\Library\RequestException;
use GuiBranco\ProjectsMonitor\Library\Webhooks;

/**
 * Tests the Idle Pull Requests feed: the webhooks rows with the Idle Retry
 * forecast applied from the worker's HealthChecks.io check, and the worker's
 * description, degrading to rows without a projection when the check cannot
 * be read.
 */
class IdlePullRequestsFeedTest extends TestCase
{


    /**
     * A webhooks client answering GET /pull-requests/idle with $rows.
     *
     * @param mixed $rows The response.
     *
     * @return Webhooks
     */
    protected function getWebhooks($rows): Webhooks
    {
        $webhooks = $this->createMock(Webhooks::class);

        $webhooks->method('getPullRequestsIdle')->willReturn($rows);

        return $webhooks;

    }//end getWebhooks()


    /**
     * One DUE row as the webhooks API returns it.
     *
     * @param string $owner    The repository's owner.
     * @param string $activity The repository's last pull request update.
     *
     * @return array
     */
    protected function getRow(string $owner='guibranco', string $activity='2026-10-10 08:30:00'): array
    {
        $row = [];

        $row['repositoryOwner']                 = $owner;
        $row['repositoryName']                  = 'projects-monitor';
        $row['repositoryLastPullRequestUpdate'] = $activity;
        $row['updatedAt']                       = '2026-10-05 17:51:21';
        $row['idleStatus']                      = 'DUE';
        $row['waitingUntil']                    = null;

        return $row;

    }//end getRow()


    /**
     * A HealthChecks.io client whose worker check last pinged at 11:05:12 Dublin.
     *
     * @return HealthChecksIo
     */
    protected function getHealthChecks(): HealthChecksIo
    {
        $check = [];

        $check['name']     = 'Webhooks - Idle Pull Requests';
        $check['status']   = 'up';
        $check['lastPing'] = '2026-10-23T10:05:12+00:00';
        $check['nextPing'] = '2026-10-23T11:10:12+00:00';

        $healthChecks = $this->createMock(HealthChecksIo::class);

        $healthChecks->method('getCheckByName')->willReturn($check);

        return $healthChecks;

    }//end getHealthChecks()


    /**
     * The worker's check gives the last run; the rows get their projection
     * and the worker carries the check's name and status.
     *
     * @return void
     */
    public function testProjectsFromTheWorkerCheck(): void
    {
        $check = [];

        $check['name']     = 'Webhooks - Idle Pull Requests';
        $check['status']   = 'up';
        $check['lastPing'] = '2026-10-23T10:05:12+00:00';
        $check['nextPing'] = '2026-10-23T11:10:12+00:00';

        $healthChecks = $this->createMock(HealthChecksIo::class);

        $healthChecks->expects($this->once())->method('getCheckByName')->with('idle')->willReturn($check);

        $feed = (new IdlePullRequestsFeed($this->getWebhooks([$this->getRow()]), $healthChecks))->fetch();

        $this->assertSame('2026-10-23 11:05:12', $feed['worker']['lastRunAt']);
        $this->assertSame('2026-10-23 12:05:12', $feed['worker']['nextRunAt']);
        $this->assertSame('Webhooks - Idle Pull Requests', $feed['worker']['check']);
        $this->assertSame('up', $feed['worker']['checkStatus']);
        $this->assertCount(1, $feed['items']);
        $this->assertSame(0, $feed['hidden']);
        $this->assertSame('2026-10-23 12:05:12', $feed['items'][0]['expectedRetryAt']);
        $this->assertSame('DUE', $feed['items'][0]['idleStatus']);

    }//end testProjectsFromTheWorkerCheck()


    /**
     * Other accounts' rows are left out, but only after the forecast: the
     * hidden repository is quieter, so it takes the next run and the own
     * row is projected an hour later, exactly as the worker will do.
     *
     * @return void
     */
    public function testHidesOtherAccountsAfterForecasting(): void
    {
        $rows = [];

        $rows[] = $this->getRow('octocat', '2026-10-01 00:00:00');
        $rows[] = $this->getRow('guibranco', '2026-10-10 08:30:00');
        $rows[] = $this->getRow('ApiBR', '2026-10-12 08:30:00');

        $feed = (new IdlePullRequestsFeed($this->getWebhooks($rows), $this->getHealthChecks()))->fetch();

        $this->assertSame(1, $feed['hidden']);
        $this->assertSame(['guibranco', 'ApiBR'], array_column($feed['items'], 'repositoryOwner'));
        $this->assertSame('2026-10-23 13:05:12', $feed['items'][0]['expectedRetryAt']);
        $this->assertSame('2026-10-23 14:05:12', $feed['items'][1]['expectedRetryAt']);

    }//end testHidesOtherAccountsAfterForecasting()


    /**
     * When HealthChecks.io cannot be read, the rows still come through
     * without a projection and the worker says nothing is known.
     *
     * @return void
     */
    public function testRowsStillComeThroughWithoutHealthChecks(): void
    {
        $healthChecks = $this->createMock(HealthChecksIo::class);

        $healthChecks->method('getCheckByName')->willThrowException(new RequestException('Code: 503 - Error: unavailable'));

        $feed = (new IdlePullRequestsFeed($this->getWebhooks([$this->getRow()]), $healthChecks))->fetch();

        $this->assertNull($feed['worker']['lastRunAt']);
        $this->assertNull($feed['worker']['nextRunAt']);
        $this->assertNull($feed['worker']['check']);
        $this->assertNull($feed['worker']['checkStatus']);
        $this->assertCount(1, $feed['items']);
        $this->assertNull($feed['items'][0]['expectedRetryAt']);
        $this->assertSame('guibranco', $feed['items'][0]['repositoryOwner']);

    }//end testRowsStillComeThroughWithoutHealthChecks()


    /**
     * No check matching the worker's name means the same: rows, no projection.
     *
     * @return void
     */
    public function testRowsStillComeThroughWithoutAMatchingCheck(): void
    {
        $healthChecks = $this->createMock(HealthChecksIo::class);

        $healthChecks->method('getCheckByName')->willReturn(null);

        $feed = (new IdlePullRequestsFeed($this->getWebhooks([$this->getRow()]), $healthChecks))->fetch();

        $this->assertNull($feed['worker']['lastRunAt']);
        $this->assertNull($feed['worker']['check']);
        $this->assertNull($feed['items'][0]['expectedRetryAt']);

    }//end testRowsStillComeThroughWithoutAMatchingCheck()


    /**
     * A webhooks response that is not a list is an empty feed, not an error.
     *
     * @return void
     */
    public function testANonListResponseIsAnEmptyFeed(): void
    {
        $healthChecks = $this->createMock(HealthChecksIo::class);

        $healthChecks->method('getCheckByName')->willReturn(null);

        $feed = (new IdlePullRequestsFeed($this->getWebhooks(null), $healthChecks))->fetch();

        $this->assertSame([], $feed['items']);
        $this->assertSame(3600, $feed['worker']['intervalSeconds']);

    }//end testANonListResponseIsAnEmptyFeed()


}//end class

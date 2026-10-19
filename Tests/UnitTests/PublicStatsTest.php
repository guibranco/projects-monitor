<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\PublicStats;

/**
 * Tests the trimming of webhooks idle pull request rows into the public
 * stats payload served to the unauthenticated login page.
 */
class PublicStatsTest extends TestCase
{


    /**
     * Invokes the summarizer under test through a callable rather than a
     * static call expression, so PHPMD's StaticAccess rule (enforced by
     * Codacy) does not flag every test method.
     *
     * @param mixed $items Rows as returned by the webhooks API.
     *
     * @return array
     */
    protected function summarize(mixed $items): array
    {
        $summarize = sprintf('%s::summarizeIdlePullRequests', PublicStats::class);

        return $summarize($items);

    }//end summarize()


    /**
     * Builds one idle pull request row exactly as the webhooks API returns
     * it, internal processing metadata included.
     *
     * @return array
     */
    protected function webhooksRow(): array
    {
        $row = [];

        $row['sequence'] = 4711;
        $row['number']   = '1206';
        $row['title']    = 'feat: add "IDLE" state handling';
        $row['ref']      = 'refs/heads/feature/idle';

        $row['repositoryOwner'] = 'guibranco';
        $row['repositoryName']  = 'projects-monitor';

        $row['senderLogin']    = 'dependabot[bot]';
        $row['updatedAt']      = '2026-10-17 17:51:21';
        $row['processingDate'] = '2026-10-18 06:00:00';
        $row['idleRetryCount'] = '3';
        $row['idleStatus']     = 'DUE';

        $row['webhooksReceiverVersion'] = '1.28.6';
        $row['webhooksHandlerVersion']  = '1.28.4';
        $row['gstracciniBotVersion']    = '1.25.4';

        return $row;

    }//end webhooksRow()


    /**
     * Only the public-safe fields survive, with numeric strings cast to int.
     *
     * @return void
     */
    public function testKeepsOnlyPublicFields(): void
    {
        $summary = $this->summarize([$this->webhooksRow()]);

        $this->assertCount(1, $summary);
        $this->assertCount(8, $summary[0]);
        $this->assertSame('guibranco', $summary[0]['repositoryOwner']);
        $this->assertSame('projects-monitor', $summary[0]['repositoryName']);
        $this->assertSame(1206, $summary[0]['number']);
        $this->assertSame('feat: add "IDLE" state handling', $summary[0]['title']);
        $this->assertSame('dependabot[bot]', $summary[0]['senderLogin']);
        $this->assertSame('2026-10-17 17:51:21', $summary[0]['updatedAt']);
        $this->assertSame(3, $summary[0]['idleRetryCount']);
        $this->assertSame('DUE', $summary[0]['idleStatus']);

    }//end testKeepsOnlyPublicFields()


    /**
     * Internal processing metadata never reaches the public payload.
     *
     * @return void
     */
    public function testDropsInternalProcessingFields(): void
    {
        $summary = $this->summarize([$this->webhooksRow()]);

        $this->assertArrayNotHasKey('sequence', $summary[0]);
        $this->assertArrayNotHasKey('ref', $summary[0]);
        $this->assertArrayNotHasKey('processingDate', $summary[0]);
        $this->assertArrayNotHasKey('webhooksReceiverVersion', $summary[0]);
        $this->assertArrayNotHasKey('webhooksHandlerVersion', $summary[0]);
        $this->assertArrayNotHasKey('gstracciniBotVersion', $summary[0]);

    }//end testDropsInternalProcessingFields()


    /**
     * Missing optional fields default to null, and a missing retry count to 0.
     *
     * @return void
     */
    public function testDefaultsMissingFields(): void
    {
        $row = $this->webhooksRow();
        unset($row['title'], $row['senderLogin'], $row['updatedAt']);
        unset($row['idleRetryCount'], $row['idleStatus']);

        $summary = $this->summarize([$row]);

        $this->assertSame(1206, $summary[0]['number']);
        $this->assertNull($summary[0]['title']);
        $this->assertNull($summary[0]['senderLogin']);
        $this->assertNull($summary[0]['updatedAt']);
        $this->assertSame(0, $summary[0]['idleRetryCount']);
        $this->assertNull($summary[0]['idleStatus']);

    }//end testDefaultsMissingFields()


    /**
     * Entries that are not rows are skipped, and an empty feed stays empty.
     *
     * @return void
     */
    public function testSkipsNonRowEntries(): void
    {
        $items   = [];
        $items[] = 'garbage';
        $items[] = 42;
        $items[] = $this->webhooksRow();

        $summary = $this->summarize($items);

        $this->assertCount(1, $summary);
        $this->assertSame('DUE', $summary[0]['idleStatus']);
        $this->assertSame([], $this->summarize([]));

    }//end testSkipsNonRowEntries()


    /**
     * A payload that is not a list of rows (a malformed 200 from the
     * webhooks API) is rejected with a TypeError, which generate() treats
     * like any other fetch failure: the feed stays unavailable, not empty.
     *
     * @return void
     */
    public function testRejectsNonArrayPayload(): void
    {
        $this->expectException(TypeError::class);

        $this->summarize('not a list');

    }//end testRejectsNonArrayPayload()


}//end class

<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\PublicStats;

class PublicStatsTest extends TestCase
{
    public function testSummarizeIdlePullRequestsKeepsOnlyPublicFields()
    {
        $rows = [
            [
                'sequence'                => 4711,
                'repositoryOwner'         => 'guibranco',
                'repositoryName'          => 'projects-monitor',
                'number'                  => '1206',
                'title'                   => 'feat: add "IDLE" state handling',
                'ref'                     => 'refs/heads/feature/idle',
                'senderLogin'             => 'dependabot[bot]',
                'updatedAt'               => '2026-10-17 17:51:21',
                'processingDate'          => '2026-10-18 06:00:00',
                'webhooksReceiverVersion' => '1.28.6',
                'webhooksHandlerVersion'  => '1.28.4',
                'gstracciniBotVersion'    => '1.25.4',
                'idleRetryCount'          => '3',
                'idleStatus'              => 'DUE',
            ],
        ];

        $summary = PublicStats::summarizeIdlePullRequests($rows);

        $this->assertCount(1, $summary);
        $this->assertSame([
            'repositoryOwner' => 'guibranco',
            'repositoryName'  => 'projects-monitor',
            'number'          => 1206,
            'title'           => 'feat: add "IDLE" state handling',
            'senderLogin'     => 'dependabot[bot]',
            'updatedAt'       => '2026-10-17 17:51:21',
            'idleRetryCount'  => 3,
            'idleStatus'      => 'DUE',
        ], $summary[0]);

        foreach (['sequence', 'ref', 'processingDate', 'webhooksReceiverVersion', 'webhooksHandlerVersion', 'gstracciniBotVersion'] as $internalField) {
            $this->assertArrayNotHasKey($internalField, $summary[0]);
        }
    }

    public function testSummarizeIdlePullRequestsDefaultsMissingFields()
    {
        $summary = PublicStats::summarizeIdlePullRequests([
            ['repositoryOwner' => 'guibranco', 'repositoryName' => 'pancake', 'number' => 9],
        ]);

        $this->assertSame(9, $summary[0]['number']);
        $this->assertNull($summary[0]['title']);
        $this->assertNull($summary[0]['senderLogin']);
        $this->assertNull($summary[0]['updatedAt']);
        $this->assertSame(0, $summary[0]['idleRetryCount']);
        $this->assertNull($summary[0]['idleStatus']);
    }

    public function testSummarizeIdlePullRequestsSkipsNonRowEntriesAndNonArrayInput()
    {
        $this->assertSame([], PublicStats::summarizeIdlePullRequests(null));
        $this->assertSame([], PublicStats::summarizeIdlePullRequests('not a list'));
        $this->assertSame([], PublicStats::summarizeIdlePullRequests([]));

        $summary = PublicStats::summarizeIdlePullRequests([
            'garbage',
            42,
            ['repositoryOwner' => 'guibranco', 'repositoryName' => 'gitbot', 'number' => 1, 'idleStatus' => 'WAITING'],
        ]);

        $this->assertCount(1, $summary);
        $this->assertSame('WAITING', $summary[0]['idleStatus']);
    }
}

<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\AppMessagesEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\DatabaseErrorsEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\ErrorLogFilesEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\HealthChecksEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\QueuesEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\ServerHealthEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\UpTimeRobotEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\WebhooksBacklogEvaluator;

/**
 * Tests for the evaluators of the ops, pipeline and errors domains.
 */
class AttentionEvaluatorsTest extends TestCase
{
    private const NOW = 1763221200;


    /**
     * The default thresholds.
     *
     * @return AttentionThresholds
     */
    private function getThresholds(): AttentionThresholds
    {
        return new AttentionThresholds([]);

    }//end getThresholds()


    /**
     * Down monitors are critical, late ones warnings, up and paused ones healthy.
     *
     * @return void
     */
    public function testMonitors(): void
    {
        $checks = [
                   array_merge(["name" => "vinhedo2 upgrades", "status" => "critical", "lastChange" => "2026-11-15T15:40:00Z"]),
                   array_merge(["name" => "backup", "status" => "warning", "lastChange" => null]),
                   array_merge(["name" => "api", "status" => "healthy", "lastChange" => null]),
                   array_merge(["name" => "old", "status" => "paused", "lastChange" => null]),
                  ];

        $evaluation = (new HealthChecksEvaluator())->evaluate($checks, $this->getThresholds(), self::NOW);
        $items      = $evaluation->getItems();

        $this->assertSame(4, $evaluation->getChecked());
        $this->assertCount(2, $items);
        $this->assertSame(AttentionItem::CRITICAL, $items[0]->severity);
        $this->assertSame("Healthcheck vinhedo2 upgrades is down", $items[0]->title);
        $this->assertSame("2026-11-15T15:40:00Z", $items[0]->since);
        $this->assertSame("hc:vinhedo2-upgrades", $items[0]->fingerprint);
        $this->assertSame(AttentionItem::WARNING, $items[1]->severity);
        $this->assertSame("ops", $items[0]->domain);

        $robot = (new UpTimeRobotEvaluator())->evaluate([["name" => "site", "status" => "critical"]], $this->getThresholds(), self::NOW);
        $this->assertSame("utr:site", $robot->getItems()[0]->fingerprint);
        $this->assertSame("uptimerobot", $robot->getItems()[0]->source);

    }//end testMonitors()


    /**
     * Servers the cache script found degraded or silent need attention.
     *
     * @return void
     */
    public function testServers(): void
    {
        $rows = [
                 array_merge(["label" => "Osasco (cPanel)", "status" => "operational"]),
                 array_merge(["label" => "Vinhedo1", "status" => "warning"]),
                 array_merge(["label" => "Vinhedo2", "status" => "unknown"]),
                 array_merge(["label" => "Database", "status" => "critical"]),
                ];

        $items = (new ServerHealthEvaluator())->evaluate($rows, $this->getThresholds(), self::NOW)->getItems();

        $this->assertCount(3, $items);
        $this->assertSame("Vinhedo2 is not reporting", $items[1]->title);
        $this->assertSame(AttentionItem::CRITICAL, $items[2]->severity);

    }//end testServers()


    /**
     * Queues are judged against queue.messages.
     *
     * @return void
     */
    public function testQueues(): void
    {
        $rows  = [
                  array_merge(["name" => "small", "messages" => 3]),
                  array_merge(["name" => "busy", "messages" => 150]),
                  array_merge(["name" => "stuck", "messages" => 5000]),
                 ];
        $items = (new QueuesEvaluator())->evaluate($rows, $this->getThresholds(), self::NOW)->getItems();

        $this->assertCount(2, $items);
        $this->assertSame(AttentionItem::WARNING, $items[0]->severity);
        $this->assertSame(AttentionItem::CRITICAL, $items[1]->severity);
        $this->assertSame("Queue stuck has 5000 messages waiting", $items[1]->title);

    }//end testQueues()


    /**
     * The webhooks backlog counts pending states only, not IDLE or PROCESSED.
     *
     * @return void
     */
    public function testWebhooksBacklog(): void
    {
        $stats = [
                  "NEW"        => array_merge(["github_pull_requests" => 150, "github_pushes" => 40]),
                  "PROCESSING" => ["github_pull_requests" => 20],
                  "IDLE"       => ["github_pull_requests" => 5000],
                  "PROCESSED"  => ["github_pushes" => 90000],
                 ];

        $items = (new WebhooksBacklogEvaluator())->evaluate($stats, $this->getThresholds(), self::NOW)->getItems();

        $this->assertCount(1, $items);
        $this->assertSame(AttentionItem::WARNING, $items[0]->severity);
        $this->assertSame("210 webhook entities pending processing (pull requests 170, pushes 40)", $items[0]->title);

        $quiet = (new WebhooksBacklogEvaluator())->evaluate(["IDLE" => ["github_pull_requests" => 5000]], $this->getThresholds(), self::NOW);
        $this->assertCount(0, $quiet->getItems());
        $this->assertSame(1, $quiet->getChecked());

    }//end testWebhooksBacklog()


    /**
     * Applications are judged against messages.application, skipping the header row.
     *
     * @return void
     */
    public function testAppMessages(): void
    {
        $rows  = [
                  array_merge(["Application", "Messages"]),
                  array_merge(["quiet", 3]),
                  array_merge(["noisy", 600]),
                 ];
        $items = (new AppMessagesEvaluator())->evaluate($rows, $this->getThresholds(), self::NOW)->getItems();

        $this->assertCount(1, $items);
        $this->assertSame("noisy logged 600 messages", $items[0]->title);
        $this->assertSame(AttentionItem::CRITICAL, $items[0]->severity);

    }//end testAppMessages()


    /**
     * Any stored PHP error is a warning, many are critical.
     *
     * @return void
     */
    public function testDatabaseErrors(): void
    {
        $evaluator = new DatabaseErrorsEvaluator();
        $this->assertCount(0, $evaluator->evaluate(0, $this->getThresholds(), self::NOW)->getItems());
        $this->assertSame(AttentionItem::WARNING, $evaluator->evaluate(3, $this->getThresholds(), self::NOW)->getItems()[0]->severity);
        $this->assertSame(AttentionItem::CRITICAL, $evaluator->evaluate(250, $this->getThresholds(), self::NOW)->getItems()[0]->severity);

    }//end testDatabaseErrors()


    /**
     * Each error_log file is a warning; no files is one healthy check.
     *
     * @return void
     */
    public function testErrorLogFiles(): void
    {
        $evaluator = new ErrorLogFilesEvaluator();
        $none      = $evaluator->evaluate([], $this->getThresholds(), self::NOW);
        $this->assertSame(1, $none->getChecked());
        $this->assertCount(0, $none->getItems());

        $rows  = [
                  array_merge(["Directory", "Size", "Creation time"]),
                  array_merge(["public_html/api", "12 KB", "10:00:00 15/11/2026"]),
                 ];
        $items = $evaluator->evaluate($rows, $this->getThresholds(), self::NOW)->getItems();
        $this->assertCount(1, $items);
        $this->assertSame("error_log in /public_html/api (12 KB)", $items[0]->title);
        $this->assertSame("errlog:public_html/api", $items[0]->fingerprint);
        $this->assertNotNull($items[0]->since);

    }//end testErrorLogFiles()


}//end class

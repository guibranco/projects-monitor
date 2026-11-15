<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Attention\Fingerprint;
use GuiBranco\ProjectsMonitor\Library\Attention\TimeFormatter;

/**
 * Tests for AttentionThresholds, AttentionItem, Fingerprint and TimeFormatter.
 */
class AttentionThresholdsTest extends TestCase
{


    /**
     * The defaults come from attention-thresholds.json and overrides win.
     *
     * @return void
     */
    public function testDefaultsAndOverrides(): void
    {
        $defaults = new AttentionThresholds([]);
        $this->assertSame(100.0, $defaults->getValue("queue.messages.warning"));

        $overridden = new AttentionThresholds(["queue.messages.warning" => "5", "not.numeric" => "x"]);
        $this->assertSame(5.0, $overridden->getValue("queue.messages.warning"));
        $this->assertArrayNotHasKey("not.numeric", $overridden->getAll());

    }//end testDefaultsAndOverrides()


    /**
     * A value is critical, warning or nothing against a threshold pair.
     *
     * @return void
     */
    public function testSeverity(): void
    {
        $thresholds = new AttentionThresholds(["queue.messages.warning" => 10, "queue.messages.critical" => 20]);
        $this->assertNull($thresholds->getSeverity("queue.messages", 9));
        $this->assertSame(AttentionItem::WARNING, $thresholds->getSeverity("queue.messages", 10));
        $this->assertSame(AttentionItem::CRITICAL, $thresholds->getSeverity("queue.messages", 20));

    }//end testSeverity()


    /**
     * An unknown threshold is a programming error.
     *
     * @return void
     */
    public function testUnknownThresholdThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AttentionThresholds([]))->getValue("nope");

    }//end testUnknownThresholdThrows()


    /**
     * An item serialises with the default actions around its own.
     *
     * @return void
     */
    public function testItemToArray(): void
    {
        $item  = new AttentionItem("ci", "ci", AttentionItem::CRITICAL, "Build failed", null, "index.php", "wf:a");
        $rerun = $item->withAction("rerun", ["workflowRunId" => 7]);

        $this->assertSame(["open", "snooze", "acknowledge"], $item->toArray()["actions"]);
        $this->assertSame(["open", "rerun", "snooze", "acknowledge"], $rerun->toArray()["actions"]);
        $this->assertSame(7, $rerun->toArray()["meta"]->workflowRunId);
        $this->assertTrue($item->isCritical());

    }//end testItemToArray()


    /**
     * Fingerprints are lowercase slugs.
     *
     * @return void
     */
    public function testFingerprint(): void
    {
        $fingerprint = new Fingerprint();
        $this->assertSame("hc:vinhedo2-unattended-upgrades", $fingerprint->make("hc", "Vinhedo2 Unattended Upgrades!"));
        $this->assertSame("wf:guibranco/repo/.github/workflows/build.yml", $fingerprint->make("wf", "guibranco/repo/.github/workflows/build.yml"));

    }//end testFingerprint()


    /**
     * Timestamps normalise to ISO 8601 UTC.
     *
     * @return void
     */
    public function testTimeFormatter(): void
    {
        $time = new TimeFormatter();
        $this->assertSame("2026-11-15T15:40:00Z", $time->toIso("2026-11-15T15:40:00+00:00"));
        $this->assertNull($time->toIso(""));
        $this->assertNull($time->toIso(null));
        $this->assertNull($time->toIso("not a date"));
        $this->assertNull($time->fromDashboardFormat("garbage"));
        $this->assertMatchesRegularExpression("/^2026-11-1[45]T\\d{2}:\\d{2}:00Z$/", (string) $time->fromDashboardFormat("10:00:00 15/11/2026"));

    }//end testTimeFormatter()


}//end class

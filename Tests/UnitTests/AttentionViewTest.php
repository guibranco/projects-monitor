<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionStore;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionView;

/**
 * Tests for AttentionView.
 */
class AttentionViewTest extends TestCase
{
    private const NOW = 1763221200;


    /**
     * An item as the snapshot holds it.
     *
     * @param string      $fingerprint Its fingerprint, also its title.
     * @param string      $domain      Its domain.
     * @param string      $severity    Its severity.
     * @param string|null $since       Since when.
     *
     * @return array
     */
    private function getItem(string $fingerprint, string $domain, string $severity, ?string $since): array
    {
        return (new AttentionItem("test", $domain, $severity, $fingerprint, $since, "index.php", $fingerprint))->toArray();

    }//end getItem()


    /**
     * A snapshot of the given items, out of 10 checks.
     *
     * @param array $items The items.
     *
     * @return array
     */
    private function getSnapshot(array $items): array
    {
        $snapshot = [];

        $snapshot["generatedAt"] = gmdate("Y-m-d\TH:i:s\Z", (self::NOW - 40));
        $snapshot["items"]       = $items;
        $snapshot["sources"]     = [];
        $snapshot["checked"]     = 10;

        return $snapshot;

    }//end getSnapshot()


    /**
     * Snoozed items are hidden, acknowledged ones kept last and not counted,
     * the rest ordered critical first then oldest first.
     *
     * @return void
     */
    public function testStatesAndOrder(): void
    {
        $items = [
                  $this->getItem("warn-old", "ops", AttentionItem::WARNING, "2026-11-01T00:00:00Z"),
                  $this->getItem("crit-new", "ci", AttentionItem::CRITICAL, "2026-11-15T00:00:00Z"),
                  $this->getItem("crit-old", "ops", AttentionItem::CRITICAL, "2026-11-10T00:00:00Z"),
                  $this->getItem("crit-unknown", "errors", AttentionItem::CRITICAL, null),
                  $this->getItem("snoozed", "ops", AttentionItem::CRITICAL, null),
                  $this->getItem("acked", "ops", AttentionItem::CRITICAL, "2026-11-01T00:00:00Z"),
                 ];
        $states = [
                   "snoozed" => array_merge(["state" => AttentionStore::SNOOZED, "snoozedUntil" => (self::NOW + 60), "acknowledgedSince" => null]),
                   "acked"   => array_merge(["state" => AttentionStore::ACKNOWLEDGED, "snoozedUntil" => null, "acknowledgedSince" => "2026-11-01T00:00:00Z"]),
                  ];

        $response = (new AttentionView(self::NOW))->build($this->getSnapshot($items), $states, null);

        $this->assertSame(["crit-old", "crit-new", "crit-unknown", "warn-old", "acked"], array_column($response["items"], "title"));
        $this->assertSame("acknowledged", $response["items"][4]["state"]);
        $this->assertSame(1, $response["summary"]["snoozed"]);
        $this->assertSame(1, $response["summary"]["acknowledged"]);
        $this->assertSame(4, $response["summary"]["active"]);
        $this->assertSame(3, $response["summary"]["critical"]);
        $this->assertSame(4, $response["summary"]["healthy"]);

    }//end testStatesAndOrder()


    /**
     * An ended snooze no longer hides the item, and an acknowledgement
     * lapses when the item's since value changes.
     *
     * @return void
     */
    public function testStatesLapse(): void
    {
        $items  = [
                   $this->getItem("snoozed", "ops", AttentionItem::CRITICAL, null),
                   $this->getItem("acked", "ops", AttentionItem::CRITICAL, "2026-11-15T00:00:00Z"),
                  ];
        $states = [
                   "snoozed" => array_merge(["state" => AttentionStore::SNOOZED, "snoozedUntil" => (self::NOW - 1), "acknowledgedSince" => null]),
                   "acked"   => array_merge(["state" => AttentionStore::ACKNOWLEDGED, "snoozedUntil" => null, "acknowledgedSince" => "2026-11-01T00:00:00Z"]),
                  ];

        $result = (new AttentionView(self::NOW))->applyStates($items, $states);

        $this->assertSame(["active", "active"], array_column($result, "state"));

    }//end testStatesLapse()


    /**
     * Each domain tile shows the worst active severity and the active count.
     *
     * @return void
     */
    public function testDomainTiles(): void
    {
        $items  = [
                   $this->getItem("a", "ops", AttentionItem::WARNING, null),
                   $this->getItem("b", "ops", AttentionItem::CRITICAL, null),
                   $this->getItem("c", "ci", AttentionItem::WARNING, null),
                   $this->getItem("d", "errors", AttentionItem::CRITICAL, null),
                  ];
        $states = [
                   "d" => array_merge(["state" => AttentionStore::ACKNOWLEDGED, "snoozedUntil" => null, "acknowledgedSince" => null]),
                  ];

        $tiles = (new AttentionView(self::NOW))->build($this->getSnapshot($items), $states, null)["domains"];
        $byDomain = array_column($tiles, null, "domain");

        $this->assertSame(["ops", "pipeline", "errors", "ci", "github", "domains"], array_column($tiles, "domain"));
        $this->assertSame("critical", $byDomain["ops"]["severity"]);
        $this->assertSame(2, $byDomain["ops"]["count"]);
        $this->assertSame("warning", $byDomain["ci"]["severity"]);
        $this->assertSame("ok", $byDomain["errors"]["severity"]);
        $this->assertSame(0, $byDomain["errors"]["count"]);

    }//end testDomainTiles()


    /**
     * The freshness block reports the snapshot age and whether the cache script is stale.
     *
     * @return void
     */
    public function testFreshness(): void
    {
        $view = new AttentionView(self::NOW);

        $fresh = $view->build($this->getSnapshot([]), [], ["updatedAt" => (self::NOW - 120), "staleSeconds" => 900])["freshness"];
        $this->assertSame(40, $fresh["ageSeconds"]);
        $this->assertSame(120, $fresh["cacheAgeSeconds"]);
        $this->assertFalse($fresh["cacheStale"]);

        $stale = $view->build($this->getSnapshot([]), [], ["updatedAt" => (self::NOW - 3600), "staleSeconds" => 900])["freshness"];
        $this->assertTrue($stale["cacheStale"]);

        $missing = $view->build($this->getSnapshot([]), [], ["updatedAt" => null, "staleSeconds" => 900])["freshness"];
        $this->assertTrue($missing["cacheStale"]);
        $this->assertNull($missing["cacheAgeSeconds"]);

    }//end testFreshness()


}//end class

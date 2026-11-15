<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionCollector;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionFeed;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionStore;

/**
 * Tests for AttentionFeed: snapshot caching, refresh, pruning and freshness.
 */
class AttentionFeedTest extends TestCase
{
    private const NOW = 1763221200;

    /**
     * A temporary directory for the cache and public stats files.
     *
     * @var string
     */
    private string $directory;


    /**
     * Creates the temporary directory.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->directory = sprintf("%s/attention-feed-%s", sys_get_temp_dir(), uniqid());
        mkdir($this->directory);

    }//end setUp()


    /**
     * Removes the temporary directory.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        array_map("unlink", glob(sprintf("%s/*", $this->directory)));
        rmdir($this->directory);

    }//end tearDown()


    /**
     * A snapshot with one critical item, as the collector returns it.
     *
     * @param string $status The status of its single source.
     *
     * @return array
     */
    private function getSnapshot(string $status): array
    {
        $item     = new AttentionItem("queues", "pipeline", AttentionItem::CRITICAL, "Queue stuck", null, "index.php", "queue:stuck");
        $source   = array_merge(["source" => "queues", "domain" => "pipeline", "status" => $status]);
        $snapshot = [];

        $snapshot["generatedAt"] = gmdate("Y-m-d\TH:i:s\Z", self::NOW);
        $snapshot["items"]       = [$item->toArray()];
        $snapshot["sources"]     = [$source];
        $snapshot["checked"]     = 3;

        return $snapshot;

    }//end getSnapshot()


    /**
     * A collector that returns the given snapshot, expected to run $times times.
     *
     * @param array   $snapshot The snapshot.
     * @param integer $times    How many times collect() must run.
     *
     * @return AttentionCollector
     */
    private function createCollector(array $snapshot, int $times): AttentionCollector
    {
        $collector = $this->createMock(AttentionCollector::class);
        $collector->expects($this->exactly($times))->method("collect")->willReturn($snapshot);
        return $collector;

    }//end createCollector()


    /**
     * A feed reading and writing the temporary directory.
     *
     * @param AttentionStore|null $store     The store.
     * @param AttentionCollector  $collector The collector.
     *
     * @return AttentionFeed
     */
    private function createFeed(?AttentionStore $store, AttentionCollector $collector): AttentionFeed
    {
        return new AttentionFeed($store, $collector, sprintf("%s/attention.json", $this->directory), sprintf("%s/public-stats.json", $this->directory));

    }//end createFeed()


    /**
     * Without a cache the feed collects, caches the snapshot and prunes stale states.
     *
     * @return void
     */
    public function testCollectsCachesAndPrunes(): void
    {
        $store = $this->createMock(AttentionStore::class);
        $store->method("getThresholdOverrides")->willReturn([]);
        $store->method("getStates")->willReturn(array_merge(["queue:stuck" => ["state" => AttentionStore::SNOOZED, "snoozedUntil" => (self::NOW + 60), "acknowledgedSince" => null]]));
        $store->expects($this->once())->method("prune")->with(["queue:stuck"], self::NOW);

        $response = $this->createFeed($store, $this->createCollector($this->getSnapshot("ok"), 1))->getResponse(false, self::NOW);

        $this->assertFileExists(sprintf("%s/attention.json", $this->directory));
        $this->assertSame([], $response["items"]);
        $this->assertSame(1, $response["summary"]["snoozed"]);
        $this->assertTrue($response["freshness"]["cacheStale"]);

    }//end testCollectsCachesAndPrunes()


    /**
     * A recent cache is served without collecting; refresh collects anyway.
     *
     * @return void
     */
    public function testServesRecentCacheUnlessRefreshed(): void
    {
        file_put_contents(sprintf("%s/attention.json", $this->directory), json_encode($this->getSnapshot("ok")));
        touch(sprintf("%s/attention.json", $this->directory), (self::NOW - 10));

        $cached = $this->createFeed(null, $this->createCollector([], 0))->getResponse(false, self::NOW);
        $this->assertSame("Queue stuck", $cached["items"][0]["title"]);

        $refreshed = $this->createFeed(null, $this->createCollector($this->getSnapshot("ok"), 1))->getResponse(true, self::NOW);
        $this->assertCount(1, $refreshed["items"]);

    }//end testServesRecentCacheUnlessRefreshed()


    /**
     * An expired or unreadable cache is collected again.
     *
     * @return void
     */
    public function testExpiredOrBrokenCacheIsCollectedAgain(): void
    {
        $cacheFile = sprintf("%s/attention.json", $this->directory);
        file_put_contents($cacheFile, json_encode($this->getSnapshot("ok")));
        touch($cacheFile, (self::NOW - 3600));
        $this->createFeed(null, $this->createCollector($this->getSnapshot("ok"), 1))->getResponse(false, self::NOW);

        file_put_contents($cacheFile, "not json");
        touch($cacheFile, self::NOW);
        $this->createFeed(null, $this->createCollector($this->getSnapshot("ok"), 1))->getResponse(false, self::NOW);

    }//end testExpiredOrBrokenCacheIsCollectedAgain()


    /**
     * A failing source keeps the stored states: nothing is pruned.
     *
     * @return void
     */
    public function testDoesNotPruneWhenASourceFailed(): void
    {
        $store = $this->createMock(AttentionStore::class);
        $store->method("getThresholdOverrides")->willReturn([]);
        $store->method("getStates")->willReturn([]);
        $store->expects($this->never())->method("prune");

        $this->createFeed($store, $this->createCollector($this->getSnapshot("error"), 1))->getResponse(false, self::NOW);

    }//end testDoesNotPruneWhenASourceFailed()


    /**
     * Threshold overrides apply, a failing store read is ignored, and the
     * public stats file's age drives the freshness indicator.
     *
     * @return void
     */
    public function testOverridesStoreFailureAndFreshness(): void
    {
        $statsFile = sprintf("%s/public-stats.json", $this->directory);
        file_put_contents($statsFile, "{}");
        touch($statsFile, (self::NOW - 120));

        $store = $this->createMock(AttentionStore::class);
        $store->method("getThresholdOverrides")->willReturn(array_merge(["cache.publicStats.staleSeconds" => 60]));
        $store->method("getStates")->willThrowException(new RuntimeException("table missing"));

        $response = $this->createFeed($store, $this->createCollector($this->getSnapshot("ok"), 1))->getResponse(false, self::NOW);

        $this->assertSame(120, $response["freshness"]["cacheAgeSeconds"]);
        $this->assertSame(60, $response["freshness"]["cacheStaleSeconds"]);
        $this->assertTrue($response["freshness"]["cacheStale"]);
        $this->assertSame("active", $response["items"][0]["state"]);

    }//end testOverridesStoreFailureAndFreshness()


}//end class

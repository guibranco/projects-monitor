<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

use GuiBranco\ProjectsMonitor\Library\LogStream;
use GuiBranco\ProjectsMonitor\Library\PublicStats;

/**
 * The attention feed behind GET /api/v1/attention: collects from every source
 * at most once per cache.attention.ttlSeconds (the snapshot is cached in
 * Src/cache/attention.json), then applies the stored snoozes and
 * acknowledgements on every read so they take effect at once.
 */
class AttentionFeed
{
    public const CACHE_FILE = __DIR__."/../../cache/attention.json";

    /**
     * Snoozes, acknowledgements and threshold overrides; null when the database is unavailable.
     *
     * @var AttentionStore|null
     */
    private ?AttentionStore $store = null;


    /**
     * Connects to the store, carrying on without it when the database is unavailable.
     */
    public function __construct()
    {
        try {
            $this->store = new AttentionStore();
        } catch (\Throwable $exception) {
            (new LogStream())->warning("Attention store unavailable: {reason}", ["reason" => $exception->getMessage()], "attention");
        }

    }//end __construct()


    /**
     * The attention API response.
     *
     * @param boolean $refresh Collect again even when the cached snapshot is recent.
     *
     * @return array
     */
    public function getResponse(bool $refresh): array
    {
        $now        = time();
        $thresholds = new AttentionThresholds($this->readStore("getThresholdOverrides"));
        $snapshot   = $this->readCache($thresholds, $now);
        if ($refresh === true || $snapshot === null) {
            $snapshot = $this->refresh($thresholds, $now);
        }

        $freshness = [];

        $freshness["updatedAt"]    = $this->getPublicStatsTime();
        $freshness["staleSeconds"] = $thresholds->getValue("cache.publicStats.staleSeconds");

        $view = new AttentionView($now);
        return $view->build($snapshot, $this->readStore("getStates"), $freshness);

    }//end getResponse()


    /**
     * Collects a new snapshot, caches it and, when every source answered,
     * prunes ended snoozes and acknowledgements of items no longer raised.
     *
     * @param AttentionThresholds $thresholds The thresholds.
     * @param integer             $now        The current Unix time.
     *
     * @return array
     */
    private function refresh(AttentionThresholds $thresholds, int $now): array
    {
        $collector = new AttentionCollector((new AttentionSources())->getEvaluators());
        $snapshot  = $collector->collect($thresholds, $now);
        $cacheDir  = dirname(self::CACHE_FILE);
        if (is_dir($cacheDir) === true && is_writable($cacheDir) === true) {
            file_put_contents(self::CACHE_FILE, json_encode($snapshot), LOCK_EX);
        }

        $failed = array_filter($snapshot["sources"], fn ($source) => $source["status"] === "error");
        if ($this->store !== null && count($failed) === 0) {
            $this->store->prune(array_column($snapshot["items"], "fingerprint"), $now);
        }

        return $snapshot;

    }//end refresh()


    /**
     * The cached snapshot, or null when there is none or it is older than cache.attention.ttlSeconds.
     *
     * @param AttentionThresholds $thresholds The thresholds.
     * @param integer             $now        The current Unix time.
     *
     * @return array|null
     */
    private function readCache(AttentionThresholds $thresholds, int $now): ?array
    {
        if (file_exists(self::CACHE_FILE) === false) {
            return null;
        }

        if (($now - (int) filemtime(self::CACHE_FILE)) >= $thresholds->getValue("cache.attention.ttlSeconds")) {
            return null;
        }

        $snapshot = json_decode((string) file_get_contents(self::CACHE_FILE), true);
        if (is_array($snapshot) === false || isset($snapshot["items"], $snapshot["sources"]) === false) {
            return null;
        }

        return $snapshot;

    }//end readCache()


    /**
     * When the public stats worker (the cache script) last wrote its file, null when it never did.
     *
     * @return integer|null
     */
    private function getPublicStatsTime(): ?int
    {
        if (file_exists(PublicStats::CACHE_FILE) === false) {
            return null;
        }

        return (int) filemtime(PublicStats::CACHE_FILE);

    }//end getPublicStatsTime()


    /**
     * Calls a reader of the store, an empty list when the store is unavailable or the read fails.
     *
     * @param string $method getStates or getThresholdOverrides.
     *
     * @return array
     */
    private function readStore(string $method): array
    {
        if ($this->store === null) {
            return [];
        }

        try {
            return $this->store->$method();
        } catch (\Throwable $exception) {
            (new LogStream())->warning("Attention store read {method} failed: {reason}", ["method" => $method, "reason" => $exception->getMessage()], "attention");
            return [];
        }

    }//end readStore()


}//end class

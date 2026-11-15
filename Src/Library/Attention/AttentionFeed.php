<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

use GuiBranco\ProjectsMonitor\Library\LogStream;

/**
 * The attention feed behind GET /api/v1/attention: collects from every source
 * at most once per cache.attention.ttlSeconds (the snapshot is cached in a
 * JSON file), then applies the stored snoozes and acknowledgements on every
 * read so they take effect at once.
 */
class AttentionFeed
{
    public const CACHE_FILE = __DIR__."/../../cache/attention.json";

    /**
     * Snoozes, acknowledgements and threshold overrides; null when the database is unavailable.
     *
     * @var AttentionStore|null
     */
    private ?AttentionStore $store;

    /**
     * Runs the evaluators.
     *
     * @var AttentionCollector
     */
    private AttentionCollector $collector;

    /**
     * Where the snapshot is cached.
     *
     * @var string
     */
    private string $cacheFile;

    /**
     * The public stats worker's (the cache script's) output file, whose age is the freshness indicator.
     *
     * @var string
     */
    private string $publicStatsFile;


    /**
     * Creates the feed.
     *
     * @param AttentionStore|null $store           The store, null when the database is unavailable.
     * @param AttentionCollector  $collector       Runs the evaluators.
     * @param string              $cacheFile       Where the snapshot is cached.
     * @param string              $publicStatsFile The public stats worker's output file.
     */
    public function __construct(?AttentionStore $store, AttentionCollector $collector, string $cacheFile, string $publicStatsFile)
    {
        $this->store           = $store;
        $this->collector       = $collector;
        $this->cacheFile       = $cacheFile;
        $this->publicStatsFile = $publicStatsFile;

    }//end __construct()


    /**
     * The attention API response.
     *
     * @param boolean $refresh Collect again even when the cached snapshot is recent.
     * @param integer $now     The current Unix time.
     *
     * @return array
     */
    public function getResponse(bool $refresh, int $now): array
    {
        $thresholds = new AttentionThresholds($this->readStore("getThresholdOverrides"));
        $snapshot   = null;
        if ($refresh === false) {
            $snapshot = $this->readCache($thresholds, $now);
        }

        if ($snapshot === null) {
            $snapshot = $this->refresh($thresholds, $now);
        }

        $freshness = [];

        $freshness["updatedAt"]    = $this->getFileTime($this->publicStatsFile);
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
        $snapshot = $this->collector->collect($thresholds, $now);
        $cacheDir = dirname($this->cacheFile);
        if (is_dir($cacheDir) === true && is_writable($cacheDir) === true) {
            file_put_contents($this->cacheFile, json_encode($snapshot), LOCK_EX);
        }

        $failed = array_filter($snapshot["sources"], fn ($source) => $source["status"] === "error");
        if ($this->store !== null && count($failed) === 0) {
            $this->store->prune(array_column($snapshot["items"], "fingerprint"), $now);
        }

        return $snapshot;

    }//end refresh()


    /**
     * The cached snapshot, or null when there is none, it is older than
     * cache.attention.ttlSeconds or it cannot be read.
     *
     * @param AttentionThresholds $thresholds The thresholds.
     * @param integer             $now        The current Unix time.
     *
     * @return array|null
     */
    private function readCache(AttentionThresholds $thresholds, int $now): ?array
    {
        $writtenAt = $this->getFileTime($this->cacheFile);
        if ($writtenAt === null || ($now - $writtenAt) >= $thresholds->getValue("cache.attention.ttlSeconds")) {
            return null;
        }

        $snapshot = json_decode((string) file_get_contents($this->cacheFile), true);
        if (is_array($snapshot) === false || isset($snapshot["items"], $snapshot["sources"]) === false) {
            return null;
        }

        return $snapshot;

    }//end readCache()


    /**
     * When a file was last written, null when it does not exist.
     *
     * @param string $path The file.
     *
     * @return integer|null
     */
    private function getFileTime(string $path): ?int
    {
        if (file_exists($path) === false) {
            return null;
        }

        return (int) filemtime($path);

    }//end getFileTime()


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

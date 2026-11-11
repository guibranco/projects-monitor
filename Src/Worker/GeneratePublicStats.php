#!/usr/bin/env php
<?php

/**
 * GeneratePublicStats — worker that pre-computes the public dashboard stats
 * and writes them to a JSON cache file consumed by api/v1/public-stats.php.
 *
 * Recommended cron entry (every 5 minutes):
 *
 *   *\/5 * * * * php /home/<username>/public_html/Src/Worker/GeneratePublicStats.php \
 *       >> /var/log/projects-monitor-public-stats.log 2>&1
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\PublicStats;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::initialize();
LogStream::info("{worker} worker started", ["worker" => "GeneratePublicStats"], "worker");

$startedAt = microtime(true);
$cacheFile = PublicStats::CACHE_FILE;
$cacheDir  = dirname($cacheFile);

if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true)) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR: could not create cache directory ' . $cacheDir . PHP_EOL);
    LogStream::critical("{worker} could not create cache directory {path}", ["worker" => "GeneratePublicStats", "path" => $cacheDir], "worker");
    exit(1);
}

$data = PublicStats::generate();
$json = json_encode($data, JSON_UNESCAPED_UNICODE);

if ($json === false) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR: json_encode failed — ' . json_last_error_msg() . PHP_EOL);
    LogStream::error("{worker} failed to encode public stats as JSON: {reason}", [
        "worker" => "GeneratePublicStats",
        "reason" => json_last_error_msg(),
    ], "worker");
    exit(1);
}

if (file_put_contents($cacheFile, $json, LOCK_EX) === false) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR: could not write cache file ' . $cacheFile . PHP_EOL);
    LogStream::error("{worker} could not write cache file {path}", [
        "worker" => "GeneratePublicStats",
        "path" => $cacheFile,
        "bytes" => strlen($json),
    ], "worker");
    exit(1);
}

LogStream::info("{worker} worker completed in {elapsed_seconds}s, wrote {bytes} bytes to {cache_file}", [
    "worker" => "GeneratePublicStats",
    "cache_file" => $cacheFile,
    "bytes" => strlen($json),
    "elapsed_seconds" => round(microtime(true) - $startedAt, 2),
], "worker");
exit(0);

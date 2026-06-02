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
LogStream::info("GeneratePublicStats worker started", null, "worker");

$cacheFile = PublicStats::CACHE_FILE;
$cacheDir  = dirname($cacheFile);

if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true)) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR: could not create cache directory ' . $cacheDir . PHP_EOL);
    LogStream::critical("Could not create cache directory", ["path" => $cacheDir], "worker");
    exit(1);
}

$data = PublicStats::generate();
$json = json_encode($data, JSON_UNESCAPED_UNICODE);

if ($json === false) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR: json_encode failed — ' . json_last_error_msg() . PHP_EOL);
    LogStream::error("json_encode failed", ["reason" => json_last_error_msg()], "worker");
    exit(1);
}

if (file_put_contents($cacheFile, $json, LOCK_EX) === false) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR: could not write cache file ' . $cacheFile . PHP_EOL);
    LogStream::error("Could not write cache file", ["path" => $cacheFile], "worker");
    exit(1);
}

LogStream::info("GeneratePublicStats worker completed", ["cache_file" => $cacheFile], "worker");
exit(0);

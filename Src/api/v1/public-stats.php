<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\PublicStats;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('Access-Control-Allow-Origin: *');

$cacheFile = PublicStats::CACHE_FILE;

if (
    file_exists($cacheFile) &&
    (time() - filemtime($cacheFile)) < PublicStats::CACHE_TTL
) {
    $cached = file_get_contents($cacheFile);
    if ($cached !== false) {
        echo $cached;
        exit;
    }
}

echo json_encode(PublicStats::generate(), JSON_UNESCAPED_UNICODE);

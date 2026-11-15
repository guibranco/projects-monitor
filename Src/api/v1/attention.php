<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionCollector;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionFeed;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionSources;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionStore;
use GuiBranco\ProjectsMonitor\Library\Database;
use GuiBranco\ProjectsMonitor\Library\LogStream;
use GuiBranco\ProjectsMonitor\Library\PublicStats;

$store = null;
try {
    $store = new AttentionStore((new Database())->getConnection());
} catch (Throwable $exception) {
    (new LogStream())->warning("Attention store unavailable: {reason}", ["reason" => $exception->getMessage()], "attention");
}

$collector = new AttentionCollector((new AttentionSources())->getEvaluators());
$feed      = new AttentionFeed($store, $collector, AttentionFeed::CACHE_FILE, PublicStats::CACHE_FILE);
$refresh   = isset($_GET["refresh"]) && $_GET["refresh"] === "1";

echo json_encode($feed->getResponse($refresh, time()));

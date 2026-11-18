#!/usr/bin/env php
<?php

/**
 * NotifyAttention — worker that pushes critical attention items to the
 * dashboard's OneSignal subscribers, once per raise. Warnings never push;
 * snoozed and acknowledged items never push. It reads the same attention
 * feed as the Overview (re-evaluating the sources when the cached snapshot
 * is older than cache.attention.ttlSeconds).
 *
 * Needs Src/secrets/oneSignal.secrets.php ($oneSignalAppId, $oneSignalApiKey,
 * optionally $oneSignalSegment and $oneSignalSiteUrl); without it the
 * worker logs that it is not configured and exits 0.
 *
 * Recommended cron entry (every 5 minutes):
 *
 *   *\/5 * * * * php /home/<username>/public_html/Src/Worker/NotifyAttention.php \
 *       >> /var/log/projects-monitor-notify-attention.log 2>&1
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once __DIR__.'/../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionCollector;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionFeed;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionNotifier;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionPushRun;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionSources;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionStore;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\Database;
use GuiBranco\ProjectsMonitor\Library\LogStream;
use GuiBranco\ProjectsMonitor\Library\OneSignal;
use GuiBranco\ProjectsMonitor\Library\PublicStats;

$logStream = new LogStream();
(new Configuration())->init();

$oneSignal = new OneSignal();
if ($oneSignal->isConfigured() === false) {
    $logStream->info("{worker} skipped: OneSignal is not configured", ["worker" => "NotifyAttention"], "worker");
    exit(0);
}

try {
    $store = new AttentionStore((new Database())->getConnection());
} catch (Throwable $exception) {
    // Without the store nothing remembers what was pushed, so every run would push again.
    $logStream->error("{worker} cannot open the attention store: {reason}", array_merge(["worker" => "NotifyAttention"], ["reason" => $exception->getMessage()]), "worker");
    exit(1);
}

$collector  = new AttentionCollector((new AttentionSources())->getEvaluators());
$feed       = new AttentionFeed($store, $collector, AttentionFeed::CACHE_FILE, PublicStats::CACHE_FILE);
$thresholds = new AttentionThresholds($store->getThresholdOverrides());
$notifier   = new AttentionNotifier((int) $thresholds->getValue("notify.maxPushesPerRun"));
$result     = (new AttentionPushRun($store, $oneSignal, $notifier))->run($feed->getResponse(false, time()));

foreach ($result["errors"] as $reason) {
    $logStream->error("{worker} push failed: {reason}", array_merge(["worker" => "NotifyAttention"], ["reason" => $reason]), "worker");
}

$context = [
            "worker"   => "NotifyAttention",
            "selected" => $result["selected"],
            "sent"     => $result["sent"],
            "failed"   => $result["failed"],
           ];
$logStream->info("{worker} completed: {selected} new critical items, {sent} pushes sent, {failed} failed", $context, "worker");
exit(min(1, $result["failed"]));

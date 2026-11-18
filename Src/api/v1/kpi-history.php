<?php
/*
 * GET  /api/v1/kpi-history?metrics=a,b: the KPI tiles' samples of the
 *      retention window, as {"<metric>": [{"t": <ms>, "v": <value>}, ...]}.
 * POST /api/v1/kpi-history: stores the values the dashboard shows now.
 *      Body: {"samples": {"<metric>": <number>, ...}}.
 */

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

header('Content-Type: application/json');

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionStore;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Database;
use GuiBranco\ProjectsMonitor\Library\KpiHistory;

$method = $_SERVER["REQUEST_METHOD"];
if ($method !== "GET" && $method !== "POST") {
    sendErrorResponse("Method not allowed", 405);
}

try {
    $connection = (new Database())->getConnection();
    $overrides  = [];
    try {
        $overrides = (new AttentionStore($connection))->getThresholdOverrides();
    } catch (Throwable $exception) {
        $overrides = [];
    }

    $retention = (new AttentionThresholds($overrides))->getValue("kpi.history.retentionHours");
    $history   = new KpiHistory($connection, $retention);
} catch (Throwable $exception) {
    sendErrorResponse("KPI history unavailable", 503);
}

if ($method === "GET") {
    echo json_encode((object) $history->get($history->parseMetrics((string) ($_GET["metrics"] ?? "")), time()));
    exit;
}

$body    = json_decode((string) file_get_contents("php://input"), true);
$samples = $history->filterSamples(($body["samples"] ?? null));
if (count($samples) === 0) {
    sendErrorResponse("No valid samples", 400);
}

echo json_encode(["recorded" => $history->record($samples, time())]);

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

try {
    $connection = (new Database())->getConnection();
    $thresholds = new AttentionThresholds((new AttentionStore($connection))->getThresholdOverrides());
    $history    = new KpiHistory($connection, $thresholds->getValue("kpi.history.retentionHours"));
} catch (Throwable $exception) {
    sendErrorResponse("KPI history unavailable", 503);
}

$answer = $history->handle($_SERVER["REQUEST_METHOD"], (string) ($_GET["metrics"] ?? ""), (string) file_get_contents("php://input"), time());
http_response_code($answer[0]);
echo json_encode($answer[1]);

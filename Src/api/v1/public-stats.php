<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\CPanel;
use GuiBranco\ProjectsMonitor\Library\Database;
use GuiBranco\ProjectsMonitor\Library\HealthChecksIo;
use GuiBranco\ProjectsMonitor\Library\UpTimeRobot;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('Access-Control-Allow-Origin: *');

$upStats     = null;
$hcStats     = null;
$cpanelUsage = null;
$dbConnected = false;

try {
    $upTimeRobot = new UpTimeRobot();
    $upStats     = $upTimeRobot->getStats();
} catch (Throwable) {
    // service unavailable – skip
}

try {
    $healthChecksIo = new HealthChecksIo();
    $hcStats        = $healthChecksIo->getStats();
} catch (Throwable) {
    // service unavailable – skip
}

try {
    $cPanel      = new CPanel();
    $cpanelUsage = $cPanel->getUsageData();
} catch (Throwable) {
    // service unavailable – skip
}

try {
    $db          = new Database();
    $conn        = $db->getConnection();
    $dbConnected = $conn !== null;
} catch (Throwable) {
    // service unavailable – skip
}

// ── Aggregate monitor counts across both services ────────────────────────────

$totalMonitors  = 0;
$totalUp        = 0;
$totalWarning   = 0;
$totalDown      = 0;

if ($upStats !== null) {
    $totalMonitors += $upStats['counts']['total'];
    $totalUp       += $upStats['counts']['up'];
    $totalWarning  += $upStats['counts']['warning'];
    $totalDown     += $upStats['counts']['down'];
}

if ($hcStats !== null) {
    $totalMonitors += $hcStats['counts']['total'];
    $totalUp       += $hcStats['counts']['up'];
    $totalWarning  += $hcStats['counts']['warning'];
    $totalDown     += $hcStats['counts']['down'];
}

// ── Recent activity: merge monitors + checks, keep last 5 by most recent change ──

$allActivity = array_merge(
    $upStats['monitors'] ?? [],
    $hcStats['checks']   ?? []
);

usort($allActivity, function ($a, $b) {
    // Items without a lastChange go to the bottom
    if ($a['lastChange'] === null && $b['lastChange'] === null) return 0;
    if ($a['lastChange'] === null) return 1;
    if ($b['lastChange'] === null) return -1;
    return strcmp($b['lastChange'], $a['lastChange']);
});

$recentActivity = array_slice($allActivity, 0, 5);

// ── System status derived from CPanel resource usage ────────────────────────

$cpuData       = null;
$memoryData    = null;
$processesData = null;

if ($cpanelUsage !== null) {
    foreach ($cpanelUsage as $item) {
        switch ($item['id']) {
            case 'lvecpu':
                $cpuData = $item;
                break;
            case 'lvememphy':
                $memoryData = $item;
                break;
            case 'lvenproc':
                $processesData = $item;
                break;
        }
    }
}

function resourceStatus(array|null $item): string
{
    if ($item === null || $item['maximum'] == 0) {
        return 'unknown';
    }
    $pct = ($item['usage'] / $item['maximum']) * 100;
    if ($pct >= 90) return 'critical';
    if ($pct >= 75) return 'warning';
    return 'operational';
}

function resourcePercent(array|null $item): float|null
{
    if ($item === null || $item['maximum'] == 0) return null;
    return round(($item['usage'] / $item['maximum']) * 100, 1);
}

$systemStatus = [
    'cpu'       => ['status' => resourceStatus($cpuData),       'percent' => resourcePercent($cpuData),       'label' => $cpuData['description']       ?? 'CPU'],
    'memory'    => ['status' => resourceStatus($memoryData),    'percent' => resourcePercent($memoryData),    'label' => $memoryData['description']    ?? 'Memory'],
    'processes' => ['status' => resourceStatus($processesData), 'percent' => resourcePercent($processesData), 'label' => $processesData['description'] ?? 'Processes'],
    'database'  => ['status' => $dbConnected ? 'operational' : 'critical', 'percent' => null, 'label' => 'Database'],
];

// ── Performance metrics from CPanel resource usage ───────────────────────────

$performance = [
    'cpu'       => ['value' => $cpuData       ? (float)$cpuData['usage']       : null, 'max' => $cpuData       ? (float)$cpuData['maximum']       : null, 'percent' => resourcePercent($cpuData),       'unit' => '%'],
    'memory'    => ['value' => $memoryData    ? (float)$memoryData['usage']    : null, 'max' => $memoryData    ? (float)$memoryData['maximum']    : null, 'percent' => resourcePercent($memoryData),    'unit' => 'MB'],
    'processes' => ['value' => $processesData ? (float)$processesData['usage'] : null, 'max' => $processesData ? (float)$processesData['maximum'] : null, 'percent' => resourcePercent($processesData), 'unit' => ''],
];

echo json_encode([
    'monitors'      => [
        'total'   => $totalMonitors,
        'healthy' => $totalUp,
        'warning' => $totalWarning,
        'critical'=> $totalDown,
    ],
    'recentActivity' => $recentActivity,
    'systemStatus'   => $systemStatus,
    'performance'    => $performance,
    'generatedAt'    => date('Y-m-d\TH:i:s\Z'),
], JSON_UNESCAPED_UNICODE);

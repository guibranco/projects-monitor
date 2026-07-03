<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

class PublicStats
{
    public const CACHE_FILE = __DIR__ . '/../cache/public-stats.json';
    public const CACHE_TTL  = 600; // seconds — 10 minutes

    public static function generate(): array
    {
        $upStats      = null;
        $hcStats      = null;
        $cpanelUsage  = null;
        $dbConnected  = false;
        $queueSummary = null;
        $webhookStats = null;

        try {
            $upStats = (new UpTimeRobot())->getStats();
        } catch (\Throwable) {
        }

        try {
            $hcStats = (new HealthChecksIo())->getStats();
        } catch (\Throwable) {
        }

        try {
            $cpanelUsage = (new CPanel())->getUsageData();
        } catch (\Throwable) {
        }

        try {
            $dbConnected = (new Database())->getConnection() !== null;
        } catch (\Throwable) {
        }

        try {
            $queueSummary = (new RabbitMq())->getQueueSummary();
        } catch (\Throwable) {
        }

        try {
            $webhookStats = (new Webhooks())->getStatistics();
        } catch (\Throwable) {
        }

        $webhookDashboard = null;

        try {
            $webhookDashboard = (new Webhooks())->getDashboard('all');
        } catch (\Throwable) {
        }

        $totalBranches    = $webhookDashboard !== null ? max(0, count($webhookDashboard['branches'] ?? []) - 1) : null;
        $totalPullRequests = $webhookDashboard !== null ? max(0, count($webhookDashboard['pull_requests'] ?? []) - 1) : null;

        $totalMonitors = 0;
        $totalUp       = 0;
        $totalWarning  = 0;
        $totalDown     = 0;

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

        $allActivity = array_merge($upStats['monitors'] ?? [], $hcStats['checks'] ?? []);
        usort($allActivity, static function ($a, $b) {
            if ($a['lastChange'] === null && $b['lastChange'] === null) return 0;
            if ($a['lastChange'] === null) return 1;
            if ($b['lastChange'] === null) return -1;
            return strcmp($b['lastChange'], $a['lastChange']);
        });

        $cpuData       = null;
        $memoryData    = null;
        $processesData = null;

        if ($cpanelUsage !== null) {
            foreach ($cpanelUsage as $item) {
                match ($item['id']) {
                    'lvecpu'    => $cpuData       = $item,
                    'lvememphy' => $memoryData    = $item,
                    'lvenproc'  => $processesData = $item,
                    default     => null,
                };
            }
        }

        return [
            'monitors' => [
                'total'    => $totalMonitors,
                'healthy'  => $totalUp,
                'warning'  => $totalWarning,
                'critical' => $totalDown,
            ],
            'recentActivity' => array_slice($allActivity, 0, 5),
            'systemStatus' => [
                'cpu'       => self::resourceEntry($cpuData,       $cpuData['description']       ?? 'CPU'),
                'memory'    => self::resourceEntry($memoryData,    $memoryData['description']    ?? 'Memory'),
                'processes' => self::resourceEntry($processesData, $processesData['description'] ?? 'Processes'),
                'database'  => ['status' => $dbConnected ? 'operational' : 'critical', 'percent' => null, 'label' => 'Database'],
            ],
            'performance' => [
                'cpu'       => self::performanceEntry($cpuData,       '%'),
                'memory'    => self::performanceEntry($memoryData,    'MB'),
                'processes' => self::performanceEntry($processesData, ''),
            ],
            'generatedAt'   => gmdate('Y-m-d\TH:i:s\Z'),
            'queues'        => $queueSummary,
            'webhookStats'  => $webhookStats,
            'webhookCounts' => [
                'branches'     => $totalBranches,
                'pullRequests' => $totalPullRequests,
            ],
        ];
    }

    private static function resourceStatus(?array $item): string
    {
        if ($item === null || $item['maximum'] == 0) return 'unknown';
        $pct = ($item['usage'] / $item['maximum']) * 100;
        if ($pct >= 90) return 'critical';
        if ($pct >= 75) return 'warning';
        return 'operational';
    }

    private static function resourcePercent(?array $item): ?float
    {
        if ($item === null || $item['maximum'] == 0) return null;
        return round(($item['usage'] / $item['maximum']) * 100, 1);
    }

    private static function resourceEntry(?array $item, string $label): array
    {
        return ['status' => self::resourceStatus($item), 'percent' => self::resourcePercent($item), 'label' => $label];
    }

    private static function performanceEntry(?array $item, string $unit): array
    {
        return [
            'value'   => $item ? (float)$item['usage']   : null,
            'max'     => $item ? (float)$item['maximum']  : null,
            'percent' => self::resourcePercent($item),
            'unit'    => $unit,
        ];
    }
}

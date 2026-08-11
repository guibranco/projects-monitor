<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\ShieldsIo;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

class SSH
{
    private $ssh;

    private $name;
    private $host;
    private $port = 22;
    private $username;
    private $privateKey;
    private $connected = false;

    /**
     * @param array{name?: string, host: string, port?: int, username: string, privateKey: string}|null $hostConfig
     * Connects to the given host, or to the default host (the entry named
     * "Vinhedo1" in ssh.secrets.php, falling back to the first entry) when omitted.
     */
    public function __construct(?array $hostConfig = null)
    {
        $config = new Configuration();
        $config->init();

        $hostConfig ??= self::getDefaultHostConfig();

        $this->name = $hostConfig['name'] ?? $hostConfig['host'];
        $this->host = $hostConfig['host'];
        $this->port = $hostConfig['port'] ?? 22;
        $this->username = $hostConfig['username'];
        $this->privateKey = $hostConfig['privateKey'];
        $this->connect();
    }

    /**
     * Reads the list of configured SSH hosts from ssh.secrets.php.
     *
     * @return array<int, array{name: string, host: string, port?: int, username: string, privateKey: string}>
     */
    public static function getHosts(): array
    {
        if (!file_exists(__DIR__ . "/../secrets/ssh.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: ssh.secrets.php");
        }

        require __DIR__ . "/../secrets/ssh.secrets.php";

        return $sshHosts ?? [];
    }

    private static function getDefaultHostConfig(): array
    {
        $hosts = self::getHosts();

        foreach ($hosts as $hostConfig) {
            if (($hostConfig['name'] ?? null) === 'Vinhedo1') {
                return $hostConfig;
            }
        }

        return $hosts[0] ?? [];
    }

    public function getName(): string
    {
        return $this->name;
    }

    private function connect(): void
    {
        try {
            $privateKey = PublicKeyLoader::loadPrivateKey($this->privateKey);
            $this->ssh = new SSH2($this->host, $this->port);
            $this->connected = $this->ssh->login($this->username, $privateKey);
        } catch (\Throwable $e) {
            $this->connected = false;
            LogStream::warning("SSH connection failed", ["host" => $this->name, "reason" => $e->getMessage()], "ssh");
        }
    }

    private function mapPeerToHostname($peerName)
    {
        $peers = [
            '10.141.230.2/32' => 'GH Actions - Projects Monitor',
            '10.141.230.3/32' => 'GH Actions - Webhooks',
        ];

        return $peers[$peerName] ?? $peerName;
    }

    /**
     * Runs the `monitor-report` script on the remote host (installed at
     * /usr/local/bin/monitor-report) and returns its decoded JSON payload.
     * This single script run is the source of both the system health report
     * and the WireGuard peer list — vinhedo1's sudoers no longer permits
     * running `wg show` directly, only this wrapped script.
     */
    private function fetchMonitorReport(): array
    {
        if (!$this->connected) {
            throw new \Exception('Not connected');
        }

        $response = $this->ssh->exec('/usr/local/bin/monitor-report');

        if (empty($response)) {
            throw new \Exception('Command execution failed or no output received');
        }

        $report = json_decode($response, true);

        if (!is_array($report)) {
            throw new \Exception('Unable to parse monitor-report output');
        }

        return $report;
    }

    /**
     * Returns WireGuard peer connection data, parsed from the
     * `wireguard_peers` array of the `monitor-report` script's output.
     */
    public function getWireGuardConnections(): array
    {
        try {
            $report = $this->fetchMonitorReport();
            return $this->formatWireGuardPeers((array) ($report['wireguard_peers'] ?? []));
        } catch (\Exception $e) {
            LogStream::warning("Failed to fetch WireGuard connections", ["host" => $this->name, "reason" => $e->getMessage()], "ssh");
        }

        return [];
    }

    private function formatWireGuardPeers(array $peers): array
    {
        $shields = new ShieldsIo();

        $rows = [];
        $rows[] = ["Peer", "Status", "Last Handshake", "Received", "Sent"];

        foreach ($peers as $peer) {
            $handshakeTimestamp = (int) ($peer['latest_handshake'] ?? 0);
            $hasHandshake = $handshakeTimestamp > 0;
            $diff = $hasHandshake ? time() - $handshakeTimestamp : PHP_INT_MAX;

            $label = "🔴";
            $content = "Disconnected";
            $color = "red";

            if ($hasHandshake && $diff > 600) {
                $label = "🟠";
                $content = "Inactive";
                $color = "orange";
            } elseif ($hasHandshake) {
                $label = "🟢";
                $content = "Active";
                $color = "brightgreen";
            }

            $status = $shields->generateBadgeUrl($label, $content, $color, "for-the-badge", "white", null);
            $statusImg = "<img src='$status' alt='Status' />";

            $rows[] = array(
                $this->mapPeerToHostname($peer['allowed_ips'] ?? ''),
                $statusImg,
                $hasHandshake ? date("Y-m-d H:i:s", $handshakeTimestamp) : 'Never',
                $this->formatBytes((int) ($peer['rx'] ?? 0)),
                $this->formatBytes((int) ($peer['tx'] ?? 0))
            );
        }

        return $rows;
    }

    /**
     * Runs the `monitor-report` script on the remote host and returns its
     * parsed system health data as a Metric/Value table: load average, CPU
     * count, memory, swap, disk usage, pending-reboot flag, and systemd
     * service states.
     */
    public function getSystemReport(): array
    {
        try {
            $report = $this->fetchMonitorReport();
            return $this->formatSystemReport($report);
        } catch (\Exception $e) {
            LogStream::warning("Failed to fetch system report", ["host" => $this->name, "reason" => $e->getMessage()], "ssh");
        }

        return [];
    }

    /**
     * Returns a compact CPU/memory/disk usage summary for this host, suitable
     * for the public dashboard. When the host is unreachable or doesn't have
     * the `monitor-report` script installed, returns a status of "unknown"
     * with no metrics rather than throwing.
     *
     * @return array{name: string, status: string, metrics: array<string, array{value: float|null, max: float|null, percent: float|null, unit: string}>}
     */
    public function getResourceUsage(): array
    {
        $emptyMetric = ['value' => null, 'max' => null, 'percent' => null, 'unit' => ''];

        try {
            $report = $this->fetchMonitorReport();
        } catch (\Exception $e) {
            LogStream::warning("Failed to fetch resource usage", ["host" => $this->name, "reason" => $e->getMessage()], "ssh");
            return [
                'name' => $this->name,
                'status' => 'unknown',
                'metrics' => [
                    'cpu'    => $emptyMetric,
                    'memory' => $emptyMetric,
                    'disk'   => $emptyMetric,
                ],
            ];
        }

        $cpuCount = (int) ($report['cpu_count'] ?? 0);
        $load1m = (float) ($report['load']['1m'] ?? 0);
        $cpuPercent = $cpuCount > 0 ? round(min(100, ($load1m / $cpuCount) * 100), 1) : null;

        $memTotal = (int) ($report['memory_kb']['total'] ?? 0);
        $memAvailable = (int) ($report['memory_kb']['available'] ?? 0);
        $memPercent = $memTotal > 0 ? round((($memTotal - $memAvailable) / $memTotal) * 100, 1) : null;

        $diskPercent = isset($report['disk_root_used_pct']) ? round((float) $report['disk_root_used_pct'], 1) : null;

        $metrics = [
            'cpu' => [
                'value' => $cpuCount > 0 ? $load1m : null,
                'max' => $cpuCount > 0 ? (float) $cpuCount : null,
                'percent' => $cpuPercent,
                'unit' => '',
            ],
            'memory' => [
                'value' => $memTotal > 0 ? round(($memTotal - $memAvailable) / 1024, 1) : null,
                'max' => $memTotal > 0 ? round($memTotal / 1024, 1) : null,
                'percent' => $memPercent,
                'unit' => 'MB',
            ],
            'disk' => [
                'value' => $diskPercent,
                'max' => $diskPercent !== null ? 100.0 : null,
                'percent' => $diskPercent,
                'unit' => '%',
            ],
        ];

        $percents = array_filter(array_column($metrics, 'percent'), static fn($p) => $p !== null);
        $status = 'operational';
        if ($percents !== []) {
            $maxPercent = max($percents);
            $status = $maxPercent >= 90 ? 'critical' : ($maxPercent >= 75 ? 'warning' : 'operational');
        }

        return [
            'name' => $this->name,
            'status' => $status,
            'metrics' => $metrics,
        ];
    }

    private function formatSystemReport(array $report): array
    {
        $shields = new ShieldsIo();
        $rows = [];
        $rows[] = ["Metric", "Value"];

        $rows[] = ["Host", $report['host'] ?? '-'];
        $rows[] = ["Last Checked", $report['timestamp'] ?? '-'];
        $rows[] = ["Uptime", $this->humanizeUptime((int) ($report['uptime_seconds'] ?? 0))];

        $load = $report['load'] ?? [];
        $cpuCount = (int) ($report['cpu_count'] ?? 0);
        $loadValue = sprintf(
            "%s / %s / %s (%d cores)",
            $load['1m'] ?? '-',
            $load['5m'] ?? '-',
            $load['15m'] ?? '-',
            $cpuCount
        );
        $rows[] = ["Load Average (1m/5m/15m)", $loadValue];

        $memTotal = (int) ($report['memory_kb']['total'] ?? 0);
        $memAvailable = (int) ($report['memory_kb']['available'] ?? 0);
        $memUsedPct = $memTotal > 0 ? (int) round((($memTotal - $memAvailable) / $memTotal) * 100) : 0;
        $memDetail = $this->formatKilobytes($memTotal - $memAvailable) . " / " . $this->formatKilobytes($memTotal);
        $rows[] = ["Memory", $this->usageBadge($shields, $memUsedPct, $memDetail)];

        $swapTotal = (int) ($report['swap_kb']['total'] ?? 0);
        if ($swapTotal > 0) {
            $swapFree = (int) ($report['swap_kb']['free'] ?? 0);
            $swapUsedPct = (int) round((($swapTotal - $swapFree) / $swapTotal) * 100);
            $swapDetail = $this->formatKilobytes($swapTotal - $swapFree) . " / " . $this->formatKilobytes($swapTotal);
            $rows[] = ["Swap", $this->usageBadge($shields, $swapUsedPct, $swapDetail)];
        } else {
            $rows[] = ["Swap", "None configured"];
        }

        $diskPct = (int) round((float) ($report['disk_root_used_pct'] ?? 0));
        $rows[] = ["Disk Usage (/)", $this->usageBadge($shields, $diskPct, "{$diskPct}%")];

        $rebootRequired = !empty($report['reboot_required']);
        $rows[] = ["Reboot Required", $this->statusBadge(
            $shields,
            $rebootRequired ? "⚠️" : "✅",
            $rebootRequired ? "Yes" : "No",
            $rebootRequired ? "red" : "brightgreen"
        )];

        foreach ((array) ($report['services'] ?? []) as $service => $state) {
            [$label, $color] = $this->serviceBadgeStyle($state);
            $rows[] = ["Service: {$service}", $this->statusBadge($shields, $label, $state, $color)];
        }

        return $rows;
    }

    private function humanizeUptime(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        if ($days > 0 || $hours > 0) {
            $parts[] = "{$hours}h";
        }
        $parts[] = "{$minutes}m";

        return implode(" ", $parts);
    }

    private function formatKilobytes(int $kilobytes): string
    {
        return $this->formatBytes($kilobytes * 1024);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = max(0, $bytes);
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return number_format($value, 1) . ' ' . $units[$i];
    }

    private function usageBadge(ShieldsIo $shields, int $percent, string $content): string
    {
        $color = "brightgreen";
        if ($percent >= 90) {
            $color = "red";
        } elseif ($percent >= 70) {
            $color = "orange";
        }

        return $this->statusBadge($shields, "{$percent}%", $content, $color);
    }

    private function statusBadge(ShieldsIo $shields, string $label, string $content, string $color): string
    {
        $url = $shields->generateBadgeUrl($label, $content, $color, "for-the-badge", "white", null);
        return "<img src='{$url}' alt='{$label}' />";
    }

    private function serviceBadgeStyle(string $state): array
    {
        return match ($state) {
            'active' => ['🟢', 'brightgreen'],
            'inactive', 'failed' => ['🔴', 'red'],
            default => ['⚪', 'lightgrey'],
        };
    }
}

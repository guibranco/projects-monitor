<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\ShieldsIo;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

class SSH
{
    private $ssh;

    private $host;
    private $port = 22;
    private $username;
    private $privateKey;

    public function __construct()
    {
        global $sshHost, $sshUsername, $sshPrivateKey;

        $config = new Configuration();
        $config->init();

        if (!file_exists(__DIR__ . "/../secrets/ssh.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: ssh.secrets.php");
        }

        require_once __DIR__ . "/../secrets/ssh.secrets.php";

        $this->host = $sshHost;
        $this->username = $sshUsername;
        $this->privateKey = $sshPrivateKey;
        $this->connect();
    }

    private function connect(): void
    {
        try {
            $privateKey = PublicKeyLoader::loadPrivateKey($this->privateKey);
            $this->ssh = new SSH2($this->host, $this->port);

            if (!$this->ssh->login($this->username, $privateKey)) {
                throw new \Exception('Login failed');
            }
        } catch (\Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    private function listWireGuardConnections(): array
    {
        try {
            $command = 'sudo wg show';
            $response = $this->ssh->exec($command);

            if (empty($response)) {
                throw new \Exception('Command execution failed or no output received');
            }

            $groups = explode("\n\n", $response);

            $parsedResponse = [];
            foreach ($groups as $group) {
                $parsedGroup = [];
                $lines = explode("\n", $group);

                foreach ($lines as $line) {
                    $line = trim($line);

                    if (empty($line)) {
                        continue;
                    }

                    if (strpos($line, 'interface:') === 0) {
                        $parsedGroup['interface'] = trim(substr($line, strlen('interface:')));
                        continue;
                    }

                    if (strpos($line, 'peer:') === 0) {
                        $parsedGroup['peer'] = trim(substr($line, strlen('peer:')));
                        continue;
                    }

                    if (strpos($line, ':') !== false) {
                        list($key, $value) = array_map('trim', explode(':', $line, 2));

                        if ($key == 'transfer') {
                            preg_match('/([0-9.]+ \w+) received, ([0-9.]+ \w+) sent/', $value, $matches);
                            $parsedGroup['transfer'] = [
                                'received' => $matches[1] ?? '',
                                'sent' => $matches[2] ?? ''
                            ];
                        } else {
                            $parsedGroup[$key] = $value;
                        }
                    }
                }

                $parsedResponse[] = $parsedGroup;
            }

            return $parsedResponse;

        } catch (\Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }

        return [];
    }

    private function mapPeerToHostname($peerName)
    {
        $peers = [
            '10.141.230.2/32' => 'GH Actions - Projects Monitor',
            '10.207.124.2/32' => 'Laptop Work',
            '10.207.124.3/32' => 'Laptop Personal',
            '10.207.124.4/32' => 'GH Actions - Webhooks',
            '10.207.124.5/32' => 'GH Actions - GStraccini Bot',
            '10.207.124.6/32' => 'GH Actions - Currencies',
            '10.207.124.7/32' => 'GH Actions - POC',
            '10.207.124.8/32' => 'Samsung Galaxy S25 Ultra',
            '10.207.124.9/32' => 'GH Actions - Projects Monitor',
            '10.207.124.10/32' => 'GH Actions - Sports Agenda',
            '10.207.124.11/32' => 'GH Actions - Vagas Aggregator',
            '10.207.124.12/32' => 'GH Actions - Bancos Brasileiros',
            '10.207.124.13/32' => 'GH Actions - Sports Agenda Worker',
            '10.207.124.14/32' => 'iPhone Beatriz Jesus',
        ];

        return $peers[$peerName] ?? $peerName;
    }

    public function getWireGuardConnections(): array
    {
        $data = $this->listWireGuardConnections();
        $shields = new ShieldsIo();

        $peers = array();
        $peers[] = array("Peer", "Status", "Last Handshake", "Received", "Sent");
        foreach ($data as $peer) {
            if (array_key_exists('peer', $peer) === false) {
                continue;
            }

            $handshake = array_key_exists('latest handshake', $peer) ? true : false;
            $time = $handshake ? strtotime($peer["latest handshake"]) : 0;
            $diff = time() - $time;

            $label = "🔴";
            $content = "Disconnected";
            $color = "red";

            if ($handshake === true && $diff > 600) {
                $label = "🟠";
                $content = "Inactive";
                $color = "orange";
            } elseif ($handshake === true) {
                $label =  "🟢";
                $content = "Active";
                $color = "brightgreen";
            }

            $status = $shields->generateBadgeUrl($label, $content, $color, "for-the-badge", "white", null);
            $statusImg = "<img src='$status' alt='Status' />";

            $peers[] = array(
                $this->mapPeerToHostname($peer['allowed ips']),
                $statusImg,
                $peer['latest handshake'] ?? '',
                $peer['transfer']['received'] ?? '0',
                $peer['transfer']['sent'] ?? '0'
            );
        }

        return $peers;
    }

    /**
     * Runs the `monitor-report` script on the remote host (installed at
     * /usr/local/bin/monitor-report) and returns its parsed system health
     * data as a Metric/Value table: load average, CPU count, memory, swap,
     * disk usage, pending-reboot flag, and systemd service states.
     */
    public function getSystemReport(): array
    {
        try {
            $response = $this->ssh->exec('/usr/local/bin/monitor-report');

            if (empty($response)) {
                throw new \Exception('Command execution failed or no output received');
            }

            $report = json_decode($response, true);

            if (!is_array($report)) {
                throw new \Exception('Unable to parse monitor-report output');
            }

            return $this->formatSystemReport($report);
        } catch (\Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }

        return [];
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
        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = max(0, $kilobytes);
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

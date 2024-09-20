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
            '10.207.124.2/32' => 'Laptop Work',
            '10.207.124.3/32' => 'Laptop Personal',
            '10.207.124.4/32' => 'GH Actions - Webhooks',
            '10.207.124.5/32' => 'GH Actions - GStraccini Bot',
            '10.207.124.6/32' => 'GH Actions - Currencies',
            '10.207.124.7/32' => 'GH Actions - POC',
            '10.207.124.8/32' => 'Smartphone',
            '10.207.124.9/32' => 'GH Actions - Projects Monitor',
            '10.207.124.10/32' => 'GH Actions - Sports Agenda',
            '10.207.124.11/32' => 'GH Actions - Vagas Aggregator',
            '10.207.124.12/32' => 'GH Actions - Bancos Brasileiros',
            '10.207.124.13/32' => 'GH Actions - Sports Agenda Worker',
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

            $connected = array_key_exists('latest handshake', $peer) ? true : false;
            $label = $connected ? "🟢" : "🔴";
            $content = $connected ? "Connected" : "Disconnected";
            $color = $connected ? "brightgreen" : "red";

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
}

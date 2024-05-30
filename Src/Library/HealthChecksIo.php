<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;

class HealthChecksIo
{
    private const HEALTHCHECKS_API_URL = "https://healthchecks.io/api/v3/";

    private $readKeys = array();

    private $request;

    public function __construct()
    {
        global $healthChecksIoReadKeys;

        if (!file_exists(__DIR__ . "/../secrets/healthChecksIo.secrets.php")) {
            throw new HealthChecksIoException("File not found: healthChecksIo.secrets.php");
        }

        require_once __DIR__ . "/../secrets/healthChecksIo.secrets.php";

        $this->readKeys = $healthChecksIoReadKeys;
        $this->request = new Request();
    }

    private function getRequest($readKey)
    {
        $url = self::HEALTHCHECKS_API_URL . "checks/";
        $headers = [
            "X-Api-Key: {$readKey}",
            "Accept: application/json",
            "User-Agent: ProjectsMonitor/1.0"
        ];

        $response = $this->request->get($url, $headers);

        if ($response->statusCode != 200) {
            throw new HealthChecksIoException("Error: {$response->body}");
        }

        return json_decode($response->body);
    }

    private function mapStatus($status)
    {
        return match ($status) {
            "up" => "✅",
            "down" => "❌",
            "paused" => "⏸",
            "new" => "🆕",
            "grace" => "⏳",
            default => "❓",
        };
    }

    public function getChecks()
    {
        $checks = array();

        foreach ($this->readKeys as $readKey) {
            $response = $this->getRequest($readKey);

            foreach ($response->checks as $check) {

                $checks[] = array(
                    $check->name,
                    $this->mapStatus($check->status),
                    date("H:i:s d/m/Y", $check->last_ping == null ? time() : strtotime($check->last_ping)),
                    date("H:i:s d/m/Y", $check->next_ping == null ? time() : strtotime($check->next_ping))
                );
            }
        }

        sort($checks, SORT_ASC);

        array_unshift($checks, array("Name", "Status", "Last Ping", "Next Ping"));

        return $checks;
    }
}

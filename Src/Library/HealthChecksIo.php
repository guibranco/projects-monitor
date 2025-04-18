<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\Configuration;

class HealthChecksIo
{
    private const HEALTH_CHECKS_API_URL = "https://healthchecks.io/api/v3/";

    private $writeKeys = array();

    private $request;

    public function __construct()
    {
        $config = new Configuration();
        $config->init();

        global $healthChecksIoWriteKeys;

        if (!file_exists(__DIR__ . "/../secrets/healthChecksIo.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: healthChecksIo.secrets.php");
        }

        require_once __DIR__ . "/../secrets/healthChecksIo.secrets.php";

        $this->writeKeys = $healthChecksIoWriteKeys;
        $this->request = new Request();
    }

    private function getRequest($writeKey)
    {
        $url = self::HEALTH_CHECKS_API_URL . "checks/";
        $headers = [
            "X-Api-Key: {$writeKey}",
            "Accept: application/json",
            constant("USER_AGENT")
        ];

        $response = $this->request->get($url, $headers);

        if ($response->getStatusCode() != 200) {
            $error = $response->getStatusCode() == -1 ? $response->getMessage() : $response->getBody();
            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
        }

        return json_decode($response->getBody());
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

    private function mapColor($status)
    {
        return match ($status) {
            "up" => "green",
            "down" => "red",
            "paused" => "gray",
            "new" => "blue",
            "grace" => "yellow",
            default => "orange",
        };
    }

    public function getChecks()
    {
        $checks = array();

        foreach ($this->writeKeys as $writeKey) {
            $response = $this->getRequest($writeKey);

            foreach ($response->checks as $check) {
                $link = "https://healthchecks.io/checks/{$check->uuid}/details/";

                $badge = "<img alt='" . $check->name . "' src='https://img.shields.io/badge/" . $this->mapStatus($check->status);
                $badge .= "-" . str_replace("-", "--", $check->name) . "-" . $this->mapColor($check->status);
                $badge .= "?style=for-the-badge&labelColor=white' />";

                $badgeLink = "<a href='{$link}' title='{$check->status}' target='_blank' rel='noopener noreferrer'>{$badge}</a>";

                $checks[] = array(
                             $badgeLink,
                             date("H:i:s d/m/Y", $check->last_ping == null ? time() : strtotime($check->last_ping)),
                             date("H:i:s d/m/Y", $check->next_ping == null ? time() : strtotime($check->next_ping))
                );
            }
        }

        sort($checks, SORT_ASC);
        array_unshift($checks, array("Check", "Last Ping", "Next Ping"));
        return $checks;
    }
}

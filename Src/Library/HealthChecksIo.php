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
            throw new SecretsFileNotFoundException("File not found: healthChecksIo.secrets.php");
        }

        require_once __DIR__ . "/../secrets/healthChecksIo.secrets.php";

        $this->readKeys = $healthChecksIoReadKeys;
        $this->request = new Request();

        $timezone = $this->getTimezone();
        ini_set("default_charset", "UTF-8");
        ini_set("date.timezone", $timezone["timezone"]);
        mb_internal_encoding("UTF-8");
    }
    
    private function getTimezone()
    {
        $timezone = "Europe/Dublin";

        if (isset($_COOKIE["timezone"])) {
            $timezone = strtolower($_COOKIE["timezone"]) === "europe/london"
                ? $timezone
                : $_COOKIE["timezone"];
        }

        if (isset($_COOKIE["offset"])) {
            $offset = $_COOKIE["offset"];
        } else {
            $datetimezone = new \DateTimeZone($timezone);
            $dateTime = new \DateTime("now", $datetimezone);
            $offset = $dateTime->getOffset() === 3600 ? "+01:00" : "+00:00";
        }

        return array("timezone" => $timezone, "offset" => $offset);
    }

    private function getRequest($readKey)
    {
        $url = self::HEALTHCHECKS_API_URL . "checks/";
        $headers = [
            "X-Api-Key: {$readKey}",
            "Accept: application/json",
            "User-Agent: ProjectsMonitor/1.0 (+https://github.com/guibranco/projects-monitor)"
        ];

        $response = $this->request->get($url, $headers);

        if ($response->statusCode != 200) {
            $error = $response->statusCode == -1 ? $response->error : $response->body;
            throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
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

        foreach ($this->readKeys as $readKey) {
            $response = $this->getRequest($readKey);

            foreach ($response->checks as $check) {

                $img =
                    "<img alt='" . $check->name . "' src='https://img.shields.io/badge/" . $this->mapStatus($check->status) .
                    "-" . str_replace("-", "--", $check->name) . "-" . $this->mapColor($check->status) .
                    "?style=for-the-badge&labelColor=white' />";

                $checks[] = array(
                    $img,
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

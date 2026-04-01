<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;

class UpTimeRobot
{
    private const API_URL = "https://api.uptimerobot.com/v2/";

    private $token;

    private $request;

    public function __construct()
    {
        $config = new Configuration();
        $config->init();

        global $upTimeRobotToken;

        if (!file_exists(__DIR__ . "/../secrets/upTimeRobot.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: upTimeRobot.secrets.php");
        }

        require_once __DIR__ . "/../secrets/upTimeRobot.secrets.php";

        $this->token = $upTimeRobotToken;
        $this->request = new Request();
    }

    private function doRequest()
    {
        $url = self::API_URL . "getMonitors";
        $headers = [
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: application/json",
            "Cache-Control: no-cache",
            constant("USER_AGENT")
        ];

        $data = http_build_query([
            "api_key" => $this->token,
            "format" => "json",
            "logs" => 1
        ]);

        $response = $this->request->post($url, $headers, $data);

        if ($response->getStatusCode() != 200) {
            $error = $response->getStatusCode() == -1 ? $response->getMessage() : $response->getBody();
            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
        }

        return json_decode($response->getBody());
    }

    private function mapStatus($status)
    {
        return match ($status) {
            0 => "⏸",
            1 => "🆕",
            2 => "✅",
            8 => "⚠️",
            9 => "❌",
            default => "❓"
        };
    }

    private function mapColor($status)
    {
        return match ($status) {
            0 => "gray",
            1 => "blue",
            2 => "green",
            8 => "yellow",
            9 => "red",
            default => "lightgray"
        };
    }

    public function getMonitors()
    {
        $monitors = array();
        $response = $this->doRequest();
    
        foreach ($response->monitors as $monitor) {
    
            $img =
                "<img alt='" . $monitor->friendly_name . "' src='https://img.shields.io/badge/" . $this->mapStatus($monitor->status) .
                "-" . str_replace("-", "--", $monitor->friendly_name) . "-" . $this->mapColor($monitor->status) .
                "?style=for-the-badge&labelColor=white' />";
    
            $lastChange = "-";
            $details = "No logs available";
    
            if (!empty($monitor->logs) && isset($monitor->logs[0])) {
    
                $log = $monitor->logs[0];
    
                $lastChange = isset($log->datetime)
                    ? date("H:i:s d/m/Y", $log->datetime)
                    : "-";
    
                if (isset($log->reason)) {
    
                    $code = $log->reason->code ?? "-";
                    $detail = $log->reason->detail ?? "-";
    
                    $details = "{$code} - {$detail}";
                }
            }
    
            $monitors[] = array(
                $img,
                $lastChange,
                $details
            );
        }
    
        sort($monitors, SORT_ASC);
    
        array_unshift($monitors, array("Monitor", "Last change", "Details"));
    
        return $monitors;
    }
}

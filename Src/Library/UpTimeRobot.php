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
            "User-Agent: ProjectsMonitor/1.0 (+https://github.com/guibranco/projects-monitor)"
        ];

        $data = http_build_query([
            "api_key" => $this->token,
            "format" => "json",
            "logs" => 1
        ]);

        $response = $this->request->post($url, $data, $headers);

        if ($response->statusCode != 200) {
            $error = $response->statusCode == -1 ? $response->error : $response->body;
            throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
        }

        return json_decode($response->body);
    }

    private function mapStatus($status)
    {
        return match ($status) {
            0 => "⏸",
            1 => "🆕",
            2 => "✅",
            8 => "⚠️",
            9 => "❌"
        };
    }

    private function mapColor($status)
    {
        return match ($status) {
            0 => "gray",
            1 => "blue",
            2 => "green",
            8 => "⚠yellow",
            9 => "red"
        };
    }

    public function getMonitors()
    {
        $monitors = array();
        $response = $this->doRequest();

        foreach ($response->monitors as $monitor) {
            $log = $monitor->logs[0];
            $img =
                "<img alt='" . $monitor->friendly_name . "' src='https://img.shields.io/badge/" . $this->mapStatus($monitor->status) .
                "-" . str_replace("-", "--", $monitor->friendly_name) . "-" . $this->mapColor($monitor->status) .
                "?style=for-the-badge&labelColor=white' />";
            $monitors[] = array(
                $img,
                date("H:i:s d/m/Y", $log->datetime),
                $log->reason->code . " - " . $log->reason->detail
            );
        }

        sort($monitors, SORT_ASC);

        array_unshift($monitors, array("Monitor", "Last change", "Details"));

        return $monitors;
    }
}

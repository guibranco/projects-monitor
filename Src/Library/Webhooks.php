<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;

class Webhooks
{
    private const API_URL = "https://guilhermebranco.com.br/webhooks/api.php";

    private $token;

    private $request;

    private $headers;

    public function __construct()
    {
        global $webhooksApiToken;

        if (!file_exists(__DIR__ . "/../secrets/webhooks.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: webhooks.secrets.php");
        }

        require_once __DIR__ . "/../secrets/webhooks.secrets.php";

        $timezone = $this->getTimezone();
        $this->headers = [
            "Authorization: token {$this->token}",
            "Accept: application/json",
            "Cache-Control: no-cache",
            "User-Agent: ProjectsMonitor/1.0 (+https://github.com/guibranco/projects-monitor)",
            "X-timezone: {$timezone["timezone"]}",
            "X-timezone-offset: {$timezone["offset"]}"
        ];
        $this->request = new Request();
        $this->token = $webhooksApiToken;
    }

    private function getTimezone()
    {
        $timezone = "Europe/Dublin";
        $offset = "+00:00";

        if (isset($_COOKIE["timezone"])) {
            $timezone = strtolower($_COOKIE["timezone"]) === "europe/london"
                ? "Europe/Dublin"
                : $_COOKIE["timezone"];
        }

        if (isset($_COOKIE["offset"])) {
            $offset = $_COOKIE["offset"];
        } else {
            $datetimezone = new \DateTimeZone($timezone);
            $dateTime = new \DateTime("now", $timezone);
            $offset = $dateTime->getOffset() === 3600 ? "+01:00" : "+00:00";
        }

        return array("timezone" => $timezone, "offset" => $offset);
    }


    public function getDashboard()
    {
        $response = $this->request->get(self::API_URL, $this->headers);
        if ($response->statusCode != 200) {
            $error = $response->statusCode == -1 ? $response->error : $response->body;
            throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
        }

        return json_decode($response->body);
    }
}

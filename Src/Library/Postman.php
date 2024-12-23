<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\ShieldsIo;

class Postman
{
    private const API_URL = "https://api.getpostman.com/";

    private $token;

    private $request;

    public function __construct()
    {
        $config = new Configuration();
        $config->init();

        global $postmanToken;

        if (file_exists(__DIR__ . "/../secrets/postman.secrets.php") === false) {
            throw new SecretsFileNotFoundException("File not found: postman.secrets.php");
        }

        require_once __DIR__ . "/../secrets/postman.secrets.php";

        $this->token = $postmanToken;
        $this->request = new Request();
    }

    private function doRequest($endpoint)
    {
        $url = self::API_URL . $endpoint;
        $headers = [
            "X-API-Key: {$this->token}",
            "Accept: application/json",
            constant("USER_AGENT")
        ];

        $response = $this->request->get($url, $headers);

        if ($response->getStatusCode() !== 200) {
            $error = $response->getStatusCode() == -1 ? $response->getMessage() : $response->getBody();
            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
        }

        return json_decode($response->getBody());
    }

    private function getImageUsage($apiUsage, $label)
    {
        $usage = $apiUsage->usage;
        $limit = $apiUsage->limit;
        $percentage = ($usage * 100) / $limit;

        $color = "green";
        if ($percentage >= 90) {
            $color = "red";
        } elseif ($percentage >= 75) {
            $color = "orange";
        } elseif ($percentage >= 50) {
            $color = "yellow";
        }

        $percentageStr = number_format($percentage, 2, '.', '')."%";
        if ($percentage < 10) {
            $percentageStr = "0" . $percentageStr;
        }

        $usage = str_pad($usage, strlen($limit), "0", STR_PAD_LEFT);
        $shields = new ShieldsIo();
        $badge = $shields->generateBadgeUrl($percentageStr, "{$usage}/{$limit} {$label}", $color, "for-the-badge", "black", null);
        return "<a href='https://web.postman.co/billing/add-ons/overview' target='_blank' rel='noopener noreferrer'><img src='{$badge}' alt='{$label}' /></a>";
    }

    public function getUsage()
    {
        $shields = new ShieldsIo();
        $me = $this->doRequest("me");

        $badge = $shields->generateBadgeUrl("⭕", "Error", "red", "for-the-badge", "white", null);
        $link = "<a href='https://web.postman.co/billing/add-ons/overview'>{$badge}</a>";

        if (isset($me->operations) === false || is_array($me->operations) === false || count($me->operations) === 0) {
            return $link;
        }

        $apiUsage = "";
        $monitorUsage = "";

        foreach ($me->operations as $operation) {
            switch ($operation->name) {
                case "api_usage":
                    $apiUsage = $this->getImageUsage($operation, "Postman API Usage");
                    break;
                case "monitor_request_runs":
                    $monitorUsage = $this->getImageUsage($operation, "Monitor Request Runs");
                    break;
            }
        }

        if ($apiUsage === "" && $monitorUsage === "") {
            return $link;
        }

        return $apiUsage . "\n<br />" . $monitorUsage;
    }

}

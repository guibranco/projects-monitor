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

        if ($response->statusCode !== 200) {
            $error = $response->statusCode == -1 ? $response->error : $response->body;
            throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
        }

        return json_decode($response->body);
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

        $apiUsage = null;

        foreach ($me->operations as $operation) {
            if($operation->name === "api_usage") {
                $apiUsage = $operation;
                break;
            }
        }

        if ($apiUsage === null) {
            return $link;
        }

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

        $badge = $shields->generateBadgeUrl(number_format($percentage, 2, '.', '') . "%", "{$usage}/{$limit}", $color, "for-the-badge", "black", null);
        return "<a href='https://web.postman.co/billing/add-ons/overview'><img src='{$badge}' alt='Postman API usage' /></a>";
    }

}
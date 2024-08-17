<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\Configuration;

class Webhooks
{
    private $apiUrl;

    private $headers;

    private $request;

    public function __construct()
    {
        global $webhooksApiToken, $webhooksApiUrl;

        $configuration = new Configuration();

        if (!file_exists(__DIR__ . "/../secrets/webhooks.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: webhooks.secrets.php");
        }

        require_once __DIR__ . "/../secrets/webhooks.secrets.php";

        $this->apiUrl = $webhooksApiUrl;
        $this->headers = [
            "Authorization: token {$webhooksApiToken}",
            "Accept: application/json",
            "Cache-Control: no-cache",
            constant("USER_AGENT"),
            "X-timezone: {$configuration->getTimeZone()->getTimeZone()}",
            "X-timezone-offset: {$configuration->getTimeZone()->getOffset()}"
        ];
        $this->request = new Request();
    }

    public function getDashboard()
    {
        $response = $this->request->get($this->apiUrl, $this->headers);
        if ($response->statusCode === 200) {
            return json_decode($response->body);
        }

        $error = $response->statusCode == -1 ? $response->error : $response->body;
        throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
    }

    public function getWebhook($sequence)
    {
        $response = $this->request->get($this->apiUrl . $sequence, $this->headers);
        if ($response->statusCode === 200) {
            return json_decode($response->body);
        }

        $error = $response->statusCode == -1 ? $response->error : $response->body;
        throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
    }
}

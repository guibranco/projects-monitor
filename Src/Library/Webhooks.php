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

        $config = new Configuration();
        $config->init();

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
            "X-timezone: {$config->getTimeZone()->getTimeZone()}",
            "X-timezone-offset: {$config->getTimeZone()->getOffset()}"
        ];
        $this->request = new Request();
    }

    private function doRequest($endpoint, $method, $expectedStatusCode, $data = null)
    {
        $response = null;
        $method = strtolower($method);
        switch ($method) {
            case "get":
                $response = $this->request->get("{$this->apiUrl}{$endpoint}", $this->headers);
                break;
            case "post":
                $response = $this->request->post("{$this->apiUrl}{$endpoint}", json_encode($data), $this->headers);
                break;
            case "delete":
                $response = $this->request->delete("{$this->apiUrl}{$endpoint}", $this->headers);
                break;
            default:
                throw new RequestException("Method not mapped: {$method}");
                break;
        }

        if ($response->statusCode === $expectedStatusCode) {
            return json_decode($response->body);
        }

        $error = $response->statusCode == -1 ? $response->error : $response->body;
        throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
    }

    public function getDashboard()
    {
        return $this->doRequest("github", "get", 200);
    }

    public function getWebhook($sequence)
    {
        return $this->doRequest("github/{$sequence}", "get", 200);
    }

    public function requestRerun($sequence)
    {
        return $this->doRequest("github/workflow", "post", 201, array("sequence", $sequence));
    }

    public function requestDelete($sequence)
    {
        return $this->doRequest("github/workflow/{$sequence}", "delete", 202);
    }
}

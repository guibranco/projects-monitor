<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\LogStream;

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
        LogStream::debug("Webhooks API request", ["method" => strtoupper($method), "endpoint" => $endpoint], "webhooks");
        switch ($method) {
            case "get":
                $response = $this->request->get("{$this->apiUrl}{$endpoint}", $this->headers);
                break;
            case "post":
                $response = $this->request->post("{$this->apiUrl}{$endpoint}", json_encode($data), $this->headers);
                break;
            case "put":
                $response = $this->request->put("{$this->apiUrl}{$endpoint}", json_encode($data), $this->headers);
                break;
            case "delete":
                $response = $this->request->delete("{$this->apiUrl}{$endpoint}", $this->headers);
                break;
            default:
                throw new RequestException("Method not mapped: {$method}");
        }

        if ($response->getStatusCode() === $expectedStatusCode) {
            return json_decode($response->getBody(), true);
        }

        $error = $response->getStatusCode() == -1 ? $response->getMessage() : $response->getBody();
        LogStream::error("Webhooks API request failed", [
            "method" => strtoupper($method),
            "endpoint" => $endpoint,
            "status_code" => $response->getStatusCode(),
            "error" => $error,
        ], "webhooks");
        throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
    }

    public function getDashboard($feedOptionsFilter, $workflowsLimiterQuantity)
    {
        $allowedFilters = ['all', 'mine'];
        if (!in_array($feedOptionsFilter, $allowedFilters)) {
            throw new \InvalidArgumentException('Invalid filter value provided');
        }
        $endpoint = sprintf("github?feedOptionsFilter=%s", urlencode($feedOptionsFilter));
        LogStream::info("Fetching webhooks dashboard", ["filter" => $feedOptionsFilter, "limit" => $workflowsLimiterQuantity], "webhooks");
        $response = $this->doRequest($endpoint, "get", 200);
        if ($workflowsLimiterQuantity <= 0) {
            LogStream::debug("Webhooks dashboard fetched", ["total_runs" => count($response["workflow_runs"] ?? [])], "webhooks");
            return $response;
        }

        $workflowsLimiterQuantity++; // The table header is the first row.
        $min = min($workflowsLimiterQuantity, count($response["workflow_runs"]));
        $response["workflow_runs"] = array_slice($response["workflow_runs"], 0, $min);
        LogStream::debug("Webhooks dashboard fetched (limited)", ["returned_runs" => $min], "webhooks");

        return $response;
    }

    public function getWebhook($sequence): mixed
    {
        LogStream::info("Fetching single webhook", ["sequence" => $sequence], "webhooks");
        return $this->doRequest("github/{$sequence}", "get", 200);
    }

    public function requestRerun($sequence): mixed
    {
        LogStream::info("Requesting workflow rerun", ["sequence" => $sequence], "webhooks");
        return $this->doRequest("github/workflow", "post", 201, ["sequence" => $sequence]);
    }

    public function requestUpdate($sequence): mixed
    {
        LogStream::info("Requesting workflow update", ["sequence" => $sequence], "webhooks");
        return $this->doRequest("github/workflow", "put", 202, ["sequence" => $sequence]);
    }

    public function requestDelete($sequence): mixed
    {
        LogStream::info("Requesting workflow delete", ["sequence" => $sequence], "webhooks");
        return $this->doRequest("github/workflow/{$sequence}", "delete", 202);
    }

    public function getStatistics(): mixed
    {
        LogStream::debug("Fetching webhooks statistics", null, "webhooks");
        return $this->doRequest("processing-state", "get", 200);
    }
}

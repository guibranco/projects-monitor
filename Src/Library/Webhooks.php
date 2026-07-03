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

    public function getDashboard($feedOptionsFilter)
    {
        $allowedFilters = ['all', 'mine'];
        if (!in_array($feedOptionsFilter, $allowedFilters)) {
            throw new \InvalidArgumentException('Invalid filter value provided');
        }
        $endpoint = sprintf("github?feedOptionsFilter=%s", urlencode($feedOptionsFilter));
        LogStream::info("Fetching webhooks dashboard", ["filter" => $feedOptionsFilter], "webhooks");
        $response = $this->doRequest($endpoint, "get", 200);
        LogStream::debug("Webhooks dashboard fetched", ["total_runs" => count($response["workflow_runs"] ?? [])], "webhooks");

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

    public function getPullRequestsProcessing(): mixed
    {
        LogStream::debug("Fetching pull requests pending processing", null, "webhooks");
        return $this->doRequest("pull-requests/processing", "get", 200);
    }

    public function getBranchesProcessing(): mixed
    {
        LogStream::debug("Fetching branches pending processing", null, "webhooks");
        return $this->doRequest("branches/processing", "get", 200);
    }

    public function getCommentsProcessing(): mixed
    {
        LogStream::debug("Fetching comments pending processing", null, "webhooks");
        return $this->doRequest("comments/processing", "get", 200);
    }

    public function getInstallationsProcessing(): mixed
    {
        LogStream::debug("Fetching installations pending processing", null, "webhooks");
        return $this->doRequest("installations/processing", "get", 200);
    }

    public function getIssuesProcessing(): mixed
    {
        LogStream::debug("Fetching issues pending processing", null, "webhooks");
        return $this->doRequest("issues/processing", "get", 200);
    }

    public function getPushesProcessing(): mixed
    {
        LogStream::debug("Fetching pushes pending processing", null, "webhooks");
        return $this->doRequest("pushes/processing", "get", 200);
    }

    public function getRepositoriesProcessing(): mixed
    {
        LogStream::debug("Fetching repositories pending processing", null, "webhooks");
        return $this->doRequest("repositories/processing", "get", 200);
    }

    public function getSignatureProcessing(): mixed
    {
        LogStream::debug("Fetching signature pending processing", null, "webhooks");
        return $this->doRequest("signature/processing", "get", 200);
    }

    public function getUsersProcessing(): mixed
    {
        LogStream::debug("Fetching users pending processing", null, "webhooks");
        return $this->doRequest("users/processing", "get", 200);
    }
}

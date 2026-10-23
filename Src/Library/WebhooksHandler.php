<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\LogStream;

/**
 * Client for the WebhooksHandler (guibranco/WebhooksHandler) bearer-token
 * protected /broker endpoints — the C# service that forwards GitHub webhook
 * payloads to the LavinMQ brokers. Distinct from Webhooks (the PHP consumer's
 * API) and RabbitMq (the brokers' own management API).
 */
class WebhooksHandler
{
    private $apiUrl;

    private $headers;

    private $request;

    public function __construct()
    {
        global $webhooksHandlerApiUrl, $webhooksHandlerApiToken;

        $config = new Configuration();
        $config->init();

        if (!file_exists(__DIR__ . "/../secrets/webhooksHandler.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: webhooksHandler.secrets.php");
        }

        require_once __DIR__ . "/../secrets/webhooksHandler.secrets.php";

        $this->apiUrl = rtrim($webhooksHandlerApiUrl, "/") . "/";
        $this->headers = [
            "Authorization: Bearer {$webhooksHandlerApiToken}",
            "Accept: application/json",
            "Cache-Control: no-cache",
            constant("USER_AGENT"),
        ];
        $this->request = new Request();
    }

    private function doGet(string $endpoint): array
    {
        LogStream::debug("WebhooksHandler API request", ["method" => "GET", "endpoint" => $endpoint], "webhooks-handler");
        $response = $this->request->get("{$this->apiUrl}{$endpoint}", $this->headers);

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody(), true) ?? [];
        }

        $error = $response->getStatusCode() == -1 ? $response->getMessage() : $response->getBody();
        LogStream::error("WebhooksHandler API request failed", [
            "method" => "GET",
            "endpoint" => $endpoint,
            "status_code" => $response->getStatusCode(),
            "error" => $error
        ], "webhooks-handler");
        throw new RequestException("Request to WebhooksHandler API failed: GET {$endpoint} ({$response->getStatusCode()})");
    }

    /**
     * Current-month broker usage for every configured broker connection, as
     * reported by GET /broker/usage/current, rendered for the dashboard
     * (see BrokerUsagePresenter).
     */
    public function getBrokerUsage(): array
    {
        $usage = $this->doGet("broker/usage/current");
        $presenter = new BrokerUsagePresenter();
        return $presenter->present($usage);
    }
}

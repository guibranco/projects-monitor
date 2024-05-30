<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;

class Webhooks
{
    private const API_URL = "https://guilhermebranco.com.br/webhooks/api.php";

    private $token;

    private $request;

    public function __construct()
    {
        global $webhooksApiToken;

        if (!file_exists(__DIR__ . "/../secrets/webhooks.secrets.php")) {
            throw new WebhooksException("File not found: webhooks.secrets.php");
        }
        
        require_once __DIR__ . "/../secrets/webhooks.secrets.php";

        $this->token = $webhooksApiToken;
        $this->request = new Request();
    }

    public function getDashboard()
    {
        $headers = [
            "Authorization: token {$this->token}",
            "Accept: application/json",
            "Cache-Control: no-cache",
            "User-Agent: ProjectsMonitor/1.0 (+https://github.com/guibranco/projects-monitor)"
        ];

        $response = $this->request->get(self::API_URL, $headers);

        if ($response->statusCode != 200) {
            throw new WebhooksException("Error: {$response->body}");
        }

        return json_decode($response->body);
    }
}

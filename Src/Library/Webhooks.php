<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\TimeZone;

class Webhooks
{
    private const API_URL = "https://guilhermebranco.com.br/webhooks/api.php";

    private $request;

    private $headers;

    public function __construct()
    {
        global $webhooksApiToken;

        if (!file_exists(__DIR__ . "/../secrets/webhooks.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: webhooks.secrets.php");
        }

        require_once __DIR__ . "/../secrets/webhooks.secrets.php";

        $timeZone = new TimeZone();
        $this->headers = [
            "Authorization: token {$webhooksApiToken}",
            "Accept: application/json",
            "Cache-Control: no-cache",
            "User-Agent: ProjectsMonitor/1.0 (+https://github.com/guibranco/projects-monitor)",
            "X-timezone: {$timeZone->getTimeZone()}",
            "X-timezone-offset: {$timeZone->getOffset()}"
        ];
        $this->request = new Request();
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

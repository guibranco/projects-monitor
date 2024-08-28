<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;

class AppVeyorProjects
{
    private const APPVEYOR_API_URL = "https://ci.appveyor.com/api/";

    private $request;

    private $headers;

    public function __construct()
    {
        new Configuration();

        global $appVeyorApiKey;

        if (!file_exists(__DIR__ . "/../secrets/appVeyor.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: appVeyor.secrets.php");
        }

        require_once __DIR__ . "/../secrets/appVeyor.secrets.php";

        $this->request = new Request();
        $this->headers = ['Authorization' => 'Bearer {$appVeyorApiKey}', 'Content-Type' => 'application/json'];
    }

    private function getProjects()
    {
        $url = 'https://ci.appveyor.com/api/projects';
        $response = $this->request->get($url, $this->headers);

        if ($response->statusCode != 200) {
            $error = $response->statusCode == -1 ? $response->error : $response->body;
            throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
        }

        return json_decode($response->body);
    }
}

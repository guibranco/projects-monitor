<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;

class GitHub
{

    private const GITHUB_API_URL = "https://api.github.com/search/";

    private $token;

    private $request;

    public function __construct()
    {
        global $gitHubToken;

        if (!file_exists(__DIR__ . "/../secrets/gitHub.secrets.php")) {
            throw new Exception("File not found: gitHub.secrets.php");
        }

        require_once __DIR__ . "/../secrets/gitHub.secrets.php";

        $this->token = $gitHubToken;
        $this->request = new Request();
    }

    public function getIssues()
    {
        $users = array(
            "guibranco",
            "ApiBR",
            "GuilhermeStracini",
            "InovacaoMediaBrasil",
        );

        $url = self::GITHUB_API_URL .
            "issues?q=" .
            urlencode("is:open is:issue archived:false " .
                implode(" ", array_map(function ($user) {
                    return "user:{$user}";
                }, $users)));
        $headers = [
            "Authorization: token {$this->token}",
            "Accept: application/vnd.github.v3+json",
            "X-GitHub-Api-Version: 2022-11-28",
            "User-Agent: ProjectsMonitor/1.0"
        ];

        $response = $this->request->get($url, $headers);

        if ($response->statusCode != 200) {
            throw new Exception("Error: {$response->body}");
        }

        $data = json_decode($response->body);
        return $data->total_count;
    }

    public function getPullRequests()
    {
        $users = array(
            "guibranco",
            "ApiBR",
            "GuilhermeStracini",
            "InovacaoMediaBrasil",
            "rustdevbr",
            "pythondevbr",
            "pydevbr",
            "dotnetdevbr",
            "nodejsdevbr",
            "rubydevbr",
            "frontend-ao",
            "frontend-pt",
            "backend-ao",
            "backend-pt",
            "developersRJ"
        );

        $url = self::GITHUB_API_URL .
            "issues?q=" .
            urlencode("is:open is:pr archived:false sort:updated-desc " .
                implode(" ", array_map(function ($user) {
                    return "user:{$user}";
                }, $users)));
        $headers = [
            "Authorization: token {$this->token}",
            "Accept: application/vnd.github.v3+json",
            "X-GitHub-Api-Version: 2022-11-28",
            "User-Agent: ProjectsMonitor/1.0"
        ];

        $response = $this->request->get($url, $headers);

        if ($response->statusCode != 200) {
            throw new Exception("Error: {$response->body}");
        }

        $data = json_decode($response->body);
        return $data->total_count;
    }
}
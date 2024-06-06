<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use FastVolt\Helper\Markdown;

class GitHub
{
    private const GITHUB_API_URL = "https://api.github.com/";

    private $token;

    private $request;

    public function __construct()
    {
        global $gitHubToken;

        if (!file_exists(__DIR__ . "/../secrets/gitHub.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: gitHub.secrets.php");
        }

        require_once __DIR__ . "/../secrets/gitHub.secrets.php";

        $this->token = $gitHubToken;
        $this->request = new Request();
    }

    private function getRequest($users, $type, $label = null)
    {
        $url = self::GITHUB_API_URL .
            "search/issues?q=" .
            urlencode("is:open is:" . $type . " archived:false " .
                ($label == null ? "" : "label:{$label} ") .
                implode(" ", array_map(function ($user) {
                    return "user:{$user}";
                }, $users)));
        $headers = [
            "Authorization: token {$this->token}",
            "Accept: application/vnd.github.v3+json",
            "X-GitHub-Api-Version: 2022-11-28",
            "User-Agent: ProjectsMonitor/1.0 (+https://github.com/guibranco/projects-monitor)"
        ];

        $response = $this->request->get($url, $headers);

        if ($response->statusCode != 200) {
            throw new RequestException("Code: {$response->statusCode} - Error: {$response->body}");
        }

        return json_decode($response->body);
    }

    private function mapItems($items)
    {
        if (count($items) == 0) {
            return array();
        }

        $result = array();

        $result[] = array("Title", "Repository", "User");

        foreach ($items as $item) {
            $repositoryName = str_replace("https://api.github.com/repos/", "", $item->repository_url);
            $labels = implode(" ", array_map(function ($label) {
                return "<span style='background-color: #" . $label->color . "; color: #" . (Color::luminance($label->color) > 90 ? "000" : "fff") . "; padding: 2px; border-radius: 5px; border: 1px solid #000;'>" . $label->name . "</span>";
            }, $item->labels));
            $result[] = array(
                "<a href='" . $item->html_url . "' target='_blank'>[#" . $item->number . "] " . $item->title . " " . $labels . "</a>",
                "<a href='https://github.com/" . $repositoryName . "' target='_blank'><img alt='login' src='https://img.shields.io/badge/" . str_replace("-", "--", $repositoryName) . "-black?style=flat&logo=github' /></a>",
                "<a href='" . $item->user->html_url . "' target='_blank'><img alt='login' src='https://img.shields.io/badge/" . str_replace("-", "--", $item->user->login) . "-black?style=social&logo=github' /></a>"
            );
        }

        return $result;
    }

    public function getIssues()
    {
        $users = array(
            "guibranco",
            "ApiBR",
            "GuilhermeStracini",
            "InovacaoMediaBrasil",
        );

        $data = array();
        $result = $this->getRequest($users, "issue");
        $resultWip = $this->getRequest($users, "issue", "WIP");
        $resultBug = $this->getRequest($users, "issue", "bug");
        $resultTriage = $this->getRequest($users, "issue", "triage");
        $data["total_count"] = $result->total_count;
        $data["latest"] = $this->mapItems($result->items);
        $data["wip"] = $this->mapItems($resultWip->items);
        $data["bug"] = $this->mapItems($resultBug->items);
        $data["triage"] = $this->mapItems($resultTriage->items);

        return $data;
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

        $data = array();
        $result = $this->getRequest($users, "pr");
        $data["total_count"] = $result->total_count;
        $data["latest"] = $this->mapItems($result->items);

        return $data;
    }

    public function getLatestReleaseOfBancosBrasileiros()
    {
        $url = self::GITHUB_API_URL . "repos/guibranco/bancosbrasileiros/releases/latest";
        $headers = [
            "Authorization: token {$this->token}",
            "Accept: application/vnd.github.v3+json",
            "X-GitHub-Api-Version: 2022-11-28",
            "User-Agent: ProjectsMonitor/1.0 (+https://github.com/guibranco/projects-monitor)"
        ];

        $response = $this->request->get($url, $headers);

        if ($response->statusCode != 200) {
            throw new RequestException("Code: {$response->statusCode} - Error: {$response->body}");
        }

        $mkd = Markdown::new();

        $body = json_decode($response->body);

        $mkd->setContent($body->body);
        $data = array();
        $data["created"] = date("H:i:s d/m/Y", strtotime($body->created_at));
        $data["published"] = date("H:i:s d/m/Y", strtotime($body->published_at));
        $data["title"] = $body->name;
        $data["description"] = $mkd->toHtml();

        return $data;
    }
}

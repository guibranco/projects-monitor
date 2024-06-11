<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use FastVolt\Helper\Markdown;

class GitHub
{
    private const GITHUB_API_URL = "https://api.github.com/";

    private $request;

    private $headers;

    public function __construct()
    {
        global $gitHubToken;

        if (!file_exists(__DIR__ . "/../secrets/gitHub.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: gitHub.secrets.php");
        }

        require_once __DIR__ . "/../secrets/gitHub.secrets.php";

        $this->request = new Request();

        $this->headers = [
            "Authorization: token {$gitHubToken}",
            "Accept: application/vnd.github.v3+json",
            "X-GitHub-Api-Version: 2022-11-28",
            "User-Agent: ProjectsMonitor/1.0 (+https://github.com/guibranco/projects-monitor)"
        ];
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
        $response = $this->request->get($url, $this->headers);

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

        $mkd = Markdown::new();

        foreach ($items as $item) {
            $repositoryName = str_replace("https://api.github.com/repos/", "", $item->repository_url);
            $labels = implode(" ", array_map(function ($label) {
                return "<span style='background-color: #" . $label->color . ";color: #" . (Color::luminance($label->color) > 120 ? "000" : "fff") . ";padding: 0 7px;border-radius: 24px;border: 1px solid #000;line-height: 21px;text-wrap:nowrap;'>" . $label->name . "</span>";
            }, $item->labels));
            $mkd->setContent($item->title);
            $title = $mkd->toHtml();
            $result[] = array(
                "<a href='" . $item->html_url . "' target='_blank'>[#" . $item->number . "] " . $title . "<br />" . $labels . "</a>",
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
        $repository = "guibranco/bancosbrasileiros";
        $url = self::GITHUB_API_URL . "repos/" . $repository . "/releases/latest";
        $response = $this->request->get($url, $this->headers);

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
        $data["release_url"] = $body->html_url;
        $data["repository"] = $repository;
        $data["author"] = $body->author->login;

        return $data;
    }

    private function getBilling($type, $items)
    {
        $data = array();

        foreach ($items as $item) {
            $urlActions = self::GITHUB_API_URL . "{$type}/{$item}/settings/billing/actions";
            $responseActions = $this->request->get($urlActions, $this->headers);
            if ($responseActions->statusCode != 200) {
                throw new RequestException("Code: {$responseActions->statusCode} - Error: {$responseActions->body}");
            }
            $contentActions = json_decode($responseActions->body);

            $urlStorage = self::GITHUB_API_URL . "{$type}/{$item}/settings/billing/shared-storage";
            $responseStorage = $this->request->get($urlStorage, $this->headers);
            if ($responseStorage->statusCode != 200) {
                throw new RequestException("Code: {$responseStorage->statusCode} - Error: {$responseStorage->body}");
            }
            $contentStorage = json_decode($responseStorage->body);

            $used = $contentActions->total_minutes_used;
            $included = $contentActions->included_minutes;
            $percentage = ($used * 100) / $included;
            $days = $contentStorage->days_left_in_billing_cycle;
            $colorActions = "green";
            if ($percentage >= 50) {
                $colorActions = "yellow";
            } elseif ($percentage >= 75) {
                $colorActions = "orange";
            } elseif ($percentage >= 90) {
                $colorActions = "red";
            }

            $colorDays = "green";
            if ($days >= 5) {
                $colorDays = "yellow";
            } elseif ($days >= 15) {
                $colorDays = "orange";
            } elseif ($days >= 20) {
                $colorDays = "red";
            }

            $accountLink = "<a href='https://github.com/" . $item . "/settings' target='_blank'><img alt='login' src='https://img.shields.io/badge/" . str_replace("-", "--", $item) . "-black?style=social&logo=github' /></a>";
            $actionsImage = "<img alt='Actions used' src='https://img.shields.io/badge/" . $percentage . "%-" . $used . "/" . $included . "_minutes-" . $colorActions . "?style=for-the-badge&labelColor=black' />";
            $daysImage = "<img alt='Actions used' src='https://img.shields.io/badge/" . $days . "%-Days_remaining-" . $colorDays . "?style=for-the-badge&labelColor=black' />";


            $data[] = array($accountLink, $actionsImage, $daysImage);
        }

        return $data;
    }

    public function getAccountUsage()
    {
        $orgs = array("ApiBR", "GuilhermeStracini", "InovacaoMediaBrasil");

        $resultUsers = $this->getBilling("users", ["GuiBranco"]);
        $resultOrgs = $this->getBilling("orgs", $orgs);

        $result = array_merge($resultUsers, $resultOrgs);
        sort($result);
        array_unshift($result, array("Account", "Actions usage/quota", "Days Left In Billing Cycle"));
        return $result;
    }
}

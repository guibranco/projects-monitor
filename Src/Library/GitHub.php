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

    private function getSearch($queryString)
    {
        $hash = md5($queryString);
        $cache = "cache/github_{$hash}.json";

        if (file_exists($cache) && filemtime($cache) > strtotime("-3 minute")) {
            return json_decode(file_get_contents($cache));
        }

        $url = self::GITHUB_API_URL . "search/issues?q=" . urlencode(preg_replace('!\s+!', ' ', "is:open archived:false is:{$queryString}"));
        $response = $this->request->get($url, $this->headers);

        if ($response->statusCode != 200) {
            $error = $response->statusCode == -1 ? $response->error : $response->body;
            throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
        }

        file_put_contents($cache, $response->body);
        return json_decode($response->body);
    }

    private function getWithLabel($users, $type, $label = null, $labelsToRemove = null)
    {
        $labels = "";
        if ($label !== null) {
            $labels = "label:{$label}";
        }

        $labelsRemove = "";
        if ($labelsToRemove !== null) {
            $labelsRemove = implode(" ", array_map(function ($labelToRemove) { return "-label:{$labelToRemove}"; }, $labelsToRemove));
        }

        $usersList = implode(" ", array_map(function ($user) { return "user:{$user}"; }, $users));
        $queryString = "{$type} ${labels} {$labelsRemove} {$usersList}";

        return $this->getSearch($queryString);
    }

    private function getWithUserExclusion($type, $filter, $user, $usersToExclude)
    {
        $filterString = "{$filter}:{$user}";
        $usersToRemove = implode(" ", array_map(function ($user) { return "-user:{$user}"; }, $usersToExclude));
        $queryString = "{$type} {$filterString} {$usersToRemove}";

        return $this->getSearch($queryString);
    }

    private function mapItems($items)
    {
        if (count($items) == 0) {
            return array();
        }

        $result = array();

        $result[] = array("Number", "Title", "Repository", "User");

        $mkd = Markdown::new();

        foreach ($items as $item) {
            $repositoryName = str_replace("https://api.github.com/repos/", "", $item->repository_url);
            $labelsJson = $item->labels;
            usort($labelsJson, function ($a, $b) { return strnatcmp($a->name, $b->name); });
            $labels = implode(" ", array_map(function ($label) {
                return "<span style='background-color: #" . $label->color . ";color: #" . (Color::luminance($label->color) > 120 ? "000" : "fff") . ";padding: 0 7px;border-radius: 24px;border: 1px solid #000;line-height: 21px;text-wrap:nowrap;'>" . $label->name . "</span>";
            }, $labelsJson));
            $mkd->setContent($item->title);
            $title = $mkd->toHtml();
            $colorNumber = Color::generateColorFromText($repositoryName);
            $styleNumber = "style='background-color: #" . $colorNumber . ";color: #" . (Color::luminance($colorNumber) > 120 ? "000" : "fff") . ";padding: 0 7px;border-radius: 24px;border: 1px solid #000;line-height: 21px;text-wrap:nowrap;'";
            $result[] = array(
                "<a href='" . $item->html_url . "' target='_blank'><span " . $styleNumber . ">#" . $item->number . "</span></a>",
                "<a href='" . $item->html_url . "' target='_blank'>" . $title . "<br />" . $labels . "</a>",
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

        $resultAll = $this->getWithLabel($users, "issue");
        $resultOthers = $this->getWithLabel($users, "issue", null, ["WIP", "'🛠 WIP'", "bug", "triage"]);
        $resultWip = $this->getWithLabel($users, "issue", "WIP");
        $resultWip2 = $this->getWithLabel($users, "issue", "'🛠 WIP'");
        $resultBug = $this->getWithLabel($users, "issue", "bug");
        $resultTriage = $this->getWithLabel($users, "issue", "triage");
        $resultAssigned = $this->getWithUserExclusion("issue", "assignee", array_slice($users, 0, 1)[0], $users);
        $resultAuthored = $this->getWithUserExclusion("issue", "author", array_slice($users, 0, 1)[0], $users);

        $data = array();
        $data["total_count"] = $resultAll->total_count;
        $data["others"] = $this->mapItems($resultOthers->items);
        $data["wip"] = $this->mapItems($resultWip->items);
        $data["wip"] = array_merge($data["wip"], $this->mapItems($resultWip2->items));
        $data["bug"] = $this->mapItems($resultBug->items);
        $data["triage"] = $this->mapItems($resultTriage->items);
        $data["assigned"] = $this->mapItems($resultAssigned->items);
        $data["authored"] = $this->mapItems($resultAuthored->items);

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

        $result = $this->getWithLabel($users, "pr");
        $resultAuthored = $this->getWithUserExclusion("pr", "author", array_slice($users, 0, 1)[0], $users);

        $data = array();
        $data["total_count"] = $result->total_count;
        $data["latest"] = $this->mapItems($result->items);
        $data["authored"] = $this->mapItems($resultAuthored->items);

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
                $error = $responseActions->statusCode == -1 ? $responseActions->error : $responseActions->body;
                throw new RequestException("Code: {$responseActions->statusCode} - Error: {$error}");
            }
            $contentActions = json_decode($responseActions->body);

            $urlStorage = self::GITHUB_API_URL . "{$type}/{$item}/settings/billing/shared-storage";
            $responseStorage = $this->request->get($urlStorage, $this->headers);
            if ($responseStorage->statusCode != 200) {
                $error = $responseActions->statusCode == -1 ? $responseStorage->error : $responseStorage->body;
                throw new RequestException("Code: {$responseStorage->statusCode} - Error: {$error}");
            }
            $contentStorage = json_decode($responseStorage->body);

            $used = $contentActions->total_minutes_used;
            $included = $contentActions->included_minutes;
            $percentage = ($used * 100) / $included;
            $days = $contentStorage->days_left_in_billing_cycle;
            $colorActions = "green";
            if ($percentage >= 90) {
                $colorActions = "red";
            } elseif ($percentage >= 75) {
                $colorActions = "orange";
            } elseif ($percentage >= 50) {
                $colorActions = "yellow";
            }

            $colorDays = "green";
            if ($days >= 20) {
                $colorDays = "red";
            } elseif ($days >= 15) {
                $colorDays = "orange";
            } elseif ($days >= 5) {
                $colorDays = "yellow";
            }

            $linkPrefix = $type == "users" ? "" : "organizations/" . $item . "/";
            $accountLink = "<a href='https://github.com/" . $linkPrefix . "settings/billing/summary' target='_blank'><img alt='login' src='https://img.shields.io/badge/" . str_replace("-", "--", $item) . "-black?style=social&logo=github' /></a>";
            $actionsImage = "<img alt='Actions used' src='https://img.shields.io/badge/" . number_format($percentage, 2, '.', '') . "%25-" . $used . "%2F" . $included . "_minutes-" . $colorActions . "?style=for-the-badge&labelColor=black' />";
            $daysImage = "<img alt='Actions used' src='https://img.shields.io/badge/" . $days . "-Days_remaining-" . $colorDays . "?style=for-the-badge&labelColor=black' />";


            $data[$item] = array($accountLink, $actionsImage, $daysImage);
        }

        return $data;
    }

    public function getAccountUsage()
    {
        $orgs = array("ApiBR", "GuilhermeStracini", "InovacaoMediaBrasil");

        $resultUsers = $this->getBilling("users", ["GuiBranco"]);
        $resultOrgs = $this->getBilling("orgs", $orgs);

        $result = array_merge($resultUsers, $resultOrgs);
        ksort($result);
        $result = array_values($result);
        array_unshift($result, array("Account", "Actions usage/quota", "Days Left In Billing Cycle"));
        return $result;
    }
}

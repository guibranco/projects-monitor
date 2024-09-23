<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use FastVolt\Helper\Markdown;

class GitHub
{
    private const GITHUB_API_URL = "https://api.github.com/";

    private $request;

    private $headers;

    private $apiUsage;

    public function __construct()
    {
        $config = new Configuration();
        $config->init();

        global $gitHubToken;

        if (!file_exists(__DIR__ . "/../secrets/gitHub.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: gitHub.secrets.php");
        }

        require_once __DIR__ . "/../secrets/gitHub.secrets.php";

        $this->apiUsage = array();
        $this->request = new Request();
        $this->headers = [
            "Authorization: token {$gitHubToken}",
            "Accept: application/vnd.github.v3+json",
            "X-GitHub-Api-Version: 2022-11-28",
            constant("USER_AGENT")
        ];
    }

    private function processHeaders($headers): void
    {
        $limit = $headers["X-RateLimit-Limit"];
        $remaining = $headers["X-RateLimit-Remaining"];
        $reset = $headers["X-RateLimit-Reset"];
        $used = $headers["X-RateLimit-Used"];
        $resource = $headers["X-RateLimit-Resource"];
        $this->apiUsage[$resource] = array("limit" => $limit, "remaining" => $remaining, "reset" => $reset, "used" => $used);
    }

    private function requestInternal($url, $isRetry = false)
    {
        $response = $this->request->get($url, $this->headers);

        if ($response->statusCode !== -1 || $isRetry) {
            $this->processHeaders($response->headers);
            return $response;
        }

        return $this->requestInternal($url, true);
    }

    private function getSearch($queryString)
    {
        $hash = md5($queryString);
        $cache = "cache/github_search_{$hash}.json";

        if (file_exists($cache) && filemtime($cache) > strtotime("-3 minute")) {
            return json_decode(file_get_contents($cache));
        }

        $url = self::GITHUB_API_URL . "search/issues?q=" . urlencode(preg_replace('!\s+!', ' ', "is:open archived:false is:{$queryString}")) . "&per_page=100";
        $response = $this->requestInternal($url);
        if ($response->statusCode !== 200) {
            $error = $response->statusCode === -1 ? $response->error : $response->body;
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
            $labelsRemove = implode(" ", array_map(function ($labelToRemove) {
                return "-label:{$labelToRemove}";
            }, $labelsToRemove));
        }

        $usersList = implode(" ", array_map(function ($user) {
            return "user:{$user}";
        }, $users));
        $queryString = "{$type} {$labels} {$labelsRemove} {$usersList}";

        return $this->getSearch($queryString);
    }

    private function getWithUserExclusion($type, $filter, $user, $usersToExclude)
    {
        $filterString = "{$filter}:{$user}";
        $usersToRemove = implode(" ", array_map(function ($user) {
            return "-user:{$user}";
        }, $usersToExclude));
        $queryString = "{$type} {$filterString} {$usersToRemove}";

        return $this->getSearch($queryString);
    }

    private function addHeader(&$items)
    {
        $keys = array_keys($items);
        foreach ($keys as $key) {
            if (is_array($items[$key]) && count($items[$key]) > 0) {
                array_unshift($items[$key], ["Number", "Title", "Repository", "User"]);
            }
        }
    }

    private function mapItems($items)
    {
        if (count($items) == 0) {
            return array();
        }

        $result = array();
        $mkd = Markdown::new();

        foreach ($items as $item) {
            $repositoryName = str_replace("https://api.github.com/repos/", "", $item->repository_url);
            $labelsJson = $item->labels;
            usort($labelsJson, function ($a, $b) {
                return strnatcasecmp($a->name, $b->name);
            });
            $labels = implode(" ", array_map(function ($label) {
                return "<span style='background-color: #" . $label->color . ";color: #" . (Color::luminance($label->color) > 120 ? "000" : "fff") . ";padding: 0 7px;border-radius: 24px;border: 1px solid #000;line-height: 21px;text-wrap:nowrap;'>" . $label->name . "</span>";
            }, $labelsJson));

            $mkd->setContent(htmlentities($item->title));
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
        $users = ["guibranco", "ApiBR", "GuilhermeStracini", "InovacaoMediaBrasil"];
        $vacanciesUsers = ["rustdevbr", "pydevbr", "dotnetdevbr", "nodejsdevbr", "rubydevbr", "frontend-ao", "frontend-pt", "backend-ao", "backend-pt", "developersRJ"];
        $allUsers = array_merge($users, $vacanciesUsers);

        $resultAll = $this->getWithLabel($users, "issue");
        $resultOthers = $this->getWithLabel($users, "issue", null, ["WIP", "\"🛠 WIP\"", "bug", "\"🐛 bug\"", "triage", "\"🚦awaiting triage\"", "\"🚦 awaiting triage\"", "blocked", "\"🚷blocked\"", "\"🚷 blocked\""]);
        $resultWip = $this->getWithLabel($users, "issue", "WIP", ["blocked", "\"🚷 blocked\""]);
        $resultWip2 = $this->getWithLabel($users, "issue", "\"🛠 WIP\"", ["blocked", "\"🚷 blocked\""]);
        $resultBlocked = $this->getWithLabel($users, "issue", "blocked");
        $resultBlocked2 = $this->getWithLabel($users, "issue", "\"🚷blocked\"");
        $resultBlocked3 = $this->getWithLabel($users, "issue", "\"🚷 blocked\"");
        $resultBug = $this->getWithLabel($users, "issue", "bug", ["blocked", "\"🚷 blocked\""]);
        $resultBug2 = $this->getWithLabel($users, "issue", "\"🐛 bug\"", ["blocked", "\"🚷 blocked\""]);
        $resultTriage = $this->getWithLabel($allUsers, "issue", "awaiting triage");
        $resultTriage2 = $this->getWithLabel($allUsers, "issue", "\"🚦awaiting triage\"");
        $resultTriage3 = $this->getWithLabel($allUsers, "issue", "\"🚦 awaiting triage\"");
        $resultTriage4 = $this->getWithLabel($allUsers, "issue", "triage");
        $resultAssigned = $this->getWithUserExclusion("issue", "assignee", array_slice($users, 0, 1)[0], $users);
        $resultAuthored = $this->getWithUserExclusion("issue", "author", array_slice($users, 0, 1)[0], $users);

        $data = array();
        $data["total_count"] = $resultAll->total_count;
        $data["others"] = $this->mapItems($resultOthers->items);
        $data["wip"] = array_merge($this->mapItems($resultWip->items), $this->mapItems($resultWip2->items));
        $data["blocked"] = array_merge($this->mapItems($resultBlocked->items), $this->mapItems($resultBlocked2->items), $this->mapItems($resultBlocked3->items));
        $data["bug"] = array_merge($this->mapItems($resultBug->items), $this->mapItems($resultBug2->items));
        $data["triage"] = array_merge($this->mapItems($resultTriage->items), $this->mapItems($resultTriage2->items), $this->mapItems($resultTriage3->items), $this->mapItems($resultTriage4->items));
        $data["assigned"] = $this->mapItems($resultAssigned->items);
        $data["authored"] = $this->mapItems($resultAuthored->items);

        $this->addHeader($data);

        return $data;
    }

    public function getPullRequests()
    {
        $users = ["guibranco", "ApiBR", "GuilhermeStracini", "InovacaoMediaBrasil", "rustdevbr", "pythondevbr", "pydevbr", "dotnetdevbr", "nodejsdevbr", "rubydevbr", "frontend-ao", "frontend-pt", "backend-ao", "backend-pt", "developersRJ"];

        $result = $this->getWithLabel($users, "pr");

        $resultNotBlocked = $this->getWithLabel($users, "pr", null, ["blocked", "\"🚷 blocked\""]);
        $resultBlocked = $this->getWithLabel($users, "pr", "blocked");
        $resultBlocked2 = $this->getWithLabel($users, "pr", "\"🚷 blocked\"");
        $resultAuthored = $this->getWithUserExclusion("pr", "author", array_slice($users, 0, 1)[0], $users);
        $resultTriage = $this->getWithLabel($users, "pr", "awaiting triage");
        $resultTriage2 = $this->getWithLabel($users, "pr", "\"🚦awaiting triage\"");
        $resultTriage3 = $this->getWithLabel($users, "pr", "\"🚦 awaiting triage\"");
        $resultTriage4 = $this->getWithLabel($users, "pr", "triage");

        $data = array();
        $data["total_count"] = $result->total_count;
        $data["latest"] = $this->mapItems($resultNotBlocked->items);
        $data["blocked"] = array_merge($this->mapItems($resultBlocked->items), $this->mapItems($resultBlocked2->items));
        $data["authored"] = $this->mapItems($resultAuthored->items);
        $data["awaiting_triage"] = array_merge($this->mapItems($resultTriage->items), $this->mapItems($resultTriage2->items), $this->mapItems($resultTriage3->items), $this->mapItems($resultTriage4->items));

        $this->addHeader($data);

        return $data;
    }

    private function getLatestRelease($owner, $repository)
    {
        $cache = "cache/github_latest_release_{$owner}_{$repository}.json";
        if (file_exists($cache) && filemtime($cache) > strtotime("-1 hour")) {
            $response = json_decode(file_get_contents($cache));
        } else {
            $url = self::GITHUB_API_URL . "repos/" . $owner . "/" . $repository . "/releases/latest";
            $response = $this->requestInternal($url);
            if ($response->statusCode !== 200) {
                $error = $response->statusCode === -1 ? $response->error : $response->body;
                throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
            }

            file_put_contents($cache, json_encode($response));
        }

        return json_decode($response->body);
    }

    private function getLatestReleaseDetails($account, $repository)
    {
        $body = $this->getLatestRelease($account, $repository);
        $mkd = Markdown::new();
        $mkd->setContent($body->body);
        $data = array();
        $data["created"] = date("H:i:s d/m/Y", strtotime($body->created_at));
        $data["published"] = date("H:i:s d/m/Y", strtotime($body->published_at));
        $data["title"] = $body->name;
        $data["description"] = $mkd->toHtml();
        $data["release_url"] = $body->html_url;
        $data["repository"] = $account . "/" . $repository;
        $data["author"] = $body->author->login;

        return $data;
    }

    public function getLatestReleaseOfBancosBrasileiros()
    {
        return $this->getLatestReleaseDetails("guibranco", "bancosbrasileiros");
    }

    private function getBillingInternal($accountType, $account, $type)
    {
        $cache = "cache/github_billing_{$accountType}_{$account}_{$type}.json";
        if (file_exists($cache) && filemtime($cache) > strtotime("-5 minute")) {
            $response = json_decode(file_get_contents($cache));
        } else {
            $url = self::GITHUB_API_URL . "{$accountType}/{$account}/settings/billing/{$type}";
            $response = $this->requestInternal($url);
            if ($response->statusCode !== 200) {
                $error = $response->statusCode === -1 ? $response->error : $response->body;
                throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
            }

            file_put_contents($cache, json_encode($response));
        }

        return json_decode($response->body);
    }

    private function getBilling($type, $items)
    {
        $data = array();

        foreach ($items as $item) {
            $contentActions = $this->getBillingInternal($type, $item, "actions");
            $contentStorage = $this->getBillingInternal($type, $item, "shared-storage");

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

    public function getApiUsage()
    {
        $data = array();
        $data[] = ["Resource", "Limit", "Remaining", "Reset", "Used"];
        foreach($this->apiUsage as $resource => $data) {
            $data[] = [$resource, $data["limit"], $data["remaining"], $data["reset"], $data["used"]];
        }
        return $data;
    }
}

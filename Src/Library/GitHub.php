<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use GuiBranco\Pancake\RequestException;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\Logger;
use FastVolt\Helper\Markdown;

class GitHub
{
    private const GITHUB_API_URL = "https://api.github.com/";
    private const DATE_TIME_FORMAT = "H:i:s d/m/Y";
    private const ISSUE_TEXT = "issue";
    private const PR_TEXT = "pr";

    private const BLOCKED_TEXT_LABEL = "blocked";
    private const BLOCKED_LABEL = "\"🚷blocked\"";
    private const BLOCKED_SPACE_LABEL = "\"🚷 blocked\"";
    private const WIP_TEXT_LABEL = "WIP";
    private const WIP_SPACE_LABEL = "\"🛠 WIP\""
    ;
    private Request $request;

    private array $headers;

    public function __construct()
    {
        $config = new Configuration();
        $config->init();

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
            constant("USER_AGENT")
        ];
    }

    private function requestInternal(string $url, bool $isRetry = false): Response
    {
        $response = $this->request->get($url, $this->headers);

        if ($response->getStatusCode() !== -1 || $isRetry) {
            return $response;
        }

        return $this->requestInternal($url, true);
    }

    private function checkUsage($type): bool
    {
        if (!isset($_SESSION["api_usage"])) {
            return true;
        }

        $resources = $_SESSION["api_usage"];
        $resource = $resources->{$type};

        return $resource->remaining > 0;
    }

    private function getSearch($queryString): mixed
    {
        $hash = md5($queryString);
        $cache = "cache/github_search_{$hash}.json";

        $cacheExists = file_exists($cache);

        if ($cacheExists && filemtime($cache) > strtotime("-10 minute")) {
            return json_decode(file_get_contents($cache));
        }

        $result = (object) ['total_count' => 0, 'items' => []];
        if ($this->checkUsage("search") === false) {
            return $cacheExists
                ? json_decode(file_get_contents($cache))
                : $result;
        }

        $url = self::GITHUB_API_URL . "search/issues?q=" . urlencode(preg_replace('!\s+!', ' ', "is:open archived:false is:{$queryString}")) . "&per_page=100";
        $response = null;
        try {
            $response = $this->requestInternal($url);
            $response->ensureSuccessStatus();
            $body = $response->getBody();
            file_put_contents($cache, $body);
            $result = json_decode($body);
        } catch (RequestException $ex) {
            $message = sprintf(
                "GitHub search request failed - URL: %s, Error: %s, Code: %d, Response: %s",
                $url,
                $ex->getMessage(),
                $ex->getCode(),
                $response === null ? "null" : $response->toJson()
            );
            $logger = new Logger();
            $logger->logMessage($message);
        }

        return $result;
    }

    private function getWithLabel($users, $type, $label = null, $labelsToRemove = null): mixed
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

    private function getWithUserExclusion($type, $filter, $user, $usersToExclude): mixed
    {
        $filterString = "{$filter}:{$user}";
        $usersToRemove = implode(" ", array_map(function ($user) {
            return "-user:{$user}";
        }, $usersToExclude));
        $queryString = "{$type} {$filterString} {$usersToRemove}";
        return $this->getSearch($queryString);
    }

    private function addHeader(&$items): void
    {
        $keys = array_keys($items);
        foreach ($keys as $key) {
            if (is_array($items[$key]) && count($items[$key]) > 0) {
                array_unshift($items[$key], ["Number", "Title", "Repository", "User"]);
            }
        }
    }

    private function mapItems($items): array
    {
        if (count($items) == 0) {
            return array();
        }

        $result = array();

        foreach ($items as $item) {
            $repositoryName = str_replace("https://api.github.com/repos/", "", $item->repository_url);
            $labelsJson = $item->labels;
            usort($labelsJson, function ($a, $b) {
                return strnatcasecmp($a->name, $b->name);
            });
            $labels = implode(" ", array_map(function ($label) {
                return "<span style='background-color: #" . $label->color . ";color: #" . (Color::luminance($label->color) > 120 ? "000" : "fff") . ";padding: 0 7px;border-radius: 24px;border: 1px solid #000;line-height: 21px;text-wrap:nowrap;'>" . $label->name . "</span>";
            }, $labelsJson));

            $mkd = Markdown::new();
            $mkd->setContent(htmlentities($item->title));
            $title = $mkd->toHtml();

            $colorNumber = Color::generateColorFromText($repositoryName);
            $styleNumber = "style='background-color: #" . $colorNumber . ";color: #" . (Color::luminance($colorNumber) > 120 ? "000" : "fff") . ";padding: 0 7px;border-radius: 24px;border: 1px solid #000;line-height: 21px;text-wrap:nowrap;'";
            $result[] = array(
                "<a href='" . $item->html_url . "' target='_blank' rel='noopener noreferrer'><span " . $styleNumber . ">#" . $item->number . "</span></a>",
                "<a href='" . $item->html_url . "' target='_blank' rel='noopener noreferrer'>" . $title . "<br />" . $labels . "</a>",
                "<a href='https://github.com/" . $repositoryName . "' target='_blank' rel='noopener noreferrer'><img alt='login' src='https://img.shields.io/badge/" . str_replace("-", "--", $repositoryName) . "-black?style=flat&logo=github' /></a>",
                "<a href='" . $item->user->html_url . "' target='_blank' rel='noopener noreferrer'><img alt='login' src='https://img.shields.io/badge/" . str_replace("-", "--", $item->user->login) . "-black?style=social&logo=github' /></a>"
            );
        }

        return $result;
    }

    public function getIssues(): array
    {
        $users = ["guibranco", "ApiBR", "GuilhermeStracini", "InovacaoMediaBrasil"];
        $vacanciesUsers = ["rustdevbr", "pydevbr", "dotnetdevbr", "nodejsdevbr", "rubydevbr", "frontend-ao", "frontend-pt", "backend-ao", "backend-pt", "developersRJ"];
        $allUsers = array_merge($users, $vacanciesUsers);

        $resultAll = $this->getWithLabel($users, self::ISSUE_TEXT);
        $resultOthers = $this->getWithLabel($users, self::ISSUE_TEXT, null, [self::WIP_TEXT_LABEL, self::WIP_SPACE_LABEL, "bug", "\"🐛 bug\"", "triage", "\"🚦awaiting triage\"", "\"🚦 awaiting triage\"", self::BLOCKED_TEXT_LABEL, self::BLOCKED_LABEL, self::BLOCKED_SPACE_LABEL]);
        $resultWip1 = $this->getWithLabel($users, self::ISSUE_TEXT, self::WIP_TEXT_LABEL, [self::BLOCKED_TEXT_LABEL, self::BLOCKED_SPACE_LABEL]);
        $resultWip2 = $this->getWithLabel($users, self::ISSUE_TEXT, self::WIP_SPACE_LABEL, [self::BLOCKED_TEXT_LABEL, self::BLOCKED_SPACE_LABEL]);
        $resultBlocked1 = $this->getWithLabel($users, self::ISSUE_TEXT, self::BLOCKED_TEXT_LABEL);
        $resultBlocked2 = $this->getWithLabel($users, self::ISSUE_TEXT, self::BLOCKED_LABEL);
        $resultBlocked3 = $this->getWithLabel($users, self::ISSUE_TEXT, self::BLOCKED_SPACE_LABEL);
        $resultBug1 = $this->getWithLabel($users, self::ISSUE_TEXT, "bug", [self::BLOCKED_TEXT_LABEL, self::BLOCKED_LABEL, self::BLOCKED_SPACE_LABEL]);
        $resultBug2 = $this->getWithLabel($users, self::ISSUE_TEXT, "\"🐛 bug\"", [self::BLOCKED_TEXT_LABEL, self::BLOCKED_LABEL, self::BLOCKED_SPACE_LABEL]);
        $resultTriage1 = $this->getWithLabel($allUsers, self::ISSUE_TEXT, "awaiting triage");
        $resultTriage2 = $this->getWithLabel($allUsers, self::ISSUE_TEXT, "\"🚦awaiting triage\"");
        $resultTriage3 = $this->getWithLabel($allUsers, self::ISSUE_TEXT, "\"🚦 awaiting triage\"");
        $resultTriage4 = $this->getWithLabel($allUsers, self::ISSUE_TEXT, "triage");
        $resultAssigned = $this->getWithUserExclusion(self::ISSUE_TEXT, "assignee", array_slice($users, 0, 1)[0], $users);
        $resultAuthored = $this->getWithUserExclusion(self::ISSUE_TEXT, "author", array_slice($users, 0, 1)[0], $users);

        $data = array();
        $data["total_count"] = $resultAll->total_count;
        $data["others"] = $this->mapItems($resultOthers->items);
        $data[strtolower(self::WIP_TEXT_LABEL)] = array_merge($this->mapItems($resultWip1->items), $this->mapItems($resultWip2->items));
        $data[strtolower(self::BLOCKED_TEXT_LABEL)] = array_merge($this->mapItems($resultBlocked1->items), $this->mapItems($resultBlocked2->items), $this->mapItems($resultBlocked3->items));
        $data["bug"] = array_merge($this->mapItems($resultBug1->items), $this->mapItems($resultBug2->items));
        $data["triage"] = array_merge($this->mapItems($resultTriage1->items), $this->mapItems($resultTriage2->items), $this->mapItems($resultTriage3->items), $this->mapItems($resultTriage4->items));
        $data["assigned"] = $this->mapItems($resultAssigned->items);
        $data["authored"] = $this->mapItems($resultAuthored->items);

        $this->addHeader($data);

        return $data;
    }

    public function getPullRequests(): array
    {
        $users = ["guibranco", "ApiBR", "GuilhermeStracini", "InovacaoMediaBrasil", "rustdevbr", "pythondevbr", "pydevbr", "dotnetdevbr", "nodejsdevbr", "rubydevbr", "frontend-ao", "frontend-pt", "backend-ao", "backend-pt", "developersRJ"];

        $result = $this->getWithLabel($users, self::PR_TEXT);
        $resultNotBlocked = $this->getWithLabel($users, self::PR_TEXT, null, ["triage", "\"🚦awaiting triage\"", "\"🚦 awaiting triage\"", self::BLOCKED_TEXT_LABEL, self::BLOCKED_LABEL, self::BLOCKED_SPACE_LABEL]);
        $resultBlocked1 = $this->getWithLabel($users, self::PR_TEXT, self::BLOCKED_TEXT_LABEL);
        $resultBlocked2 = $this->getWithLabel($users, self::PR_TEXT, self::BLOCKED_LABEL);
        $resultBlocked3 = $this->getWithLabel($users, self::PR_TEXT, self::BLOCKED_SPACE_LABEL);
        $resultAuthored = $this->getWithUserExclusion(self::PR_TEXT, "author", array_slice($users, 0, 1)[0], $users);
        $resultTriage1 = $this->getWithLabel($users, self::PR_TEXT, "triage");
        $resultTriage2 = $this->getWithLabel($users, self::PR_TEXT, "awaiting triage");
        $resultTriage3 = $this->getWithLabel($users, self::PR_TEXT, "\"🚦awaiting triage\"");
        $resultTriage4 = $this->getWithLabel($users, self::PR_TEXT, "\"🚦 awaiting triage\"");

        $data = array();
        $data["total_count"] = $result->total_count;
        $data["latest"] = $this->mapItems($resultNotBlocked->items);
        $data[strtolower(self::BLOCKED_TEXT_LABEL)] = array_merge($this->mapItems($resultBlocked1->items), $this->mapItems($resultBlocked2->items), $this->mapItems($resultBlocked3->items));
        $data["authored"] = $this->mapItems($resultAuthored->items);
        $data["awaiting_triage"] = array_merge($this->mapItems($resultTriage1->items), $this->mapItems($resultTriage2->items), $this->mapItems($resultTriage3->items), $this->mapItems($resultTriage4->items));

        $this->addHeader($data);

        return $data;
    }

    private function getLatestRelease($owner, $repository): mixed
    {
        $cache = "cache/github_latest_release_{$owner}_{$repository}.json";
        $cacheExists = file_exists($cache);
        if ($cacheExists && filemtime($cache) > strtotime("-3 hour")) {
            return json_decode(file_get_contents($cache));
        }

        $result = (object) [
            'created_at' => time(),
            'published_at' => time(),
            'name' => 'N/A',
            'body' => "# {$owner}/{$repository} - Latest Release\n\nNo release found for this repository.",
            'html_url' => "https://github.com/{$owner}/{$repository}",
            'author' => (object) ['login' => 'N/A']
        ];

        if ($this->checkUsage("core") === false) {
            return $cacheExists
                ? json_decode(file_get_contents($cache))
                : $result;
        }

        $response = null;
        try {
            $url = self::GITHUB_API_URL . "repos/" . $owner . "/" . $repository . "/releases/latest";
            $response = $this->requestInternal($url);
            $response->ensureSuccessStatus();
            $body = $response->getBody();
            file_put_contents($cache, $body);
            $result = json_decode($body);
        } catch (RequestException $ex) {
            $message = sprintf(
                "GitHub latest release request failed - Owner: %s, Repo: %s, Error: %s, Code: %d, Response: %s",
                $owner,
                $repository,
                $ex->getMessage(),
                $ex->getCode(),
                $response === null ? "null" : $response->toJson()
            );
            $logger = new Logger();
            $logger->logMessage($message);
        }

        return $result;
    }

    private function getLatestReleaseDetails($account, $repository): array
    {
        $body = $this->getLatestRelease($account, $repository);
        $mkd = Markdown::new();

        if (!isset($body->body)) {
            $message = "Unable to get latest release details for repository {$account}/{$repository} - 'body' field missing from response. Response: " . print_r($body, true);
            $logger = new Logger();
            $logger->logMessage($message);
            return ["created" => "N/A", "published" => "N/A", "title" => "N/A", "description" => "N/A", "release_url" => "", "repository" => "{$account}/{$repository}", "author" => "N/A"];
        }

        $mkd->setContent($body->body);
        $data = array();
        $data["created"] = date(self::DATE_TIME_FORMAT, strtotime($body->created_at));
        $data["published"] = date(self::DATE_TIME_FORMAT, strtotime($body->published_at));
        $data["title"] = $body->name;
        $data["description"] = $mkd->toHtml();
        $data["release_url"] = $body->html_url;
        $data["repository"] = $account . "/" . $repository;
        $data["author"] = $body->author->login;

        return $data;
    }

    public function getLatestReleaseOfBancosBrasileiros(): array
    {
        return $this->getLatestReleaseDetails("guibranco", "bancosbrasileiros");
    }

    private function getBillingInternal(string $accountType, string $account, string $type): mixed
    {
        $cache = "cache/github_billing_{$accountType}_{$account}_{$type}.json";
        $cacheExists = file_exists($cache);

        if ($cacheExists && filemtime($cache) > strtotime("-1 hour")) {
            $cachedData = json_decode(file_get_contents($cache));
            return $this->transformBillingData($cachedData, $type);
        }

        $result = (object) [
            'total_minutes_used' => 0,
            'included_minutes' => 0,
            'days_left_in_billing_cycle' => 0
        ];

        if ($this->checkUsage("core") === false) {
            return $cacheExists
                ? $this->transformBillingData(json_decode(file_get_contents($cache)), $type)
                : $result;
        }

        $response = null;
        try {
            $url = self::GITHUB_API_URL . "{$accountType}/{$account}/settings/billing/{$type}";
            $response = $this->requestInternal($url);
            $response->ensureSuccessStatus();
            $body = $response->getBody();
            file_put_contents($cache, $body);
            $rawData = json_decode($body);
            $result = $this->transformBillingData($rawData, $type);
        } catch (RequestException $ex) {
            $message = sprintf(
                "GitHub billing request failed - Type: %s, Account: %s, BillingType: %s, Error: %s, Code: %d, Response: %s",
                $accountType,
                $account,
                $type,
                $ex->getMessage(),
                $ex->getCode(),
                $response === null ? "null" : $response->toJson()
            );
            $logger = new Logger();
            $logger->logMessage($message);
        }

        return $result;
    }

    /**
     * Transform new GitHub billing API response to match old format
     */
    private function transformBillingData($rawData, string $type): object
    {
        // Handle case where old API response format is still returned
        if (isset($rawData->total_minutes_used) || isset($rawData->days_left_in_billing_cycle)) {
            return $rawData;
        }

        // Handle new API response format
        if (!isset($rawData->usageItems) || !is_array($rawData->usageItems)) {
            return (object) [
                'total_minutes_used' => 0,
                'included_minutes' => 0,
                'days_left_in_billing_cycle' => $this->calculateDaysLeftInBillingCycle()
            ];
        }

        if ($type === 'actions') {
            return $this->transformActionsData($rawData->usageItems);
        } elseif ($type === 'shared-storage') {
            return $this->transformSharedStorageData($rawData->usageItems);
        }

        // Default fallback
        return (object) [
            'total_minutes_used' => 0,
            'included_minutes' => 0,
            'days_left_in_billing_cycle' => $this->calculateDaysLeftInBillingCycle()
        ];
    }

    /**
     * Transform GitHub Actions billing data from new format to old format
     */
    private function transformActionsData(array $usageItems): object
    {
        $totalMinutesUsed = 0;
        $totalPaidMinutesUsed = 0; // Now represented as $ amount
        $includedMinutes = 0; // Now represented as $ amount via discountAmount
        $minutesUsedBreakdown = [];

        foreach ($usageItems as $item) {
            // Filter for Actions minutes only
            if ($item->product === 'Actions' && $item->unitType === 'minutes') {
                // Sum total minutes used
                $totalMinutesUsed += $item->quantity;

                // Sum paid minutes (now as dollar amount via netAmount)
                $totalPaidMinutesUsed += $item->netAmount;

                // Sum included minutes (now as dollar amount via discountAmount)
                $includedMinutes += $item->discountAmount;

                // Build minutes breakdown by SKU
                $sku = $item->sku;
                if (!isset($minutesUsedBreakdown[$sku])) {
                    $minutesUsedBreakdown[$sku] = 0;
                }
                $minutesUsedBreakdown[$sku] += $item->quantity;
            }
        }

        return (object) [
            'total_minutes_used' => $totalMinutesUsed,
            'total_paid_amount'    => $totalPaidMinutesUsed, // Dollar amount
            'included_amount'      => $includedMinutes,       // Dollar amount
            'minutes_used_breakdown' => (object) $minutesUsedBreakdown,
            'days_left_in_billing_cycle' => $this->calculateDaysLeftInBillingCycle()
        ];
    }

    /**
     * Transform shared storage billing data from new format to old format
     */
    private function transformSharedStorageData(array $usageItems): object
    {
        $estimatedStorageForMonth = 0;
        $estimatedPaidStorageForMonth = 0;

        foreach ($usageItems as $item) {
            // Filter for Packages storage
            if ($item->product === 'Packages' && $item->sku === 'Packages storage' && $item->unitType === 'GigabyteHours') {
                $estimatedStorageForMonth += $item->quantity;
                $estimatedPaidStorageForMonth += $item->netAmount;
            }
        }

        return (object) [
            'days_left_in_billing_cycle' => $this->calculateDaysLeftInBillingCycle(),
            'estimated_paid_storage_for_month' => $estimatedPaidStorageForMonth,
            'estimated_storage_for_month' => $estimatedStorageForMonth
        ];
    }

    /**
     * Calculate days left in current billing cycle
     * Since this info is no longer provided by the API, we calculate it
     */
    private function calculateDaysLeftInBillingCycle(): int
    {
        $currentDay = (int) date('j'); // Day of month without leading zeros
        $daysInMonth = (int) date('t'); // Number of days in current month

        return max(0, $daysInMonth - $currentDay);
    }

    private function getBilling(string $type, array $items): array
    {
        $data = array();

        foreach ($items as $item) {
            $contentActions = $this->getBillingInternal($type, $item, "actions");
            $contentStorage = $this->getBillingInternal($type, $item, "shared-storage");

            $used = $contentActions->total_minutes_used;
            $included = $contentActions->included_minutes;
            $percentage = ($used * 100) / $included;
            $days = $contentStorage->days_left_in_billing_cycle;
            $date = date("d/m/Y", strtotime("+{$days} days"));

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
            $accountLink = "<a href='https://github.com/{$linkPrefix}settings/billing/summary' target='_blank' rel='noopener noreferrer'><img alt='login' src='https://img.shields.io/badge/" . str_replace("-", "--", $item) . "-black?style=social&logo=github' /></a>";
            $actionsImage = "<img alt='Actions used' src='https://img.shields.io/badge/" . number_format($percentage, 2, '.', '') . "%25-{$used}%2F{$included}_minutes-{$colorActions}?style=for-the-badge&labelColor=black' />";
            $daysImage = "<img alt='Actions used' src='https://img.shields.io/badge/{$days}_days-{$date}-{$colorDays}?style=for-the-badge&labelColor=black' />";

            $data[$item] = array($accountLink, $actionsImage, $daysImage);
        }

        return $data;
    }

    public function getAccountUsage(): array
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

    public function getApiUsage(): array
    {
        $dataCore = array();
        $data = array();
        $data[] = ["Resource", "Usage", "Reset"];

        $response = $this->requestInternal(self::GITHUB_API_URL . "rate_limit");

        if ($response->getStatusCode() !== 200) {
            return $data;
        }

        $body = $response->getBody();
        $json = json_decode($body);

        foreach ($json->resources as $resource => $item) {
            if ($resource === "core") {
                $dataCore = $item;
            }

            $resource = str_replace("_", " ", ucfirst($resource));
            $used = $item->used;
            $limit = $item->limit;
            $percentage = ($used * 100) / $limit;
            $minutes = sprintf('%02d', max(0, floor(($item->reset - time()) / 60)));

            $colorUsage = "green";
            if ($percentage >= 90) {
                $colorUsage = "red";
            } elseif ($percentage >= 75) {
                $colorUsage = "orange";
            } elseif ($percentage >= 50) {
                $colorUsage = "yellow";
            }

            $colorMinutes = "green";
            if ($minutes >= 45) {
                $colorMinutes = "red";
            } elseif ($minutes >= 30) {
                $colorMinutes = "orange";
            } elseif ($minutes >= 15) {
                $colorMinutes = "yellow";
            }

            $resourceUsage = "<img alt='Resource used' src='https://img.shields.io/badge/" . sprintf('%05.2f', $percentage) . "%25-" . $used . "%2F" . $limit . "_requests-" . $colorUsage . "?style=for-the-badge&labelColor=black' />";
            $resourceReset = "<img alt='Resource reset' src='https://img.shields.io/badge/" . $minutes . "-" . date(self::DATE_TIME_FORMAT, $item->reset) . "-" . $colorMinutes . "?style=for-the-badge&labelColor=black' />";

            $data[] = [$resource, $resourceUsage, $resourceReset];
        }

        $_SESSION["api_usage"] = $json->resources;

        return ["core" => $dataCore, "data" => $data];
    }
}

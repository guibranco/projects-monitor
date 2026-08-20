<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

use DateTimeImmutable;
use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\RequestException;
use GuiBranco\Pancake\ShieldsIo;

/**
 * GitHub Actions included-usage (minutes/storage) for the accounts listed in
 * github-billing.json. Each account is its own billing pool — this class
 * never sums minutes or allowances across accounts; the only cross-account
 * rollup is the highest utilisation percentage, computed by the caller.
 *
 * Uses the enhanced-billing-platform usage endpoints
 * (/settings/billing/usage/summary and /settings/billing/usage) — the legacy
 * /settings/billing/actions endpoint is dead on accounts migrated to the new
 * platform.
 */
class GitHubActionsUsage
{
    private const API_URL = "https://api.github.com/";
    private const API_VERSION = "2026-03-10";
    private const CACHE_TTL_SECONDS = 1800;
    private const TOP_REPOS_LIMIT = 5;

    private Request $request;

    private string $defaultToken;

    private GitHubBillingConfig $config;

    public function __construct(?GitHubBillingConfig $config = null)
    {
        $appConfig = new Configuration();
        $appConfig->init();

        if (!file_exists(__DIR__ . "/../secrets/gitHub.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: gitHub.secrets.php");
        }

        global $gitHubToken;
        require_once __DIR__ . "/../secrets/gitHub.secrets.php";
        $this->defaultToken = $gitHubToken;

        $this->request = new Request();
        $this->config = $config ?? new GitHubBillingConfig();
    }

    /**
     * One entry per configured account. A single account failing (403/404/5xx/
     * timeout) never blanks the others — it degrades independently.
     *
     * @return array<int, array>
     */
    public function getAllAccountsUsage(): array
    {
        $results = [];

        foreach ($this->config->getAccounts() as $account) {
            $results[] = $this->getAccountUsageSafely($account);
        }

        return $results;
    }

    /**
     * One row per account (header row first), rendered with the same
     * shields.io-badge-in-table-cell convention used by the rest of the
     * dashboard (see accounts_usage / api_usage tables).
     */
    public function getAccountsUsageTable(): array
    {
        $shields = new ShieldsIo();
        $rows = [["Account", "Plan", "Minutes", "Storage", "Reset", "Top repositories (this cycle)"]];

        foreach ($this->getAllAccountsUsage() as $usage) {
            $rows[] = $this->buildRow($shields, $usage);
        }

        return $rows;
    }

    /**
     * The only valid cross-account number: the highest utilisation percentage
     * among healthy accounts, for an at-a-glance warning. Never a summed total.
     */
    public function getHighestUtilizationPercentage(array $accountsUsage): ?float
    {
        $percentages = [];

        foreach ($accountsUsage as $usage) {
            if ($usage["status"] !== "ok") {
                continue;
            }

            $percentages[] = max($usage["minutes"]["percentage"], $usage["storage"]["percentage"]);
        }

        return $percentages === [] ? null : max($percentages);
    }

    private function buildRow(ShieldsIo $shields, array $usage): array
    {
        $accountLink = $this->accountLink($shields, $usage["account"], $usage["accountType"]);

        if ($usage["status"] !== "ok") {
            $url = $shields->generateBadgeUrl("⚠️", "Unavailable", "lightgrey", "for-the-badge", "black", null);
            $img = "<img alt='Unavailable' src='{$url}' title='" . htmlspecialchars((string) $usage["reason"], ENT_QUOTES) . "' />";

            return [$accountLink, $usage["planType"], $img, $img, "-", "-"];
        }

        $minutesImg = $this->usageBadge(
            $shields,
            $usage["minutes"]["percentage"],
            number_format($usage["minutes"]["weightedUsed"], 0) . "/" . $usage["minutes"]["included"] . "_min"
        );
        $storageImg = $this->usageBadge(
            $shields,
            $usage["storage"]["percentage"],
            number_format($usage["storage"]["usedGb"], 2) . "/" . number_format($usage["storage"]["includedGb"], 2) . "_GB"
        );
        $resetText = "{$usage["cycle"]["daysUntilReset"]} days ({$usage["cycle"]["label"]})";
        $topRepos = $usage["topRepositories"] === []
            ? "-"
            : implode(", ", array_map(
                fn ($r) => "{$r["repository"]} (" . number_format($r["minutes"], 0) . "m)",
                $usage["topRepositories"]
            ));

        return [$accountLink, $usage["planType"], $minutesImg, $storageImg, $resetText, $topRepos];
    }

    private function usageBadge(ShieldsIo $shields, float $percentage, string $detail): string
    {
        $color = "brightgreen";
        if ($percentage > 90) {
            $color = "red";
        } elseif ($percentage >= 75) {
            $color = "orange";
        }

        $url = $shields->generateBadgeUrl(number_format($percentage, 1) . "%", $detail, $color, "for-the-badge", "black", null);
        return "<img alt='Usage' src='{$url}' />";
    }

    private function accountLink(ShieldsIo $shields, string $account, string $accountType): string
    {
        $prefix = $accountType === "org" ? "organizations/{$account}/" : "";
        $url = "https://github.com/{$prefix}settings/billing/summary";
        $badge = $shields->generateBadgeUrl($accountType, $account, "black", "social", "white", "github");

        return "<a href='{$url}' target='_blank' rel='noopener noreferrer'><img alt='{$account}' src='{$badge}' /></a>";
    }

    private function getAccountUsageSafely(array $account): array
    {
        try {
            return $this->getAccountUsage($account);
        } catch (\Throwable $e) {
            LogStream::warning("GitHub Actions usage unavailable for account", [
                "account" => $account["account"],
                "reason" => $e->getMessage(),
            ], "github-billing");

            return $this->degradedResult($account, $e->getMessage());
        }
    }

    private function getAccountUsage(array $account): array
    {
        $now = new DateTimeImmutable("now");
        $cycle = GitHubBillingCycle::resolve($account["cycleResetDay"], $now);
        $months = GitHubBillingCycle::monthsInWindow($cycle["start"], $cycle["end"]);
        $accountName = $account["account"];

        $fetched = $this->fetchUsageData($account, $cycle, $months);
        $usageItems = $fetched["usageItems"];
        $windowedRows = $fetched["usageRows"];

        $minutes = GitHubActionsUsageCalculator::minutes($usageItems);
        $storageGb = GitHubActionsUsageCalculator::storageGb($usageItems);
        $inferredMinutes = GitHubActionsUsageCalculator::inferIncludedMinutes($usageItems);

        if (GitHubActionsUsageCalculator::allowanceDiverges((float) $account["minutes"], $inferredMinutes)) {
            LogStream::warning("GitHub Actions included-minutes allowance diverges from github-billing.json", [
                "account" => $accountName,
                "configuredMinutes" => $account["minutes"],
                "inferredMinutes" => $inferredMinutes,
            ], "github-billing");
        }

        $includedStorageGb = $account["storageMb"] / 1024;

        return [
            "account" => $accountName,
            "accountType" => $account["accountType"],
            "planType" => $account["planType"],
            "status" => "ok",
            "reason" => null,
            "minutes" => [
                "rawUsed" => $minutes["raw"],
                "weightedUsed" => $minutes["weighted"],
                "included" => $account["minutes"],
                "percentage" => $this->percentage($minutes["weighted"], (float) $account["minutes"]),
            ],
            "storage" => [
                "usedGb" => $storageGb,
                "includedGb" => $includedStorageGb,
                "percentage" => $this->percentage($storageGb, $includedStorageGb),
            ],
            "cycle" => [
                "label" => $cycle["label"],
                "daysUntilReset" => $cycle["daysUntilReset"],
                "resetDate" => $cycle["end"]->format("Y-m-d"),
            ],
            "topRepositories" => GitHubActionsUsageCalculator::topRepositoriesByMinutes($windowedRows, self::TOP_REPOS_LIMIT),
        ];
    }

    /**
     * Network/cache seam: resolves the token, fetches (or serves cached)
     * usageItems for the window plus the raw per-repo rows, normalized to a
     * common shape. Isolated as its own method — and left mockable in tests —
     * so calculation/degradation logic can be exercised without live HTTP.
     *
     * @param array{start: DateTimeImmutable, end: DateTimeImmutable} $cycle
     * @param array<int, array{0: int, 1: int}> $months
     * @return array{usageItems: array<int, object>, usageRows: array<int, object>}
     */
    protected function fetchUsageData(array $account, array $cycle, array $months): array
    {
        $token = $this->resolveToken($account);
        $headers = $this->buildHeaders($token);
        $pathSegment = $account["accountType"] === "org" ? "organizations" : "users";
        $accountName = $account["account"];

        $windowedRows = [];
        foreach ($months as [$year, $month]) {
            foreach ($this->fetchUsageRows($pathSegment, $accountName, $headers, $year, $month) as $row) {
                if (isset($row->date) && GitHubBillingCycle::isDateInWindow($row->date, $cycle["start"], $cycle["end"])) {
                    $windowedRows[] = $row;
                }
            }
        }

        if (count($months) === 1) {
            [$year, $month] = $months[0];
            $usageItems = $this->fetchSummaryUsageItems($pathSegment, $accountName, $headers, $year, $month);
        } else {
            $usageItems = array_map([$this, "normalizeRow"], $windowedRows);
        }

        return ["usageItems" => $usageItems, "usageRows" => $windowedRows];
    }

    private function degradedResult(array $account, string $reason): array
    {
        $isAuthFailure = str_contains($reason, "403") || stripos($reason, "token") !== false;
        $hint = $isAuthFailure
            ? " Verify the token has 'admin:org' + user/plan scope (classic PAT) or 'Plan' (user)/'Administration' (org) read access (fine-grained PAT) for account '{$account["account"]}'."
            : "";

        return [
            "account" => $account["account"],
            "accountType" => $account["accountType"],
            "planType" => $account["planType"],
            "status" => "unavailable",
            "reason" => $reason . $hint,
            "minutes" => null,
            "storage" => null,
            "cycle" => null,
            "topRepositories" => [],
        ];
    }

    private function resolveToken(array $account): string
    {
        $secretName = $account["tokenSecret"];

        if ($secretName === null) {
            return $this->defaultToken;
        }

        if (empty($GLOBALS[$secretName])) {
            throw new GitHubActionsUsageException(
                "No token configured for account '{$account["account"]}': global \${$secretName} " .
                "is not defined in gitHub.secrets.php."
            );
        }

        return $GLOBALS[$secretName];
    }

    private function buildHeaders(string $token): array
    {
        return [
            "Authorization: Bearer {$token}",
            "Accept: application/vnd.github+json",
            "X-GitHub-Api-Version: " . self::API_VERSION,
            constant("USER_AGENT"),
        ];
    }

    private function fetchSummaryUsageItems(string $pathSegment, string $account, array $headers, int $year, int $month): array
    {
        $url = self::API_URL . "{$pathSegment}/{$account}/settings/billing/usage/summary?year={$year}&month={$month}";
        $cacheKey = "github_billing_summary_{$account}_{$year}_{$month}";

        return $this->extractUsageItems($this->fetchCachedJson($cacheKey, $url, $headers));
    }

    private function fetchUsageRows(string $pathSegment, string $account, array $headers, int $year, int $month): array
    {
        $url = self::API_URL . "{$pathSegment}/{$account}/settings/billing/usage?year={$year}&month={$month}";
        $cacheKey = "github_billing_usage_{$account}_{$year}_{$month}";

        return $this->extractUsageItems($this->fetchCachedJson($cacheKey, $url, $headers));
    }

    /**
     * The summary/usage response shape is in public preview and subject to
     * change. Accept either {"usageItems": [...]} or a bare list, and degrade
     * to an empty list rather than fatal on schema drift.
     */
    private function extractUsageItems($decoded): array
    {
        if (is_object($decoded) && isset($decoded->usageItems) && is_array($decoded->usageItems)) {
            return $decoded->usageItems;
        }

        if (is_array($decoded)) {
            return $decoded;
        }

        return [];
    }

    private function normalizeRow(object $row): object
    {
        $normalized = clone $row;

        if (!isset($normalized->grossQuantity) && isset($normalized->quantity)) {
            $normalized->grossQuantity = $normalized->quantity;
        }

        return $normalized;
    }

    private function fetchCachedJson(string $cacheKey, string $url, array $headers)
    {
        $cachePath = "cache/{$cacheKey}.json";
        $cacheExists = file_exists($cachePath);

        if ($cacheExists && filemtime($cachePath) > time() - self::CACHE_TTL_SECONDS) {
            return json_decode(file_get_contents($cachePath));
        }

        try {
            $response = $this->request->get($url, $headers);
            $response->ensureSuccessStatus();
            $body = $response->getBody();
            file_put_contents($cachePath, $body);

            return json_decode($body);
        } catch (RequestException $e) {
            if ($cacheExists) {
                LogStream::warning("GitHub billing request failed, serving stale cache", [
                    "url" => $url,
                    "reason" => $e->getMessage(),
                ], "github-billing");

                return json_decode(file_get_contents($cachePath));
            }

            throw $e;
        }
    }

    private function percentage(float $used, float $included): float
    {
        if ($included <= 0) {
            return 0.0;
        }

        return round(($used / $included) * 100, 2);
    }
}

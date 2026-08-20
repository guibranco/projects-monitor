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

    /** Shared with the config-load-failure fallback in api/v1/github.php so both stay in sync. */
    public const TABLE_HEADER = ["Account", "Plan", "Minutes", "Storage", "Reset", "Top repositories (this cycle)"];

    private Request $request;

    private string $defaultToken;

    /** @var array<string, mixed> Every variable gitHub.secrets.php defines, keyed by name. */
    private array $tokens;

    private GitHubBillingConfig $config;

    public function __construct(?GitHubBillingConfig $config = null)
    {
        $appConfig = new Configuration();
        $appConfig->init();

        $this->tokens = $this->loadTokens();

        if (empty($this->tokens["gitHubToken"])) {
            throw new SecretsFileNotFoundException("gitHub.secrets.php did not define \$gitHubToken");
        }

        $this->defaultToken = $this->tokens["gitHubToken"];

        $this->request = new Request();
        $this->config = $config ?? new GitHubBillingConfig();
    }

    /**
     * Loads gitHub.secrets.php and captures every variable it defines — not
     * just $gitHubToken. A per-account tokenSecret in github-billing.json can
     * name any other variable the same file defines (e.g. $gitHubTokenApiBr);
     * `global $gitHubToken;` only promotes the one variable it names into
     * $GLOBALS, so resolveToken() couldn't see the others. get_defined_vars()
     * in a fresh method scope, called right after a plain `require` (not
     * `require_once`, so this stays correct across repeated construction
     * within one long-lived process), captures exactly what the file defined.
     */
    private function loadTokens(): array
    {
        if (!file_exists(__DIR__ . "/../secrets/gitHub.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: gitHub.secrets.php");
        }

        require __DIR__ . "/../secrets/gitHub.secrets.php";

        return get_defined_vars();
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
        $rows = [self::TABLE_HEADER];

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
                fn ($r) => htmlspecialchars($r["repository"], ENT_QUOTES) . " (" . number_format($r["minutes"], 0) . "m)",
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
            // The window is exactly one calendar month, so the summary endpoint's
            // full-month totals are already correct (and match the GitHub UI).
            [$year, $month] = $months[0];
            $usageItems = $this->fetchSummaryUsageItems($pathSegment, $accountName, $headers, $year, $month);
        } else {
            // A cycle spanning two calendar months can't use the summary endpoint
            // here — it returns whole-month totals with no way to exclude the
            // days outside the window, which would overcount. $windowedRows is
            // already date-filtered to the window, and per the API's documented
            // shape carries the same fields as summary items (product, unitType,
            // pricePerUnit, discountAmount, ...) aside from quantity/repositoryName,
            // so discountAmount stays available for inferIncludedMinutes() below.
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

        if (empty($this->tokens[$secretName])) {
            throw new GitHubActionsUsageException(
                "No token configured for account '{$account["account"]}': \${$secretName} " .
                "is not defined in gitHub.secrets.php."
            );
        }

        return $this->tokens[$secretName];
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
        $cachePath = __DIR__ . "/../cache/{$cacheKey}.json";
        $cached = $this->readCachedJson($cachePath);
        $isFresh = $cached !== null && filemtime($cachePath) > time() - self::CACHE_TTL_SECONDS;

        if ($isFresh) {
            return $cached;
        }

        try {
            $response = $this->request->get($url, $headers);
            $response->ensureSuccessStatus();
            $body = $response->getBody();
            $decoded = json_decode($body);

            if ($decoded === null) {
                throw new GitHubActionsUsageException("GitHub billing response was not valid JSON: {$url}");
            }

            $this->writeCache($cachePath, $body);

            return $decoded;
        } catch (RequestException $e) {
            if ($cached !== null) {
                LogStream::warning("GitHub billing request failed, serving stale cache", [
                    "url" => $url,
                    "reason" => $e->getMessage(),
                ], "github-billing");

                return $cached;
            }

            throw $e;
        }
    }

    /**
     * Returns the decoded cache contents, or null on any failure (missing
     * file, unreadable, corrupt/truncated JSON) — treated as a cache miss
     * rather than risking silently-wrong (empty/zero) usage data.
     */
    private function readCachedJson(string $cachePath)
    {
        if (!file_exists($cachePath)) {
            return null;
        }

        $contents = file_get_contents($cachePath);
        if ($contents === false) {
            return null;
        }

        return json_decode($contents);
    }

    /**
     * Writes via a temp file + rename so a concurrent reader never observes a
     * partially-written cache file (rename() is atomic on the same filesystem).
     */
    private function writeCache(string $cachePath, string $body): void
    {
        $dir = dirname($cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $tempPath = $cachePath . "." . uniqid("", true) . ".tmp";
        if (file_put_contents($tempPath, $body) === false) {
            return;
        }

        rename($tempPath, $cachePath);
    }

    private function percentage(float $used, float $included): float
    {
        if ($included <= 0) {
            return 0.0;
        }

        return round(($used / $included) * 100, 2);
    }
}

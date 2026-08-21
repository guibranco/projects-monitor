<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\ShieldsIo;

/**
 * Renders GitHubActionsUsage's per-account results as the header-row-first,
 * shields.io-badge-in-table-cell rows the rest of the dashboard's data tables
 * use (see accounts_usage / api_usage). Pure formatting — no HTTP, no cache,
 * no calculation — kept separate so it's fully unit-testable on its own.
 */
class GitHubActionsUsagePresenter
{
    /**
     * One row per account (header row first).
     *
     * @param array<int, array> $accountsUsage
     */
    public function toTable(array $accountsUsage): array
    {
        $shields = new ShieldsIo();
        $rows = [GitHubActionsUsage::TABLE_HEADER];

        foreach ($accountsUsage as $usage) {
            $rows[] = $this->buildRow($shields, $usage);
        }

        return $rows;
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
}

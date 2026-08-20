<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Pure calculation rules for GitHub Actions included-usage.
 *
 * The GitHub billing UI reports OS-multiplier-weighted minutes (Linux 1x,
 * Windows 2x, macOS 10x), but the API's usageItems only give raw quantity —
 * the multiplier is implicit in pricePerUnit. These functions never sum
 * across accounts; each call operates on one account's usageItems.
 */
class GitHubActionsUsageCalculator
{
    public const DEFAULT_LINUX_BASE_PRICE = 0.008;

    /**
     * @param array<int, object> $usageItems Actions usageItems for one account/window.
     * @return array{raw: float, weighted: float}
     */
    public static function minutes(array $usageItems, float $linuxBasePrice = self::DEFAULT_LINUX_BASE_PRICE): array
    {
        $raw = 0.0;
        $weighted = 0.0;

        foreach (self::filter($usageItems, "minutes") as $item) {
            $quantity = (float) ($item->grossQuantity ?? 0);
            $raw += $quantity;
            $weighted += $quantity * self::multiplier($item, $linuxBasePrice);
        }

        return ["raw" => $raw, "weighted" => $weighted];
    }

    /**
     * @param array<int, object> $usageItems Actions usageItems for one account/window.
     */
    public static function storageGb(array $usageItems): float
    {
        $total = 0.0;

        foreach ($usageItems as $item) {
            if (($item->product ?? null) !== "Actions") {
                continue;
            }

            $unitType = strtolower((string) ($item->unitType ?? ""));
            if (!str_contains($unitType, "gigabyte")) {
                continue;
            }

            $total += (float) ($item->grossQuantity ?? 0);
        }

        return $total;
    }

    /**
     * Cross-checks the configured included-minutes allowance against what the
     * API's discountAmount/pricePerUnit imply per SKU. A >5% divergence is the
     * early warning that an account's plan changed and github-billing.json is
     * stale — callers should log it, not act on it.
     *
     * @param array<int, object> $usageItems
     */
    public static function inferIncludedMinutes(array $usageItems, float $linuxBasePrice = self::DEFAULT_LINUX_BASE_PRICE): ?float
    {
        $inferred = 0.0;
        $hasSignal = false;

        foreach (self::filter($usageItems, "minutes") as $item) {
            $pricePerUnit = (float) ($item->pricePerUnit ?? 0);
            $discountAmount = (float) ($item->discountAmount ?? 0);

            if ($pricePerUnit <= 0 || $discountAmount <= 0) {
                continue;
            }

            $hasSignal = true;
            $inferred += ($discountAmount / $pricePerUnit) * self::multiplier($item, $linuxBasePrice);
        }

        return $hasSignal ? $inferred : null;
    }

    /**
     * True when the inferred allowance diverges from the resolved (JSON)
     * allowance by more than the given tolerance.
     */
    public static function allowanceDiverges(float $resolvedMinutes, ?float $inferredMinutes, float $tolerance = 0.05): bool
    {
        if ($inferredMinutes === null || $resolvedMinutes <= 0) {
            return false;
        }

        return abs($inferredMinutes - $resolvedMinutes) / $resolvedMinutes > $tolerance;
    }

    /**
     * Top repositories by weighted minutes, from the non-summary /usage
     * endpoint rows (which carry repositoryName; the summary endpoint doesn't).
     *
     * @param array<int, object> $usageRows Rows with product, unitType, quantity, pricePerUnit, repositoryName.
     * @return array<int, array{repository: string, minutes: float}>
     */
    public static function topRepositoriesByMinutes(array $usageRows, int $limit = 5, float $linuxBasePrice = self::DEFAULT_LINUX_BASE_PRICE): array
    {
        $totals = [];

        foreach ($usageRows as $row) {
            if (($row->product ?? null) !== "Actions" || ($row->unitType ?? null) !== "minutes") {
                continue;
            }

            $repository = $row->repositoryName ?? "unknown";
            $quantity = (float) ($row->quantity ?? 0);
            $multiplier = self::multiplier($row, $linuxBasePrice);

            $totals[$repository] = ($totals[$repository] ?? 0.0) + ($quantity * $multiplier);
        }

        arsort($totals);

        $result = [];
        foreach (array_slice($totals, 0, $limit, true) as $repository => $minutes) {
            $result[] = ["repository" => $repository, "minutes" => $minutes];
        }

        return $result;
    }

    /**
     * @param array<int, object> $usageItems
     * @return array<int, object>
     */
    private static function filter(array $usageItems, string $unitType): array
    {
        return array_values(array_filter(
            $usageItems,
            static fn ($item) => ($item->product ?? null) === "Actions" && ($item->unitType ?? null) === $unitType
        ));
    }

    private static function multiplier(object $item, float $linuxBasePrice): float
    {
        $pricePerUnit = (float) ($item->pricePerUnit ?? 0);

        if ($pricePerUnit <= 0 || $linuxBasePrice <= 0) {
            return 1.0;
        }

        return $pricePerUnit / $linuxBasePrice;
    }
}

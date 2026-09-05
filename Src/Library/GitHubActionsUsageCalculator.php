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
    /**
     * Fallback only, used when a batch carries no Linux-SKU line item to
     * calibrate against (see resolveLinuxBasePrice()). GitHub's real Linux
     * per-minute price has already been observed at $0.006 (not the $0.008
     * originally assumed here) — pricing drifts, so minutes()/
     * inferIncludedMinutes() prefer deriving it live from the data itself.
     */
    public const DEFAULT_LINUX_BASE_PRICE = 0.006;

    /**
     * @param array<int, object> $usageItems Actions usageItems for one account/window.
     * @return array{raw: float, weighted: float}
     */
    public static function minutes(array $usageItems, float $linuxBasePrice = self::DEFAULT_LINUX_BASE_PRICE): array
    {
        $linuxBasePrice = self::resolveLinuxBasePrice($usageItems, $linuxBasePrice);
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
     * Prefers the batch's own Linux-SKU pricePerUnit over the hardcoded
     * fallback — GitHub's per-minute prices change over time (already caught
     * one drift: $0.008 assumed vs. $0.006 observed), and every usageItems
     * batch that includes any Linux usage already tells us today's real rate.
     */
    private static function resolveLinuxBasePrice(array $usageItems, float $fallback): float
    {
        foreach ($usageItems as $item) {
            if (!self::isActionsProduct($item) || strtolower((string) ($item->unitType ?? "")) !== "minutes") {
                continue;
            }

            if (!str_contains(strtolower((string) ($item->sku ?? "")), "linux")) {
                continue;
            }

            $pricePerUnit = (float) ($item->pricePerUnit ?? 0);
            if ($pricePerUnit > 0) {
                return $pricePerUnit;
            }
        }

        return $fallback;
    }

    /**
     * GitHub bills Actions storage in GigabyteHours — GB held × hours held,
     * an accumulated cloud-storage metric, not a point-in-time GB snapshot
     * (confirmed against GitHub's own billing-automation docs; there is no
     * multiplier here the way there is for minutes, but treating the raw sum
     * as "GB used" overstates it by roughly the hour count in the cycle so
     * far). Dividing by $hoursElapsed approximates the average GB held over
     * the window, comparable to the snapshot figure the billing UI shows.
     *
     * @param array<int, object> $usageItems Actions usageItems for one account/window.
     * @param float $hoursElapsed Hours since the account's cycle started; must be > 0.
     */
    public static function storageGb(array $usageItems, float $hoursElapsed): float
    {
        $total = 0.0;

        foreach ($usageItems as $item) {
            if (!self::isActionsProduct($item)) {
                continue;
            }

            $unitType = strtolower((string) ($item->unitType ?? ""));
            if (!str_contains($unitType, "gigabyte")) {
                continue;
            }

            $total += (float) ($item->grossQuantity ?? 0);
        }

        if ($hoursElapsed <= 0) {
            return $total;
        }

        $total /= $hoursElapsed;

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
        $linuxBasePrice = self::resolveLinuxBasePrice($usageItems, $linuxBasePrice);
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
        $linuxBasePrice = self::resolveLinuxBasePrice($usageRows, $linuxBasePrice);
        $totals = [];

        foreach ($usageRows as $row) {
            $unitType = strtolower((string) ($row->unitType ?? ""));
            if (!self::isActionsProduct($row) || $unitType !== "minutes") {
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
            // PHP casts numeric-looking array keys to int (e.g. a repo literally
            // named "123") — cast back so the serialized field stays a string.
            $result[] = ["repository" => (string) $repository, "minutes" => $minutes];
        }

        return $result;
    }

    /**
     * @param array<int, object> $usageItems
     * @return array<int, object>
     */
    private static function filter(array $usageItems, string $unitType): array
    {
        $unitType = strtolower($unitType);

        return array_values(array_filter(
            $usageItems,
            static fn ($item) => self::isActionsProduct($item)
                && strtolower((string) ($item->unitType ?? "")) === $unitType
        ));
    }

    /**
     * Case-insensitive product check — the summary and non-summary usage
     * endpoints have been observed to disagree on casing ("Actions" vs.
     * "actions") for what is otherwise the same field, a public-preview API
     * inconsistency rather than a meaningful distinction.
     */
    private static function isActionsProduct(object $item): bool
    {
        return strtolower((string) ($item->product ?? "")) === "actions";
    }

    /**
     * OS-price multiplier relative to Linux (1x), derived from pricePerUnit.
     *
     * @return float 1.0 when pricePerUnit is missing/non-positive (raw-sum fallback).
     */
    private static function multiplier(object $item, float $linuxBasePrice): float
    {
        $pricePerUnit = (float) ($item->pricePerUnit ?? 0);

        if ($pricePerUnit <= 0 || $linuxBasePrice <= 0) {
            return 1.0;
        }

        return $pricePerUnit / $linuxBasePrice;
    }
}

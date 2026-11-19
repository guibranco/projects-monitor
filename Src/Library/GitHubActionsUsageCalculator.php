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
     * checkAllowance() prefer deriving it live from the data itself.
     */
    public const DEFAULT_LINUX_BASE_PRICE = 0.006;

    /** How far the usage may stray from the configured allowance before checkAllowance() reports it. */
    public const ALLOWANCE_TOLERANCE = 0.05;

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
     * True when any Actions minutes row carries a discount, the only case in
     * which checkAllowance() has anything to say, so callers can skip the
     * repository-visibility lookup entirely when this is false.
     *
     * @param array<int, object> $usageRows Non-summary usage rows of one account.
     *
     * @return boolean
     */
    public function hasDiscountedMinutes(array $usageRows): bool
    {
        foreach (self::filter($usageRows, "minutes") as $row) {
            if ((float) ($row->discountAmount ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cross-checks the configured included-minutes allowance against the
     * per-repository usage rows. discountAmount alone can't be read as "the
     * allowance": public-repository minutes are free and arrive as a 100%
     * discount too, and private-repository discount is the allowance used so
     * far, which only equals the allowance once it is exhausted. So only rows
     * of non-public repositories count, and the result is:
     *
     * - "higher": private discounted minutes exceed the configured allowance,
     *   so the real allowance must be larger and github-billing.json is stale;
     * - "lower": the account is paying for minutes (netAmount above zero)
     *   while the private discounted minutes are still below the configured
     *   allowance, so the real allowance must be smaller.
     *
     * Null when nothing contradicts the configured allowance, including the
     * normal case of being part-way through it.
     *
     * @param array<int, object>  $usageRows          Non-summary rows (repositoryName, discountAmount, netAmount).
     * @param array<string, true> $publicRepositories Set built by publicRepositorySet().
     * @param float               $configuredMinutes  Allowance from github-billing.json.
     *
     * @return array{direction: string, privateIncludedMinutes: float, overage: bool}|null
     */
    public function checkAllowance(array $usageRows, array $publicRepositories, float $configuredMinutes): ?array
    {
        if ($configuredMinutes <= 0) {
            return null;
        }

        $totals  = $this->privateAllowanceTotals($usageRows, $publicRepositories);
        $covered = $totals["privateIncludedMinutes"];
        if ($covered > ($configuredMinutes * (1 + self::ALLOWANCE_TOLERANCE))) {
            $totals["direction"] = "higher";
        }

        if ($totals["overage"] === true && $covered < ($configuredMinutes * (1 - self::ALLOWANCE_TOLERANCE))) {
            $totals["direction"] = "lower";
        }

        if (isset($totals["direction"]) === false) {
            return null;
        }

        return $totals;
    }

    /**
     * Linux-equivalent minutes covered by the allowance on non-public
     * repositories, and whether any of them is paying for overage.
     *
     * @param array<int, object>  $usageRows          Non-summary usage rows of one account.
     * @param array<string, true> $publicRepositories Set built by publicRepositorySet().
     *
     * @return array{privateIncludedMinutes: float, overage: bool}
     */
    private function privateAllowanceTotals(array $usageRows, array $publicRepositories): array
    {
        $linuxBasePrice = self::resolveLinuxBasePrice($usageRows, self::DEFAULT_LINUX_BASE_PRICE);
        $totals         = [];
        $totals["privateIncludedMinutes"] = 0.0;
        $totals["overage"] = false;

        foreach (self::filter($usageRows, "minutes") as $row) {
            $repository = strtolower(trim((string) ($row->repositoryName ?? "")));
            if (isset($publicRepositories[$repository]) === true) {
                continue;
            }

            if ((float) ($row->netAmount ?? 0) > 0) {
                $totals["overage"] = true;
            }

            // Discount divided by the Linux base price gives weighted minutes, the allowance's unit.
            $totals["privateIncludedMinutes"] += ((float) ($row->discountAmount ?? 0) / $linuxBasePrice);
        }

        return $totals;
    }

    /**
     * Lower-cased lookup set of public repository names, keyed by both the
     * bare name and "owner/name": the usage endpoint has been observed
     * returning the bare name, but its documented example uses the full name.
     *
     * @param array<int, object> $repositories Items from GET /users/{u}/repos or /orgs/{o}/repos.
     *
     * @return array<string, true>
     */
    public function publicRepositorySet(array $repositories): array
    {
        $set = [];
        foreach ($repositories as $repository) {
            if (is_object($repository) === false || ($repository->private ?? false) === true) {
                continue;
            }

            if (is_string(($repository->name ?? null)) === true) {
                $set[strtolower($repository->name)] = true;
            }

            if (is_string(($repository->full_name ?? null)) === true) {
                $set[strtolower($repository->full_name)] = true;
            }
        }

        return $set;
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

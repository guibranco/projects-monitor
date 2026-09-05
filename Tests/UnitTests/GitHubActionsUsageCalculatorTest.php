<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\GitHubActionsUsageCalculator;

/** Tests GitHubActionsUsageCalculator's pure minutes/storage/allowance/top-repos math. */
class GitHubActionsUsageCalculatorTest extends TestCase
{
    /**
     * Builds a fake Actions "minutes" usageItem, overriding only the given
     * fields. Tagged as the Linux SKU by default so resolveLinuxBasePrice()
     * picks up pricePerUnit as the 1x baseline, matching how a real batch
     * self-calibrates — override "sku" explicitly for non-Linux fixtures.
     */
    private function item(array $fields): object
    {
        return (object) array_merge([
            "product" => "Actions",
            "sku" => "actions_linux",
            "unitType" => "minutes",
            "pricePerUnit" => 0.008,
            "grossQuantity" => 0,
            "discountAmount" => 0,
        ], $fields);
    }

    /** All-Linux usage sums to the same raw and weighted minutes (1x multiplier). */
    public function testMinutesLinuxOnly()
    {
        $items = [
            $this->item(["grossQuantity" => 100]),
            $this->item(["grossQuantity" => 200]),
            $this->item(["grossQuantity" => 300]),
        ];

        $result = GitHubActionsUsageCalculator::minutes($items);

        $this->assertSame(600.0, $result["raw"]);
        $this->assertSame(600.0, $result["weighted"]);
    }

    /** Windows (2x) and macOS (10x) multipliers inflate weighted minutes above the raw sum. */
    public function testMinutesMixedLinuxWindowsMacOs()
    {
        $items = [
            $this->item(["sku" => "actions_linux", "pricePerUnit" => 0.008, "grossQuantity" => 100]),
            $this->item(["sku" => "actions_windows", "pricePerUnit" => 0.016, "grossQuantity" => 50]),
            $this->item(["sku" => "actions_macos", "pricePerUnit" => 0.08, "grossQuantity" => 10]),
        ];

        $result = GitHubActionsUsageCalculator::minutes($items);

        $this->assertSame(160.0, $result["raw"]);
        // 100*1 (linux) + 50*2 (windows) + 10*10 (macos) = 300
        $this->assertSame(300.0, $result["weighted"]);
    }

    /** No usageItems yields zero for both raw and weighted minutes. */
    public function testMinutesZeroUsage()
    {
        $result = GitHubActionsUsageCalculator::minutes([]);

        $this->assertSame(0.0, $result["raw"]);
        $this->assertSame(0.0, $result["weighted"]);
    }

    /** A missing/zero pricePerUnit falls back to a 1x multiplier (raw sum) instead of erroring. */
    public function testMinutesMissingPricePerUnitFallsBackToRawSum()
    {
        $items = [
            $this->item(["pricePerUnit" => 0, "grossQuantity" => 100]),
            (object) ["product" => "Actions", "unitType" => "minutes", "grossQuantity" => 50],
        ];

        $result = GitHubActionsUsageCalculator::minutes($items);

        $this->assertSame(150.0, $result["raw"]);
        $this->assertSame(150.0, $result["weighted"]);
    }

    /** Non-Actions products and non-minutes unitTypes are excluded from the minutes sum. */
    public function testMinutesIgnoresNonActionsAndNonMinuteItems()
    {
        $items = [
            $this->item(["grossQuantity" => 100]),
            $this->item(["product" => "Packages", "grossQuantity" => 999]),
            $this->item(["unitType" => "GigabyteHours", "grossQuantity" => 999]),
        ];

        $result = GitHubActionsUsageCalculator::minutes($items);

        $this->assertSame(100.0, $result["raw"]);
    }

    /**
     * Regression: the real non-summary /settings/billing/usage endpoint
     * returns "product":"actions"/"packages" in lowercase, while the summary
     * endpoint has been observed returning "Actions" capitalized — a
     * public-preview API inconsistency, not a meaningful distinction. Every
     * product check must be case-insensitive or usage from one endpoint
     * silently zeroes out (this is what caused topRepositories to always be
     * empty despite real usage existing).
     */
    public function testMinutesMatchesLowercaseProductFromTheNonSummaryEndpoint()
    {
        $items = [
            $this->item(["product" => "actions", "grossQuantity" => 100]),
            $this->item(["product" => "packages", "grossQuantity" => 999]), // must not count
        ];

        $result = GitHubActionsUsageCalculator::minutes($items);

        $this->assertSame(100.0, $result["raw"]);
    }

    /** Only Actions items with a GB-based unitType count toward storage; minutes/other products don't. Here 1 hour elapsed = no averaging. */
    public function testStorageGbSumsActionsGigabyteItemsOnly()
    {
        $items = [
            $this->item(["unitType" => "GigabyteHours", "grossQuantity" => 3.5]),
            $this->item(["unitType" => "GigabyteHours", "grossQuantity" => 1.5]),
            $this->item(["grossQuantity" => 1000]), // minutes — must not count as storage
            $this->item(["product" => "Packages", "unitType" => "GigabyteHours", "grossQuantity" => 999]),
        ];

        $this->assertSame(5.0, GitHubActionsUsageCalculator::storageGb($items, 1.0));
    }

    /** Regression: lowercase "actions"/"packages" from the real non-summary endpoint must match too. */
    public function testStorageGbMatchesLowercaseProductFromTheNonSummaryEndpoint()
    {
        $items = [
            $this->item(["product" => "actions", "unitType" => "GigabyteHours", "grossQuantity" => 3.5]),
            $this->item(["product" => "packages", "unitType" => "GigabyteHours", "grossQuantity" => 999]), // must not count
        ];

        $this->assertSame(3.5, GitHubActionsUsageCalculator::storageGb($items, 1.0));
    }

    /**
     * GigabyteHours is GB held × hours held, an accumulated metric — dividing
     * by hoursElapsed converts it to an average-GB-held figure comparable to
     * the billing UI's point-in-time storage number.
     */
    public function testStorageGbDividesByHoursElapsedToApproximateAverageGb()
    {
        $items = [$this->item(["unitType" => "GigabyteHours", "grossQuantity" => 240.0])];

        $this->assertSame(10.0, GitHubActionsUsageCalculator::storageGb($items, 24.0));
    }

    /** hoursElapsed <= 0 (e.g. a clock edge case) falls back to the raw sum rather than dividing by zero. */
    public function testStorageGbFallsBackToRawSumWhenHoursElapsedIsNotPositive()
    {
        $items = [$this->item(["unitType" => "GigabyteHours", "grossQuantity" => 42.0])];

        $this->assertSame(42.0, GitHubActionsUsageCalculator::storageGb($items, 0.0));
    }

    /** No usageItems yields zero storage. */
    public function testStorageGbZeroUsage()
    {
        $this->assertSame(0.0, GitHubActionsUsageCalculator::storageGb([], 24.0));
    }

    /** discountAmount/pricePerUnit infers the included-minutes allowance from a single SKU. */
    public function testInferIncludedMinutesFromDiscount()
    {
        $items = [
            $this->item(["pricePerUnit" => 0.008, "discountAmount" => 8.0]),
        ];

        // discountAmount / pricePerUnit = 1000 raw units, multiplier 1x for linux
        $this->assertSame(1000.0, GitHubActionsUsageCalculator::inferIncludedMinutes($items));
    }

    /** With no discountAmount signal anywhere, inference returns null rather than 0. */
    public function testInferIncludedMinutesReturnsNullWithoutSignal()
    {
        $items = [$this->item(["discountAmount" => 0])];

        $this->assertNull(GitHubActionsUsageCalculator::inferIncludedMinutes($items));
    }

    /** allowanceDiverges() flags a >5% gap, tolerates a smaller one, and never fires with no inferred value. */
    public function testAllowanceDivergesBeyondTolerance()
    {
        $this->assertTrue(GitHubActionsUsageCalculator::allowanceDiverges(2000.0, 2200.0));
        $this->assertFalse(GitHubActionsUsageCalculator::allowanceDiverges(2000.0, 2050.0));
        $this->assertFalse(GitHubActionsUsageCalculator::allowanceDiverges(2000.0, null));
    }

    /** Repositories are ranked by weighted minutes descending, non-Actions/non-minutes rows excluded. */
    public function testTopRepositoriesByMinutesSortsAndLimits()
    {
        $rows = [
            (object) ["product" => "Actions", "unitType" => "minutes", "pricePerUnit" => 0.008, "quantity" => 50, "repositoryName" => "repo-a"],
            (object) ["product" => "Actions", "unitType" => "minutes", "pricePerUnit" => 0.008, "quantity" => 200, "repositoryName" => "repo-b"],
            (object) ["product" => "Actions", "unitType" => "minutes", "pricePerUnit" => 0.008, "quantity" => 10, "repositoryName" => "repo-a"],
            (object) ["product" => "Actions", "unitType" => "GigabyteHours", "pricePerUnit" => 0.25, "quantity" => 5, "repositoryName" => "repo-b"],
            (object) ["product" => "Packages", "unitType" => "minutes", "pricePerUnit" => 0.008, "quantity" => 999, "repositoryName" => "repo-c"],
        ];

        $top = GitHubActionsUsageCalculator::topRepositoriesByMinutes($rows, 5);

        $this->assertSame([
            ["repository" => "repo-b", "minutes" => 200.0],
            ["repository" => "repo-a", "minutes" => 60.0],
        ], $top);
    }

    /**
     * Regression: this is the exact bug that shipped — the non-summary
     * endpoint returns lowercase "product":"actions"/"packages" (confirmed
     * against a real response), but the case-sensitive "Actions" check
     * silently filtered out every row, leaving topRepositories always empty
     * despite real usage existing.
     */
    public function testTopRepositoriesByMinutesMatchesLowercaseProductFromTheNonSummaryEndpoint()
    {
        $rows = [
            (object) ["date" => "2026-09-01T05:31:07Z", "product" => "actions", "sku" => "Actions Linux", "quantity" => 98.0, "unitType" => "Minutes", "pricePerUnit" => 0.006, "repositoryName" => "projects-monitor"],
            (object) ["date" => "2026-09-01T00:00:00Z", "product" => "packages", "sku" => "Packages storage", "quantity" => 0.7, "unitType" => "GigabyteHours", "pricePerUnit" => 0.00033602, "repositoryName" => ""],
        ];

        $top = GitHubActionsUsageCalculator::topRepositoriesByMinutes($rows, 5);

        $this->assertSame([["repository" => "projects-monitor", "minutes" => 98.0]], $top);
    }

    /** More than $limit repositories are truncated to $limit results. */
    public function testTopRepositoriesByMinutesRespectsLimit()
    {
        $rows = [];
        for ($i = 0; $i < 8; $i++) {
            $rows[] = (object) [
                "product" => "Actions",
                "unitType" => "minutes",
                "pricePerUnit" => 0.008,
                "quantity" => 10 - $i,
                "repositoryName" => "repo-{$i}",
            ];
        }

        $this->assertCount(5, GitHubActionsUsageCalculator::topRepositoriesByMinutes($rows, 5));
    }
}

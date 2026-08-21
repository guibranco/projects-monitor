<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\GitHubActionsUsageCalculator;

class GitHubActionsUsageCalculatorTest extends TestCase
{
    private function item(array $fields): object
    {
        return (object) array_merge([
            "product" => "Actions",
            "unitType" => "minutes",
            "pricePerUnit" => 0.008,
            "grossQuantity" => 0,
            "discountAmount" => 0,
        ], $fields);
    }

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

    public function testMinutesZeroUsage()
    {
        $result = GitHubActionsUsageCalculator::minutes([]);

        $this->assertSame(0.0, $result["raw"]);
        $this->assertSame(0.0, $result["weighted"]);
    }

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

    public function testStorageGbSumsActionsGigabyteItemsOnly()
    {
        $items = [
            $this->item(["unitType" => "GigabyteHours", "grossQuantity" => 3.5]),
            $this->item(["unitType" => "GigabyteHours", "grossQuantity" => 1.5]),
            $this->item(["grossQuantity" => 1000]), // minutes — must not count as storage
            $this->item(["product" => "Packages", "unitType" => "GigabyteHours", "grossQuantity" => 999]),
        ];

        $this->assertSame(5.0, GitHubActionsUsageCalculator::storageGb($items));
    }

    public function testStorageGbZeroUsage()
    {
        $this->assertSame(0.0, GitHubActionsUsageCalculator::storageGb([]));
    }

    public function testInferIncludedMinutesFromDiscount()
    {
        $items = [
            $this->item(["pricePerUnit" => 0.008, "discountAmount" => 8.0]),
        ];

        // discountAmount / pricePerUnit = 1000 raw units, multiplier 1x for linux
        $this->assertSame(1000.0, GitHubActionsUsageCalculator::inferIncludedMinutes($items));
    }

    public function testInferIncludedMinutesReturnsNullWithoutSignal()
    {
        $items = [$this->item(["discountAmount" => 0])];

        $this->assertNull(GitHubActionsUsageCalculator::inferIncludedMinutes($items));
    }

    public function testAllowanceDivergesBeyondTolerance()
    {
        $this->assertTrue(GitHubActionsUsageCalculator::allowanceDiverges(2000.0, 2200.0));
        $this->assertFalse(GitHubActionsUsageCalculator::allowanceDiverges(2000.0, 2050.0));
        $this->assertFalse(GitHubActionsUsageCalculator::allowanceDiverges(2000.0, null));
    }

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

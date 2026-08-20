<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\GitHubActionsUsage;
use GuiBranco\ProjectsMonitor\Library\GitHubBillingConfig;

class GitHubActionsUsageTest extends TestCase
{
    private array $writtenFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->writtenFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->writtenFiles = [];
    }

    private function writeFixture(array $data): string
    {
        $path = sys_get_temp_dir() . "/github-billing-usage-" . uniqid("", true) . ".json";
        file_put_contents($path, json_encode($data));
        $this->writtenFiles[] = $path;
        return $path;
    }

    private function buildConfig(array $accounts): GitHubBillingConfig
    {
        $data = [
            "version" => 1,
            "plans" => [
                "free" => ["accountTypes" => ["user", "org"], "minutes" => 2000, "storageMb" => 500],
            ],
            "accounts" => $accounts,
        ];

        return new GitHubBillingConfig(
            $this->writeFixture($data),
            __DIR__ . "/../../Src/Library/github-billing.schema.json"
        );
    }

    private function accountEntry(string $name, string $type = "org"): array
    {
        return [
            "account" => $name,
            "accountType" => $type,
            "planType" => "free",
            "cycleResetDay" => null,
            "overrides" => [],
        ];
    }

    private function usageItem(float $grossQuantity): object
    {
        return (object) [
            "product" => "Actions",
            "unitType" => "minutes",
            "pricePerUnit" => 0.008,
            "grossQuantity" => $grossQuantity,
            "discountAmount" => 0,
        ];
    }

    /**
     * Builds a GitHubActionsUsage instance with a real, fixture-backed config
     * but the network/cache seam (fetchUsageData) replaced by a mock — the
     * same disableOriginalConstructor()+onlyMethods() pattern AppVeyorTest
     * uses to keep provider tests off the network.
     */
    private function mockUsage(GitHubBillingConfig $config)
    {
        $usage = $this->getMockBuilder(GitHubActionsUsage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(["fetchUsageData"])
            ->getMock();

        $property = new ReflectionProperty(GitHubActionsUsage::class, "config");
        $property->setAccessible(true);
        $property->setValue($usage, $config);

        return $usage;
    }

    public function testOneAccountFailureDegradesOnlyThatAccount()
    {
        $config = $this->buildConfig([
            $this->accountEntry("accountA"),
            $this->accountEntry("accountB"),
            $this->accountEntry("accountC"),
        ]);

        $usage = $this->mockUsage($config);
        $usage->method("fetchUsageData")->willReturnCallback(function (array $account) {
            if ($account["account"] === "accountB") {
                throw new \RuntimeException("403 Forbidden");
            }

            return ["usageItems" => [$this->usageItem(100)], "usageRows" => []];
        });

        $byName = [];
        foreach ($usage->getAllAccountsUsage() as $result) {
            $byName[$result["account"]] = $result;
        }

        $this->assertSame("ok", $byName["accountA"]["status"]);
        $this->assertNotNull($byName["accountA"]["minutes"]);

        $this->assertSame("unavailable", $byName["accountB"]["status"]);
        $this->assertStringContainsString("403 Forbidden", $byName["accountB"]["reason"]);
        $this->assertNull($byName["accountB"]["minutes"]);

        $this->assertSame("ok", $byName["accountC"]["status"]);
        $this->assertNotNull($byName["accountC"]["minutes"]);
    }

    public function testResultsAreNeverSummedAcrossAccounts()
    {
        $config = $this->buildConfig([
            $this->accountEntry("accountA"),
            $this->accountEntry("accountB"),
        ]);

        $usage = $this->mockUsage($config);
        $usage->method("fetchUsageData")->willReturnCallback(function (array $account) {
            $quantity = $account["account"] === "accountA" ? 500 : 1500;
            return ["usageItems" => [$this->usageItem($quantity)], "usageRows" => []];
        });

        $byName = [];
        foreach ($usage->getAllAccountsUsage() as $result) {
            $byName[$result["account"]] = $result;
        }

        $this->assertSame(500.0, $byName["accountA"]["minutes"]["weightedUsed"]);
        $this->assertSame(1500.0, $byName["accountB"]["minutes"]["weightedUsed"]);

        // Each account keeps its own included allowance — never a merged/summed total.
        $this->assertSame(2000, $byName["accountA"]["minutes"]["included"]);
        $this->assertSame(2000, $byName["accountB"]["minutes"]["included"]);
        $this->assertSame(25.0, $byName["accountA"]["minutes"]["percentage"]);
        $this->assertSame(75.0, $byName["accountB"]["minutes"]["percentage"]);
    }

    public function testHighestUtilizationIgnoresUnavailableAccounts()
    {
        $config = $this->buildConfig([
            $this->accountEntry("accountA"),
            $this->accountEntry("accountB"),
        ]);

        $usage = $this->mockUsage($config);
        $usage->method("fetchUsageData")->willReturnCallback(function (array $account) {
            if ($account["account"] === "accountB") {
                throw new \RuntimeException("timeout");
            }

            return ["usageItems" => [$this->usageItem(1900)], "usageRows" => []];
        });

        $results = $usage->getAllAccountsUsage();

        // accountB is unavailable and must not pull the badge down (or up) — only accountA counts.
        $this->assertSame(95.0, $usage->getHighestUtilizationPercentage($results));
    }

    public function testHighestUtilizationIsNullWhenAllAccountsUnavailable()
    {
        $config = $this->buildConfig([$this->accountEntry("accountA")]);

        $usage = $this->mockUsage($config);
        $usage->method("fetchUsageData")->willThrowException(new \RuntimeException("503"));

        $results = $usage->getAllAccountsUsage();

        $this->assertNull($usage->getHighestUtilizationPercentage($results));
    }

    /**
     * accountType must only drive the URL template (users/organizations) and
     * is never re-derived from the response body — the same extractUsageItems
     * parsing path handles both a user's {"user": "...", "usageItems": [...]}
     * response and an org's {"organization": "...", "usageItems": [...]} one.
     */
    public function testExtractUsageItemsIgnoresTheAccountEchoKey()
    {
        $usage = $this->getMockBuilder(GitHubActionsUsage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = new ReflectionMethod(GitHubActionsUsage::class, "extractUsageItems");
        $method->setAccessible(true);

        $orgResponse = json_decode('{"timePeriod":{"year":2026},"organization":"ApiBR","usageItems":[{"product":"Actions"}]}');
        $userResponse = json_decode('{"timePeriod":{"year":2026},"user":"guibranco","usageItems":[{"product":"Actions"}]}');
        $malformedResponse = json_decode('{"timePeriod":{"year":2026},"organization":"ApiBR"}');
        $bareListResponse = json_decode('[{"product":"Actions"}]');

        $this->assertCount(1, $method->invoke($usage, $orgResponse));
        $this->assertCount(1, $method->invoke($usage, $userResponse));
        $this->assertSame([], $method->invoke($usage, $malformedResponse));
        $this->assertCount(1, $method->invoke($usage, $bareListResponse));
    }
}

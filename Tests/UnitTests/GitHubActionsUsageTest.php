<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\RequestException;
use GuiBranco\Pancake\Response;
use GuiBranco\ProjectsMonitor\Library\GitHubActionsUsage;
use GuiBranco\ProjectsMonitor\Library\GitHubActionsUsageException;
use GuiBranco\ProjectsMonitor\Library\GitHubBillingConfig;

/** Tests GitHubActionsUsage's orchestration: per-account degradation, no cross-account summing, and token resolution. */
class GitHubActionsUsageTest extends TestCase
{
    private array $writtenFiles = [];

    private array $cacheFilesToClean = [];

    /** Deletes any temp fixture files and cache files written during the test. */
    protected function tearDown(): void
    {
        foreach ($this->writtenFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->writtenFiles = [];

        foreach ($this->cacheFilesToClean as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->cacheFilesToClean = [];
    }

    /** Writes $data as a temp JSON fixture file, tracked for cleanup in tearDown(). */
    private function writeFixture(array $data): string
    {
        $path = sys_get_temp_dir() . "/github-billing-usage-" . uniqid("", true) . ".json";
        file_put_contents($path, json_encode($data));
        $this->writtenFiles[] = $path;
        return $path;
    }

    /** A real GitHubBillingConfig backed by a temp fixture with a single "free" plan and the given accounts. */
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

    /** A minimal resolved-account-shaped array for fixtures, on the "free" plan by default. */
    private function accountEntry(string $name, string $type = "org", ?string $tokenSecret = null): array
    {
        return [
            "account" => $name,
            "accountType" => $type,
            "planType" => "free",
            "cycleResetDay" => null,
            "tokenSecret" => $tokenSecret,
            // (object) so json_encode() emits {} rather than [] — an empty PHP
            // array is otherwise indistinguishable from an empty JSON array.
            "overrides" => (object) [],
        ];
    }

    /** Sets a private property on a GitHubActionsUsage instance via reflection, bypassing its constructor. */
    private function setPrivateProperty(object $object, string $property, $value): void
    {
        // Test-only reflection against our own class, not attacker-controlled input.
        $reflection = new ReflectionProperty(GitHubActionsUsage::class, $property);
        $reflection->setAccessible(true); // NOSONAR
        $reflection->setValue($object, $value); // NOSONAR
    }

    /** Invokes the private resolveToken() method via reflection. */
    private function invokeResolveToken(GitHubActionsUsage $usage, array $account): string
    {
        $method = new ReflectionMethod(GitHubActionsUsage::class, "resolveToken");
        $method->setAccessible(true); // NOSONAR

        return $method->invoke($usage, $account);
    }

    /** A fake Actions "minutes" usageItem with the given raw quantity, Linux-priced (1x multiplier). */
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

        // Reflection is only used to inject a fixture-backed collaborator into our
        // own class in test code — never against attacker-controlled input. NOSONAR
        $property = new ReflectionProperty(GitHubActionsUsage::class, "config");
        $property->setAccessible(true); // NOSONAR
        $property->setValue($usage, $config); // NOSONAR

        return $usage;
    }

    /**
     * Builds a GitHubActionsUsage instance with everything real EXCEPT the
     * injected Request — used to exercise fetchUsageData/fetchCachedJson/the
     * cache layer for real, rather than mocking the network boundary away.
     * GuiBranco\Pancake\Request/Response are safe to use this way: Request is
     * a plain non-final class, and Response has public success()/error()
     * factories, so no HTTP call ever actually happens.
     */
    private function usageWithRealFetch(GitHubBillingConfig $config, Request $request): GitHubActionsUsage
    {
        // buildHeaders() reads the USER_AGENT constant, normally defined by
        // Configuration::init() inside the real (skipped) constructor.
        (new \GuiBranco\ProjectsMonitor\Library\Configuration())->init();

        $usage = $this->getMockBuilder(GitHubActionsUsage::class)
            ->disableOriginalConstructor()
            ->onlyMethods([]) // mock nothing — fetchUsageData and everything below it runs for real
            ->getMock();

        $this->setPrivateProperty($usage, "config", $config);
        $this->setPrivateProperty($usage, "defaultToken", "test-token");
        $this->setPrivateProperty($usage, "tokens", ["gitHubToken" => "test-token"]);
        $this->setPrivateProperty($usage, "request", $request);

        return $usage;
    }

    /** A mock of the real HTTP client whose get() is configured per-test; never makes a live call. */
    private function mockRequest(): Request
    {
        return $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->onlyMethods(["get"])
            ->getMock();
    }

    /** Path of the cache file fetchCachedJson() would use for one month's summary/usage fetch, tracked for cleanup. */
    private function trackCacheFile(string $type, string $account, int $year, int $month): string
    {
        $path = __DIR__ . "/../../Src/cache/github_billing_{$type}_{$account}_{$year}_{$month}.json";
        $this->cacheFilesToClean[] = $path;
        return $path;
    }

    /** A single account's fetch failure degrades only that account — the others stay "ok". */
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
                throw new GitHubActionsUsageException("403 Forbidden");
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

    /** Each account's minutes/allowance/percentage stay independent — never merged or summed. */
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

    /** The highest-utilisation rollup skips unavailable accounts rather than treating them as 0% or blocking the result. */
    public function testHighestUtilizationIgnoresUnavailableAccounts()
    {
        $config = $this->buildConfig([
            $this->accountEntry("accountA"),
            $this->accountEntry("accountB"),
        ]);

        $usage = $this->mockUsage($config);
        $usage->method("fetchUsageData")->willReturnCallback(function (array $account) {
            if ($account["account"] === "accountB") {
                throw new GitHubActionsUsageException("timeout");
            }

            return ["usageItems" => [$this->usageItem(1900)], "usageRows" => []];
        });

        $results = $usage->getAllAccountsUsage();

        // accountB is unavailable and must not pull the badge down (or up) — only accountA counts.
        $this->assertSame(95.0, $usage->getHighestUtilizationPercentage($results));
    }

    /** With every account unavailable, the highest-utilisation rollup is null rather than 0 or an error. */
    public function testHighestUtilizationIsNullWhenAllAccountsUnavailable()
    {
        $config = $this->buildConfig([$this->accountEntry("accountA")]);

        $usage = $this->mockUsage($config);
        $usage->method("fetchUsageData")->willThrowException(new GitHubActionsUsageException("503"));

        $results = $usage->getAllAccountsUsage();

        $this->assertNull($usage->getHighestUtilizationPercentage($results));
    }

    /** A null tokenSecret resolves to the shared default token. */
    public function testResolveTokenReturnsDefaultTokenWhenTokenSecretIsNull()
    {
        $usage = $this->getMockBuilder(GitHubActionsUsage::class)->disableOriginalConstructor()->getMock();
        $this->setPrivateProperty($usage, "defaultToken", "default-token-value");
        $this->setPrivateProperty($usage, "tokens", ["gitHubToken" => "default-token-value"]);

        $token = $this->invokeResolveToken($usage, $this->accountEntry("accountA"));

        $this->assertSame("default-token-value", $token);
    }

    /**
     * Regression: resolveToken() used to read $GLOBALS[$secretName], but only
     * $gitHubToken was ever promoted into $GLOBALS (via `global $gitHubToken;`
     * before the require) — any other per-account token the secrets file
     * defined was invisible. loadTokens() now captures every variable the
     * secrets file defines via get_defined_vars().
     */
    public function testResolveTokenReturnsNamedTokenWhenTokenSecretIsConfigured()
    {
        $usage = $this->getMockBuilder(GitHubActionsUsage::class)->disableOriginalConstructor()->getMock();
        $this->setPrivateProperty($usage, "defaultToken", "default-token-value");
        $this->setPrivateProperty($usage, "tokens", [
            "gitHubToken" => "default-token-value",
            "gitHubTokenApiBr" => "apibr-token-value",
        ]);

        $token = $this->invokeResolveToken($usage, $this->accountEntry("accountB", "org", "gitHubTokenApiBr"));

        $this->assertSame("apibr-token-value", $token);
    }

    /** A tokenSecret naming a variable the secrets file never defined throws, rather than silently using the default. */
    public function testResolveTokenThrowsWhenConfiguredSecretIsNotDefined()
    {
        $usage = $this->getMockBuilder(GitHubActionsUsage::class)->disableOriginalConstructor()->getMock();
        $this->setPrivateProperty($usage, "defaultToken", "default-token-value");
        $this->setPrivateProperty($usage, "tokens", ["gitHubToken" => "default-token-value"]);

        $this->expectException(GitHubActionsUsageException::class);
        $this->expectExceptionMessageMatches("/gitHubTokenMissing/");

        $this->invokeResolveToken($usage, $this->accountEntry("accountC", "org", "gitHubTokenMissing"));
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

        // Test-only reflection against our own class, not attacker-controlled input.
        $method = new ReflectionMethod(GitHubActionsUsage::class, "extractUsageItems");
        $method->setAccessible(true); // NOSONAR

        $orgResponse = json_decode('{"timePeriod":{"year":2026},"organization":"ApiBR","usageItems":[{"product":"Actions"}]}');
        $userResponse = json_decode('{"timePeriod":{"year":2026},"user":"guibranco","usageItems":[{"product":"Actions"}]}');
        $malformedResponse = json_decode('{"timePeriod":{"year":2026},"organization":"ApiBR"}');
        $bareListResponse = json_decode('[{"product":"Actions"}]');

        $this->assertCount(1, $method->invoke($usage, $orgResponse));
        $this->assertCount(1, $method->invoke($usage, $userResponse));
        $this->assertSame([], $method->invoke($usage, $malformedResponse));
        $this->assertCount(1, $method->invoke($usage, $bareListResponse));
    }

    /** A mocked Request whose get() returns $summaryBody for the summary endpoint URL, $rowsBody otherwise. */
    private function mockRequestRespondingByUrl(string $summaryBody, string $rowsBody): Request
    {
        $request = $this->mockRequest();
        $request->method("get")->willReturnCallback(function ($url) use ($summaryBody, $rowsBody) {
            return str_contains($url, "/settings/billing/usage/summary")
                ? Response::success($summaryBody, $url, [])
                : Response::success($rowsBody, $url, []);
        });

        return $request;
    }

    /** End-to-end real fetch (no fetchUsageData mock): live HTTP success writes both the summary and usage cache files. */
    public function testGetAccountUsageFetchesLiveAndWritesCache()
    {
        $account = "liveFetchAcct";
        $now = new DateTimeImmutable("now");
        $year = (int) $now->format("Y");
        $month = (int) $now->format("n");
        $summaryPath = $this->trackCacheFile("summary", $account, $year, $month);
        $usagePath = $this->trackCacheFile("usage", $account, $year, $month);

        $summaryBody = json_encode(["usageItems" => [
            ["product" => "Actions", "unitType" => "minutes", "pricePerUnit" => 0.008, "grossQuantity" => 100, "discountAmount" => 0],
        ]]);
        $rowsBody = json_encode([
            ["product" => "Actions", "unitType" => "minutes", "pricePerUnit" => 0.008, "quantity" => 100, "repositoryName" => "guibranco/demo", "date" => $now->format("Y-m-d")],
        ]);

        $config = $this->buildConfig([$this->accountEntry($account, "user")]);
        $usage = $this->usageWithRealFetch($config, $this->mockRequestRespondingByUrl($summaryBody, $rowsBody));

        $results = $usage->getAllAccountsUsage();

        $this->assertSame("ok", $results[0]["status"]);
        $this->assertSame(100.0, $results[0]["minutes"]["weightedUsed"]);
        $this->assertNotEmpty($results[0]["topRepositories"]);
        $this->assertFileExists($summaryPath);
        $this->assertFileExists($usagePath);
    }

    /** A fresh cache hit is served without ever calling Request::get(). */
    public function testGetAccountUsageServesFreshCacheWithoutCallingRequest()
    {
        $account = "cacheHitAcct";
        $now = new DateTimeImmutable("now");
        $year = (int) $now->format("Y");
        $month = (int) $now->format("n");
        $summaryPath = $this->trackCacheFile("summary", $account, $year, $month);
        $usagePath = $this->trackCacheFile("usage", $account, $year, $month);

        file_put_contents($summaryPath, json_encode(["usageItems" => [
            ["product" => "Actions", "unitType" => "minutes", "pricePerUnit" => 0.008, "grossQuantity" => 200, "discountAmount" => 0],
        ]]));
        file_put_contents($usagePath, json_encode([]));

        $config = $this->buildConfig([$this->accountEntry($account, "user")]);
        $request = $this->mockRequest();
        $request->expects($this->never())->method("get");

        $usage = $this->usageWithRealFetch($config, $request);
        $results = $usage->getAllAccountsUsage();

        $this->assertSame("ok", $results[0]["status"]);
        $this->assertSame(200.0, $results[0]["minutes"]["weightedUsed"]);
    }

    /** A corrupt/truncated cache file is treated as a miss — the account still gets a real, live-fetched result. */
    public function testGetAccountUsageTreatsCorruptCacheAsMissAndFetchesLive()
    {
        $account = "corruptCacheAcct";
        $now = new DateTimeImmutable("now");
        $year = (int) $now->format("Y");
        $month = (int) $now->format("n");
        $summaryPath = $this->trackCacheFile("summary", $account, $year, $month);
        $usagePath = $this->trackCacheFile("usage", $account, $year, $month);

        file_put_contents($summaryPath, "{not valid json");
        file_put_contents($usagePath, "{not valid json");

        $summaryBody = json_encode(["usageItems" => [
            ["product" => "Actions", "unitType" => "minutes", "pricePerUnit" => 0.008, "grossQuantity" => 300, "discountAmount" => 0],
        ]]);
        $rowsBody = json_encode([]);

        $config = $this->buildConfig([$this->accountEntry($account, "user")]);
        $usage = $this->usageWithRealFetch($config, $this->mockRequestRespondingByUrl($summaryBody, $rowsBody));

        $results = $usage->getAllAccountsUsage();

        $this->assertSame("ok", $results[0]["status"]);
        $this->assertSame(300.0, $results[0]["minutes"]["weightedUsed"]);
    }

    /** A live-fetch failure with an existing (even stale) cache falls back to serving that cache instead of degrading. */
    public function testGetAccountUsageFallsBackToStaleCacheOnRequestFailure()
    {
        $account = "staleFallbackAcct";
        $now = new DateTimeImmutable("now");
        $year = (int) $now->format("Y");
        $month = (int) $now->format("n");
        $summaryPath = $this->trackCacheFile("summary", $account, $year, $month);
        $usagePath = $this->trackCacheFile("usage", $account, $year, $month);

        file_put_contents($summaryPath, json_encode(["usageItems" => [
            ["product" => "Actions", "unitType" => "minutes", "pricePerUnit" => 0.008, "grossQuantity" => 400, "discountAmount" => 0],
        ]]));
        file_put_contents($usagePath, json_encode([]));
        // Force both cache files outside the TTL window so a live fetch is attempted.
        touch($summaryPath, time() - 7200);
        touch($usagePath, time() - 7200);

        $config = $this->buildConfig([$this->accountEntry($account, "user")]);
        $request = $this->mockRequest();
        $request->method("get")->willThrowException(new RequestException("503 Service Unavailable"));

        $usage = $this->usageWithRealFetch($config, $request);
        $results = $usage->getAllAccountsUsage();

        $this->assertSame("ok", $results[0]["status"]);
        $this->assertSame(400.0, $results[0]["minutes"]["weightedUsed"]);
    }

    /** A live-fetch failure with no cache at all degrades that account rather than throwing. */
    public function testGetAccountUsageDegradesWhenRequestFailsWithNoCache()
    {
        $account = "noCacheFailureAcct";
        $now = new DateTimeImmutable("now");
        $year = (int) $now->format("Y");
        $month = (int) $now->format("n");
        $this->trackCacheFile("summary", $account, $year, $month);
        $this->trackCacheFile("usage", $account, $year, $month);

        $config = $this->buildConfig([$this->accountEntry($account, "user")]);
        $request = $this->mockRequest();
        $request->method("get")->willThrowException(new RequestException("timeout"));

        $usage = $this->usageWithRealFetch($config, $request);
        $results = $usage->getAllAccountsUsage();

        $this->assertSame("unavailable", $results[0]["status"]);
        $this->assertStringContainsString("timeout", $results[0]["reason"]);
    }
}

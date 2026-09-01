<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\GitHubBillingConfig;
use GuiBranco\ProjectsMonitor\Library\GitHubBillingConfigException;

/** Tests GitHubBillingConfig's schema validation, plan/override resolution, and business-rule enforcement. */
class GitHubBillingConfigTest extends TestCase
{
    private array $writtenFiles = [];

    /** Deletes any temp fixture files written by writeFixture() during the test. */
    protected function tearDown(): void
    {
        foreach ($this->writtenFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->writtenFiles = [];
    }

    /** The real github-billing.schema.json, associative-decoded. */
    private function schema(): array
    {
        return json_decode(file_get_contents(__DIR__ . "/../../Src/Library/github-billing.schema.json"), true);
    }

    /** Writes $data as a temp JSON fixture file, tracked for cleanup in tearDown(). */
    private function writeFixture(array $data): string
    {
        $path = sys_get_temp_dir() . "/github-billing-" . uniqid("", true) . ".json";
        file_put_contents($path, json_encode($data));
        $this->writtenFiles[] = $path;
        return $path;
    }

    /** A minimal valid config: free/pro/team plans and one "free" org account. */
    private function baseConfig(): array
    {
        return [
            "version" => 1,
            "plans" => [
                "free" => ["accountTypes" => ["user", "org"], "minutes" => 2000, "storageMb" => 500],
                "pro" => ["accountTypes" => ["user"], "minutes" => 3000, "storageMb" => 1024],
                "team" => ["accountTypes" => ["org"], "minutes" => 3000, "storageMb" => 2048],
            ],
            "accounts" => [
                [
                    "account" => "someOrg",
                    "accountType" => "org",
                    "planType" => "free",
                    "cycleResetDay" => null,
                    // (object) so json_encode() emits {} rather than [] — an empty
                    // PHP array is otherwise indistinguishable from an empty JSON array.
                    "overrides" => (object) [],
                ],
            ],
        ];
    }

    /** The real github-billing.json resolves all three tracked accounts, including guibranco's storageMb override. */
    public function testLoadsRealConfigFromRepo()
    {
        $config = new GitHubBillingConfig();
        $accounts = $config->getAccounts();

        $byName = [];
        foreach ($accounts as $account) {
            $byName[$account["account"]] = $account;
        }

        $this->assertCount(3, $accounts);
        $this->assertArrayHasKey("guibranco", $byName);
        $this->assertArrayHasKey("ApiBR", $byName);
        $this->assertArrayHasKey("InovacaoMediaBrasil", $byName);

        // guibranco overrides storageMb to 2048 despite the "pro" plan's 1024 default
        $this->assertSame(3000, $byName["guibranco"]["minutes"]);
        $this->assertSame(2048, $byName["guibranco"]["storageMb"]);

        // ApiBR/InovacaoMediaBrasil are unmodified "free" org plans
        $this->assertSame(2000, $byName["ApiBR"]["minutes"]);
        $this->assertSame(500, $byName["ApiBR"]["storageMb"]);
    }

    /** A per-account override wins over its plan's default for that field only. */
    public function testOverrideMergeWinsOverPlanDefault()
    {
        $data = $this->baseConfig();
        $data["accounts"][0]["overrides"] = ["minutes" => 12345];
        $path = $this->writeFixture($data);

        $config = new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
        $account = $config->getAccount("someOrg");

        $this->assertSame(12345, $account["minutes"]);
        $this->assertSame(500, $account["storageMb"]); // untouched, still the plan default
    }

    /** Changing an account's planType in the JSON alone changes its resolved quota — no code change needed. */
    public function testPlanUpgradeChangesDenominatorWithNoCodeChange()
    {
        $data = $this->baseConfig();
        $path = $this->writeFixture($data);
        $schemaPath = __DIR__ . "/../../Src/Library/github-billing.schema.json";

        $before = (new GitHubBillingConfig($path, $schemaPath))->getAccount("someOrg");
        $this->assertSame(2000, $before["minutes"]);

        // Simulate the only change an operator should ever need to make: edit the JSON.
        $data["accounts"][0]["planType"] = "team";
        $upgradedPath = $this->writeFixture($data);

        $after = (new GitHubBillingConfig($upgradedPath, $schemaPath))->getAccount("someOrg");
        $this->assertSame(3000, $after["minutes"]);
        $this->assertSame(2048, $after["storageMb"]);
    }

    /** An account referencing an undefined planType fails loudly, listing the valid plan names. */
    public function testUnknownPlanTypeThrowsWithValidPlanList()
    {
        $data = $this->baseConfig();
        $data["accounts"][0]["planType"] = "bogusPlan";
        $path = $this->writeFixture($data);

        $this->expectException(GitHubBillingConfigException::class);
        $this->expectExceptionMessageMatches("/unknown planType 'bogusPlan'.*free.*pro.*team/s");

        $thrown = new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
        $this->fail("Expected GitHubBillingConfigException but constructed: " . $thrown::class);
    }

    /** An org marked with a user-only plan ("pro") fails loudly, naming the account. */
    public function testAccountTypeNotAllowedByPlanThrowsNamingAccount()
    {
        $data = $this->baseConfig();
        // "pro" only allows accountTypes: ["user"] — marking an org as "pro" must fail loudly.
        $data["accounts"][0]["account"] = "wronglyProOrg";
        $data["accounts"][0]["accountType"] = "org";
        $data["accounts"][0]["planType"] = "pro";
        $path = $this->writeFixture($data);

        $this->expectException(GitHubBillingConfigException::class);
        $this->expectExceptionMessageMatches("/wronglyProOrg.*accountType 'org'.*plan 'pro'/s");

        $thrown = new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
        $this->fail("Expected GitHubBillingConfigException but constructed: " . $thrown::class);
    }

    /** A user marked with an org-only plan ("team") fails loudly, naming the account. */
    public function testUserMarkedTeamThrows()
    {
        $data = $this->baseConfig();
        // "team" only allows accountTypes: ["org"] — marking a user as "team" must fail loudly.
        $data["accounts"][0]["account"] = "wronglyTeamUser";
        $data["accounts"][0]["accountType"] = "user";
        $data["accounts"][0]["planType"] = "team";
        $path = $this->writeFixture($data);

        $this->expectException(GitHubBillingConfigException::class);
        $this->expectExceptionMessageMatches("/wronglyTeamUser.*accountType 'user'.*plan 'team'/s");

        $thrown = new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
        $this->fail("Expected GitHubBillingConfigException but constructed: " . $thrown::class);
    }

    /** A plan missing a required field ("minutes") fails schema validation with a clear message. */
    public function testMissingRequiredPropertyFailsSchemaValidation()
    {
        $data = $this->baseConfig();
        unset($data["plans"]["free"]["minutes"]);
        $path = $this->writeFixture($data);

        $this->expectException(GitHubBillingConfigException::class);
        $this->expectExceptionMessageMatches("/missing required property 'minutes'/");

        $thrown = new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
        $this->fail("Expected GitHubBillingConfigException but constructed: " . $thrown::class);
    }

    /** A wrong-typed field ("version" as a string) fails schema validation. */
    public function testWrongTypeFailsSchemaValidation()
    {
        $data = $this->baseConfig();
        $data["version"] = "1"; // must be an integer, not a string
        $path = $this->writeFixture($data);

        $this->expectException(GitHubBillingConfigException::class);
        $this->expectExceptionMessageMatches("/expected type integer/");

        $thrown = new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
        $this->fail("Expected GitHubBillingConfigException but constructed: " . $thrown::class);
    }

    /** An account property not defined by the schema ("typo") fails validation. */
    public function testUnexpectedPropertyFailsSchemaValidation()
    {
        $data = $this->baseConfig();
        $data["accounts"][0]["typo"] = "oops";
        $path = $this->writeFixture($data);

        $this->expectException(GitHubBillingConfigException::class);
        $this->expectExceptionMessageMatches("/unexpected property 'typo'/");

        $thrown = new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
        $this->fail("Expected GitHubBillingConfigException but constructed: " . $thrown::class);
    }

    /** The real github-billing.json validates against the real schema with zero errors. */
    public function testRealConfigValidatesAgainstRealSchemaWithNoErrors()
    {
        // Object-mode decode (no `true`) — matches what the constructor now
        // validates against, preserving the {} vs [] distinction.
        $configData = json_decode(file_get_contents(__DIR__ . "/../../Src/Library/github-billing.json"));
        $errors = GitHubBillingConfig::validateAgainstSchema($configData, $this->schema());

        $this->assertSame([], $errors);
    }

    /** A "plans": [] typo (JSON array instead of object) is rejected, not silently treated as an empty object. */
    public function testEmptyPlansArrayFailsSchemaValidationInsteadOfSilentlyPassingAsAnEmptyObject()
    {
        // json_decode(..., true) collapses {} and [] into the same PHP [], so a
        // "plans": [] typo (JSON array instead of object) must be rejected during
        // validation — otherwise every account would fail later with a confusing
        // "unknown planType" error instead of a clear schema error naming the typo.
        $data = $this->baseConfig();
        $data["plans"] = [];
        $path = $this->writeFixture($data);

        $this->expectException(GitHubBillingConfigException::class);
        $this->expectExceptionMessageMatches("/plans: expected type object, got array/");

        $thrown = new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
        $this->fail("Expected GitHubBillingConfigException but constructed: " . $thrown::class);
    }

    /** A nonexistent config path fails loudly rather than falling back to defaults. */
    public function testMissingFileThrows()
    {
        $this->expectException(GitHubBillingConfigException::class);
        $this->expectExceptionMessageMatches("/File not found/");

        $thrown = new GitHubBillingConfig("/no/such/file.json", __DIR__ . "/../../Src/Library/github-billing.schema.json");
        $this->fail("Expected GitHubBillingConfigException but constructed: " . $thrown::class);
    }
}

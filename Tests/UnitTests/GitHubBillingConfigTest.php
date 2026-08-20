<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\GitHubBillingConfig;
use GuiBranco\ProjectsMonitor\Library\GitHubBillingConfigException;

class GitHubBillingConfigTest extends TestCase
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

    private function schema(): array
    {
        return json_decode(file_get_contents(__DIR__ . "/../../Src/Library/github-billing.schema.json"), true);
    }

    private function writeFixture(array $data): string
    {
        $path = sys_get_temp_dir() . "/github-billing-" . uniqid("", true) . ".json";
        file_put_contents($path, json_encode($data));
        $this->writtenFiles[] = $path;
        return $path;
    }

    private function writeSchemaFixture(array $schema): string
    {
        $path = sys_get_temp_dir() . "/github-billing-schema-" . uniqid("", true) . ".json";
        file_put_contents($path, json_encode($schema));
        $this->writtenFiles[] = $path;
        return $path;
    }

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
                    "overrides" => [],
                ],
            ],
        ];
    }

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

    public function testUnknownPlanTypeThrowsWithValidPlanList()
    {
        $data = $this->baseConfig();
        $data["accounts"][0]["planType"] = "bogusPlan";
        $path = $this->writeFixture($data);

        $this->expectException(GitHubBillingConfigException::class);
        $this->expectExceptionMessageMatches("/unknown planType 'bogusPlan'.*free.*pro.*team/s");

        new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
    }

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

        new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
    }

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

        new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
    }

    public function testMissingRequiredPropertyFailsSchemaValidation()
    {
        $data = $this->baseConfig();
        unset($data["plans"]["free"]["minutes"]);
        $path = $this->writeFixture($data);

        $this->expectException(GitHubBillingConfigException::class);
        $this->expectExceptionMessageMatches("/missing required property 'minutes'/");

        new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
    }

    public function testWrongTypeFailsSchemaValidation()
    {
        $data = $this->baseConfig();
        $data["version"] = "1"; // must be an integer, not a string
        $path = $this->writeFixture($data);

        $this->expectException(GitHubBillingConfigException::class);
        $this->expectExceptionMessageMatches("/expected type integer/");

        new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
    }

    public function testUnexpectedPropertyFailsSchemaValidation()
    {
        $data = $this->baseConfig();
        $data["accounts"][0]["typo"] = "oops";
        $path = $this->writeFixture($data);

        $this->expectException(GitHubBillingConfigException::class);
        $this->expectExceptionMessageMatches("/unexpected property 'typo'/");

        new GitHubBillingConfig($path, __DIR__ . "/../../Src/Library/github-billing.schema.json");
    }

    public function testRealConfigValidatesAgainstRealSchemaWithNoErrors()
    {
        $configData = json_decode(file_get_contents(__DIR__ . "/../../Src/Library/github-billing.json"), true);
        $errors = GitHubBillingConfig::validateAgainstSchema($configData, $this->schema());

        $this->assertSame([], $errors);
    }

    public function testMissingFileThrows()
    {
        $this->expectException(GitHubBillingConfigException::class);
        $this->expectExceptionMessageMatches("/File not found/");

        new GitHubBillingConfig("/no/such/file.json", __DIR__ . "/../../Src/Library/github-billing.schema.json");
    }
}

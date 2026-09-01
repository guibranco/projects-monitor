<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\GitHubActionsUsage;
use GuiBranco\ProjectsMonitor\Library\GitHubActionsUsagePresenter;

class GitHubActionsUsagePresenterTest extends TestCase
{
    private ?GitHubActionsUsagePresenter $presenter = null;

    protected function setUp(): void
    {
        $this->presenter = new GitHubActionsUsagePresenter();
    }

    private function okUsage(array $overrides = []): array
    {
        return array_merge([
            "account" => "guibranco",
            "accountType" => "user",
            "planType" => "pro",
            "status" => "ok",
            "reason" => null,
            "minutes" => ["rawUsed" => 1401.0, "weightedUsed" => 1401.0, "included" => 3000, "percentage" => 46.7],
            "storage" => ["usedGb" => 0.0, "includedGb" => 2.0, "percentage" => 0.0],
            "cycle" => ["label" => "billing cycle (resets day 1)", "daysUntilReset" => 12, "resetDate" => "2026-09-01"],
            "topRepositories" => [],
        ], $overrides);
    }

    public function testTableStartsWithTheSharedHeaderRow()
    {
        $rows = $this->presenter->toTable([]);

        $this->assertSame([GitHubActionsUsage::TABLE_HEADER], $rows);
    }

    public function testOkAccountRendersMinutesAndStorageBadgesAndResetText()
    {
        $rows = $this->presenter->toTable([$this->okUsage()]);
        $row = $rows[1];

        $this->assertStringContainsString("guibranco", $row[0]);
        $this->assertSame("pro", $row[1]);
        $this->assertStringContainsString("46.7%25", $row[2]);
        $this->assertStringContainsString("1,401", $row[2]); // number_format() thousands separator
        $this->assertStringContainsString("3000__min", $row[2]); // shields.io escapes literal "_" as "__"
        $this->assertStringContainsString("0.0%25", $row[3]);
        $this->assertSame("12 days (billing cycle (resets day 1))", $row[4]);
        $this->assertSame("-", $row[5]);
    }

    public function testUnavailableAccountRendersUnavailableBadgeWithEscapedReason()
    {
        $usage = $this->okUsage([
            "status" => "unavailable",
            "reason" => "403 Forbidden <script>alert(1)</script>",
            "minutes" => null,
            "storage" => null,
            "cycle" => null,
        ]);

        $rows = $this->presenter->toTable([$usage]);
        $row = $rows[1];

        $this->assertStringContainsString("Unavailable", $row[2]);
        $this->assertStringContainsString("&lt;script&gt;", $row[2]);
        $this->assertStringNotContainsString("<script>", $row[2]);
        $this->assertSame($row[2], $row[3]);
        $this->assertSame("-", $row[4]);
        $this->assertSame("-", $row[5]);
    }

    /**
     * Regression: repository names are attacker-influenced (GitHub API data),
     * and must be escaped the same way the unavailable-reason cell already is.
     */
    public function testTopRepositoriesAreHtmlEscaped()
    {
        $usage = $this->okUsage([
            "topRepositories" => [
                ["repository" => "<img src=x onerror=alert(1)>", "minutes" => 42.0],
            ],
        ]);

        $rows = $this->presenter->toTable([$usage]);
        $row = $rows[1];

        $this->assertStringContainsString("&lt;img", $row[5]);
        $this->assertStringNotContainsString("<img src=x", $row[5]);
        $this->assertStringContainsString("(42m)", $row[5]);
    }

    public function testThresholdColoring()
    {
        $green = $this->presenter->toTable([$this->okUsage(["minutes" => ["rawUsed" => 0, "weightedUsed" => 100.0, "included" => 1000, "percentage" => 10.0]])])[1];
        $amber = $this->presenter->toTable([$this->okUsage(["minutes" => ["rawUsed" => 0, "weightedUsed" => 800.0, "included" => 1000, "percentage" => 80.0]])])[1];
        $red = $this->presenter->toTable([$this->okUsage(["minutes" => ["rawUsed" => 0, "weightedUsed" => 950.0, "included" => 1000, "percentage" => 95.0]])])[1];

        $this->assertStringContainsString("brightgreen", $green[2]);
        $this->assertStringContainsString("orange", $amber[2]);
        $this->assertStringContainsString("red", $red[2]);
    }

    public function testOrgAccountLinksToOrganizationBillingPage()
    {
        $usage = $this->okUsage(["account" => "ApiBR", "accountType" => "org"]);

        $rows = $this->presenter->toTable([$usage]);

        $this->assertStringContainsString("https://github.com/organizations/ApiBR/settings/billing/summary", $rows[1][0]);
    }

    public function testUserAccountLinksToPersonalBillingPage()
    {
        $usage = $this->okUsage(["account" => "guibranco", "accountType" => "user"]);

        $rows = $this->presenter->toTable([$usage]);

        $this->assertStringContainsString("https://github.com/settings/billing/summary", $rows[1][0]);
    }
}

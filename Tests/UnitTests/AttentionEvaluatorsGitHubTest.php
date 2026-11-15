<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\DomainsEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\GitHubQuotaEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\Evaluators\WorkflowRunsEvaluator;

/**
 * Tests for the evaluators of the ci, github and domains domains.
 */
class AttentionEvaluatorsGitHubTest extends TestCase
{
    private const NOW = 1763221200;

    private const DAY = 86400;


    /**
     * A workflow run row as the webhooks API returns it.
     *
     * @param string  $repo       The repository of guibranco.
     * @param string  $conclusion The conclusion.
     * @param string  $updatedAt  When it was last updated.
     * @param integer $runId      The workflow run ID.
     *
     * @return array
     */
    private function getRun(string $repo, string $conclusion, string $updatedAt, int $runId): array
    {
        $run = [];

        $run["owner"]           = "guibranco";
        $run["repo"]            = $repo;
        $run["path"]            = ".github/workflows/build.yml";
        $run["name"]            = "Build";
        $run["status"]          = "completed";
        $run["conclusion"]      = $conclusion;
        $run["updated_at"]      = $updatedAt;
        $run["workflow_run_id"] = $runId;

        return $run;

    }//end getRun()


    /**
     * Only the latest run of each workflow of a followed account counts.
     *
     * @return void
     */
    public function testWorkflowRuns(): void
    {
        $foreign          = $this->getRun("theirs", "failure", "2026-11-15T10:00:00Z", 9);
        $foreign["owner"] = "someone-else";
        $running          = $this->getRun("busy", "", "2026-11-15T10:00:00Z", 10);
        $running["status"] = "in_progress";

        $runs = [
                 $this->getRun("fixed", "failure", "2026-11-15T09:00:00Z", 1),
                 $this->getRun("fixed", "success", "2026-11-15T10:00:00Z", 2),
                 $this->getRun("broken", "success", "2026-11-15T09:00:00Z", 3),
                 $this->getRun("broken", "failure", "2026-11-15T10:00:00Z", 4),
                 $this->getRun("approval", "action_required", "2026-11-15T10:00:00Z", 5),
                 $foreign,
                 $running,
                ];

        $evaluation = (new WorkflowRunsEvaluator())->evaluate($runs, new AttentionThresholds([]), self::NOW);
        $items      = $evaluation->getItems();

        $this->assertSame(3, $evaluation->getChecked());
        $this->assertCount(2, $items);
        $this->assertSame("guibranco/broken: Build failure", $items[0]->title);
        $this->assertSame(AttentionItem::CRITICAL, $items[0]->severity);
        $this->assertSame("2026-11-15T10:00:00Z", $items[0]->since);
        $this->assertSame("wf:guibranco/broken/.github/workflows/build.yml", $items[0]->fingerprint);
        $this->assertSame(4, $items[0]->toArray()["meta"]->workflowRunId);
        $this->assertContains("rerun", $items[0]->toArray()["actions"]);
        $this->assertSame(AttentionItem::WARNING, $items[1]->severity);

    }//end testWorkflowRuns()


    /**
     * The core quota is judged in percent used.
     *
     * @return void
     */
    public function testGitHubQuota(): void
    {
        $evaluator  = new GitHubQuotaEvaluator();
        $thresholds = new AttentionThresholds([]);

        $this->assertCount(0, $evaluator->evaluate(["used" => 100, "limit" => 5000, "reset" => self::NOW], $thresholds, self::NOW)->getItems());
        $this->assertCount(0, $evaluator->evaluate([], $thresholds, self::NOW)->getItems());

        $items = $evaluator->evaluate(["used" => 4700, "limit" => 5000, "reset" => self::NOW], $thresholds, self::NOW)->getItems();
        $this->assertSame(AttentionItem::CRITICAL, $items[0]->severity);
        $this->assertStringStartsWith("GitHub API core quota at 94%", $items[0]->title);

    }//end testGitHubQuota()


    /**
     * Domains close to expiry are warnings, then critical, then expired.
     *
     * @return void
     */
    public function testDomains(): void
    {
        $rows = [
                 array_merge(["domain" => "far.com", "expiresAt" => (self::NOW + (200 * self::DAY))]),
                 array_merge(["domain" => "soon.com", "expiresAt" => (self::NOW + (20 * self::DAY))]),
                 array_merge(["domain" => "urgent.com", "expiresAt" => (self::NOW + (5 * self::DAY))]),
                 array_merge(["domain" => "gone.com", "expiresAt" => (self::NOW - (3 * self::DAY))]),
                 array_merge(["domain" => "unknown.com", "expiresAt" => null]),
                ];

        $evaluation = (new DomainsEvaluator())->evaluate($rows, new AttentionThresholds([]), self::NOW);
        $items      = $evaluation->getItems();

        $this->assertSame(5, $evaluation->getChecked());
        $this->assertCount(3, $items);
        $this->assertSame(AttentionItem::WARNING, $items[0]->severity);
        $this->assertSame("Domain soon.com expires in 20 days", $items[0]->title);
        $this->assertSame(AttentionItem::CRITICAL, $items[1]->severity);
        $this->assertSame("Domain gone.com expired 3 days ago", $items[2]->title);

    }//end testDomains()


}//end class

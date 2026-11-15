<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention\Evaluators;

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluation;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Attention\Fingerprint;
use GuiBranco\ProjectsMonitor\Library\Attention\TimeFormatter;
use GuiBranco\ProjectsMonitor\Library\GitHubAccounts;
use GuiBranco\ProjectsMonitor\Library\Webhooks;
use GuiBranco\ProjectsMonitor\Library\WorkflowRunsPresenter;

/**
 * Workflows of the followed GitHub accounts whose latest run failed. Only the
 * latest run of each workflow counts, so a failure fixed by a later green run
 * drops off on its own.
 */
class WorkflowRunsEvaluator implements AttentionEvaluator
{
    private const SEVERITIES = [
                                "failure"         => AttentionItem::CRITICAL,
                                "startup_failure" => AttentionItem::CRITICAL,
                                "timed_out"       => AttentionItem::CRITICAL,
                                "action_required" => AttentionItem::WARNING,
                               ];


    /**
     * The source name.
     *
     * @return string
     */
    public function getSource(): string
    {
        return "workflow-runs";

    }//end getSource()


    /**
     * The domain.
     *
     * @return string
     */
    public function getDomain(): string
    {
        return "ci";

    }//end getDomain()


    /**
     * Reads the tracked workflow runs.
     *
     * @return mixed
     */
    public function fetch(): mixed
    {
        return (new Webhooks())->getWorkflowRunRows();

    }//end fetch()


    /**
     * Decides which workflows need attention.
     *
     * @param mixed               $data       Rows of the webhooks API's GET /workflow-runs/.
     * @param AttentionThresholds $thresholds Unused: a failed run needs a look.
     * @param integer             $now        Unused.
     *
     * @return AttentionEvaluation
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function evaluate(mixed $data, AttentionThresholds $thresholds, int $now): AttentionEvaluation
    {
        $evaluation = new AttentionEvaluation();
        foreach ($this->latestByWorkflow((array) $data) as $workflow => $run) {
            $item = $this->buildItem($workflow, $run);
            if ($item === null) {
                $evaluation->addHealthy();
                continue;
            }

            $evaluation->addItem($item);
        }

        return $evaluation;

    }//end evaluate()


    /**
     * The latest completed run of each workflow of a followed account, by "owner/repo/path".
     *
     * @param array $runs The rows.
     *
     * @return array
     */
    private function latestByWorkflow(array $runs): array
    {
        $accounts = new GitHubAccounts();
        $latest   = [];
        foreach ($runs as $run) {
            if (is_array($run) === false || ($run["status"] ?? "") !== "completed" || $accounts->isOwn(($run["owner"] ?? "")) === false) {
                continue;
            }

            $workflow = sprintf("%s/%s/%s", ($run["owner"] ?? ""), ($run["repo"] ?? ""), ($run["path"] ?? ($run["name"] ?? "")));
            $previous = ($latest[$workflow] ?? null);
            if ($previous === null || strtotime((string) ($run["updated_at"] ?? "")) > strtotime((string) ($previous["updated_at"] ?? ""))) {
                $latest[$workflow] = $run;
            }
        }

        return $latest;

    }//end latestByWorkflow()


    /**
     * The item for a workflow whose latest run failed.
     *
     * @param string $workflow The workflow key ("owner/repo/path").
     * @param array  $run      Its latest run.
     *
     * @return AttentionItem|null Null when the run did not fail.
     */
    private function buildItem(string $workflow, array $run): ?AttentionItem
    {
        $conclusion = (string) ($run["conclusion"] ?? "");
        $severity   = (self::SEVERITIES[$conclusion] ?? null);
        if ($severity === null) {
            return null;
        }

        $fingerprint = new Fingerprint();
        $time        = new TimeFormatter();
        $title       = sprintf("%s/%s: %s %s", ($run["owner"] ?? ""), ($run["repo"] ?? ""), ($run["name"] ?? "workflow"), str_replace("_", " ", $conclusion));
        $item        = new AttentionItem($this->getSource(), $this->getDomain(), $severity, $title, $time->toIso(($run["updated_at"] ?? null)), "index.php#workflow_runs_management", $fingerprint->make("wf", $workflow));
        $runId       = (int) ($run["workflow_run_id"] ?? 0);
        if ($runId > 0 && in_array($conclusion, WorkflowRunsPresenter::RETRYABLE_CONCLUSIONS, true) === true) {
            return $item->withAction("rerun", ["workflowRunId" => $runId]);
        }

        return $item;

    }//end buildItem()


}//end class

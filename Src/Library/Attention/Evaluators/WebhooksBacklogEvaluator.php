<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention\Evaluators;

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluation;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Webhooks;

/**
 * The GStraccini-bot pipeline's backlog: webhook entities still waiting to
 * be processed (NEW, RE_REQUESTED, UPDATED or PROCESSING), across the eight
 * entity tables, against the webhooks.pending thresholds. IDLE rows are left
 * out: they wait on purpose until their next Idle Retry.
 */
class WebhooksBacklogEvaluator implements AttentionEvaluator
{
    private const PENDING_STATES = [
                                    "NEW",
                                    "RE_REQUESTED",
                                    "UPDATED",
                                    "PROCESSING",
                                   ];


    /**
     * The source name.
     *
     * @return string
     */
    public function getSource(): string
    {
        return "webhooks";

    }//end getSource()


    /**
     * The domain.
     *
     * @return string
     */
    public function getDomain(): string
    {
        return "pipeline";

    }//end getDomain()


    /**
     * Reads the processing state counts, by state then table.
     *
     * @return mixed
     */
    public function fetch(): mixed
    {
        return (new Webhooks())->getStatistics();

    }//end fetch()


    /**
     * Decides whether the backlog needs attention.
     *
     * @param mixed               $data       Counts by state, then by table (e.g. github_pull_requests).
     * @param AttentionThresholds $thresholds The webhooks.pending thresholds.
     * @param integer             $now        Unused.
     *
     * @return AttentionEvaluation
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function evaluate(mixed $data, AttentionThresholds $thresholds, int $now): AttentionEvaluation
    {
        $evaluation = new AttentionEvaluation();
        $byEntity   = $this->countPending((array) $data);
        $pending    = array_sum($byEntity);
        $severity   = $thresholds->getSeverity("webhooks.pending", $pending);
        if ($severity === null) {
            $evaluation->addHealthy();
            return $evaluation;
        }

        arsort($byEntity);
        $largest = [];
        foreach (array_slice($byEntity, 0, 3, true) as $entity => $count) {
            $largest[] = sprintf("%s %d", str_replace("_", " ", $entity), $count);
        }

        $title = sprintf("%d webhook entities pending processing (%s)", $pending, implode(", ", $largest));
        $evaluation->addItem(new AttentionItem($this->getSource(), $this->getDomain(), $severity, $title, null, "pipeline.php#pending_processing", "webhooks:pending"));

        return $evaluation;

    }//end evaluate()


    /**
     * Pending rows by entity, the "github_" prefix removed.
     *
     * @param array $data Counts by state, then by table.
     *
     * @return array
     */
    private function countPending(array $data): array
    {
        $byEntity = [];
        foreach (self::PENDING_STATES as $state) {
            foreach ((array) ($data[$state] ?? []) as $table => $count) {
                $entity            = str_replace("github_", "", (string) $table);
                $byEntity[$entity] = (($byEntity[$entity] ?? 0) + (int) $count);
            }
        }

        return $byEntity;

    }//end countPending()


}//end class

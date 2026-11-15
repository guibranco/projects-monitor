<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention\Evaluators;

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluation;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Attention\TimeFormatter;
use GuiBranco\ProjectsMonitor\Library\GitHub;

/**
 * The GitHub API core rate limit, against the github.quota thresholds (percent used).
 */
class GitHubQuotaEvaluator implements AttentionEvaluator
{


    /**
     * The source name.
     *
     * @return string
     */
    public function getSource(): string
    {
        return "github-quota";

    }//end getSource()


    /**
     * The domain.
     *
     * @return string
     */
    public function getDomain(): string
    {
        return "github";

    }//end getDomain()


    /**
     * Reads the core rate limit: used, limit and reset.
     *
     * @return mixed
     */
    public function fetch(): mixed
    {
        $usage = (new GitHub())->getApiUsage();
        return (array) ($usage["core"] ?? []);

    }//end fetch()


    /**
     * Decides whether the quota needs attention.
     *
     * @param mixed               $data       The core rate limit, with used, limit and reset (Unix time).
     * @param AttentionThresholds $thresholds The github.quota thresholds.
     * @param integer             $now        Unused.
     *
     * @return AttentionEvaluation
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function evaluate(mixed $data, AttentionThresholds $thresholds, int $now): AttentionEvaluation
    {
        $evaluation = new AttentionEvaluation();
        $core       = (array) $data;
        $limit      = (int) ($core["limit"] ?? 0);
        if ($limit <= 0) {
            return $evaluation;
        }

        $percent  = ((int) ($core["used"] ?? 0) * 100 / $limit);
        $severity = $thresholds->getSeverity("github.quota", $percent);
        if ($severity === null) {
            $evaluation->addHealthy();
            return $evaluation;
        }

        $time  = new TimeFormatter();
        $title = sprintf("GitHub API core quota at %d%% (resets %s UTC)", (int) $percent, gmdate("H:i", (int) ($core["reset"] ?? 0)));
        $evaluation->addItem(new AttentionItem($this->getSource(), $this->getDomain(), $severity, $title, $time->fromTimestamp($now), "ops.php#api_usage", "github:quota-core"));

        return $evaluation;

    }//end evaluate()


}//end class

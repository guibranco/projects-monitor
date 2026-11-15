<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention\Evaluators;

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluation;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\ErrorLog;

/**
 * PHP errors stored in the errors table, against the errors.database thresholds.
 */
class DatabaseErrorsEvaluator implements AttentionEvaluator
{


    /**
     * The source name.
     *
     * @return string
     */
    public function getSource(): string
    {
        return "errors";

    }//end getSource()


    /**
     * The domain.
     *
     * @return string
     */
    public function getDomain(): string
    {
        return "errors";

    }//end getDomain()


    /**
     * Reads how many errors are stored.
     *
     * @return mixed
     */
    public function fetch(): mixed
    {
        return (new ErrorLog())->getTotal();

    }//end fetch()


    /**
     * Decides whether the stored errors need attention.
     *
     * @param mixed               $data       The number of stored errors.
     * @param AttentionThresholds $thresholds The errors.database thresholds.
     * @param integer             $now        Unused.
     *
     * @return AttentionEvaluation
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function evaluate(mixed $data, AttentionThresholds $thresholds, int $now): AttentionEvaluation
    {
        $evaluation = new AttentionEvaluation();
        $total      = (int) $data;
        $severity   = $thresholds->getSeverity("errors.database", $total);
        if ($severity === null) {
            $evaluation->addHealthy();
            return $evaluation;
        }

        $title = sprintf("%d PHP errors in the error log (database)", $total);
        $evaluation->addItem(new AttentionItem($this->getSource(), $this->getDomain(), $severity, $title, null, "index.php#db_error_messages", "errors:database"));

        return $evaluation;

    }//end evaluate()


}//end class

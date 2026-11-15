<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention\Evaluators;

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluation;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Attention\Fingerprint;
use GuiBranco\ProjectsMonitor\Library\Logger;

/**
 * Applications whose logged messages crossed the messages.application thresholds.
 */
class AppMessagesEvaluator implements AttentionEvaluator
{


    /**
     * The source name.
     *
     * @return string
     */
    public function getSource(): string
    {
        return "messages";

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
     * Reads the message count of each application, header row included.
     *
     * @return mixed
     */
    public function fetch(): mixed
    {
        return (new Logger())->getTotalByApplications();

    }//end fetch()


    /**
     * Decides which applications need attention.
     *
     * @param mixed               $data       A header row, then rows of application name and message count.
     * @param AttentionThresholds $thresholds The messages.application thresholds.
     * @param integer             $now        Unused.
     *
     * @return AttentionEvaluation
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function evaluate(mixed $data, AttentionThresholds $thresholds, int $now): AttentionEvaluation
    {
        $evaluation  = new AttentionEvaluation();
        $fingerprint = new Fingerprint();
        foreach (array_slice((array) $data, 1) as $row) {
            $application = (string) ($row[0] ?? "unknown");
            $count       = (int) ($row[1] ?? 0);
            $severity    = $thresholds->getSeverity("messages.application", $count);
            if ($severity === null) {
                $evaluation->addHealthy();
                continue;
            }

            $title = sprintf("%s logged %d messages", $application, $count);
            $evaluation->addItem(new AttentionItem($this->getSource(), $this->getDomain(), $severity, $title, null, "errors.php#messages_grouped", $fingerprint->make("messages", $application)));
        }

        return $evaluation;

    }//end evaluate()


}//end class

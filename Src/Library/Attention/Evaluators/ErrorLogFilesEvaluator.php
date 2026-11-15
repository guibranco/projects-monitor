<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention\Evaluators;

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluation;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Attention\Fingerprint;
use GuiBranco\ProjectsMonitor\Library\Attention\TimeFormatter;
use GuiBranco\ProjectsMonitor\Library\CPanel;

/**
 * error_log files on the cPanel host: each one is a warning until it is
 * read and deleted.
 */
class ErrorLogFilesEvaluator implements AttentionEvaluator
{


    /**
     * The source name.
     *
     * @return string
     */
    public function getSource(): string
    {
        return "error-log-files";

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
     * Reads the error_log files, header row included.
     *
     * @return mixed
     */
    public function fetch(): mixed
    {
        return (new CPanel())->getErrorLogFiles();

    }//end fetch()


    /**
     * Turns every error_log file into a warning.
     *
     * @param mixed               $data       A header row, then rows of directory, size and modification time.
     * @param AttentionThresholds $thresholds Unused: any error_log file needs a look.
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
        $time        = new TimeFormatter();
        $rows        = (array) $data;
        if (count($rows) === 0) {
            $evaluation->addHealthy();
            return $evaluation;
        }

        foreach (array_slice($rows, 1) as $row) {
            $directory = (string) ($row[0] ?? "");
            $title     = sprintf("error_log in /%s (%s)", $directory, (string) ($row[1] ?? "?"));
            $since     = $time->fromDashboardFormat(($row[2] ?? ""));
            $evaluation->addItem(new AttentionItem($this->getSource(), $this->getDomain(), AttentionItem::WARNING, $title, $since, "errors.php#error_log_files", $fingerprint->make("errlog", $directory)));
        }

        return $evaluation;

    }//end evaluate()


}//end class

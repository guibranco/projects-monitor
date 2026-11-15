<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention\Evaluators;

use GuiBranco\ProjectsMonitor\Library\UpTimeRobot;

/**
 * UpTimeRobot monitors that are down or degraded.
 */
class UpTimeRobotEvaluator extends MonitorsEvaluator
{


    /**
     * The source name.
     *
     * @return string
     */
    public function getSource(): string
    {
        return "uptimerobot";

    }//end getSource()


    /**
     * Reads every monitor.
     *
     * @return mixed
     */
    public function fetch(): mixed
    {
        $stats = (new UpTimeRobot())->getStats();
        return $stats["monitors"];

    }//end fetch()


    /**
     * The title format for a status.
     *
     * @param string $status The normalised status.
     *
     * @return string
     */
    protected function getTitleFormat(string $status): string
    {
        if ($status === "critical") {
            return "Monitor %s is down";
        }

        return "Monitor %s seems down (degraded)";

    }//end getTitleFormat()


    /**
     * Where the monitors are listed.
     *
     * @return string
     */
    protected function getLink(): string
    {
        return "index.php#uptimerobot";

    }//end getLink()


    /**
     * The fingerprint prefix.
     *
     * @return string
     */
    protected function getPrefix(): string
    {
        return "utr";

    }//end getPrefix()


}//end class

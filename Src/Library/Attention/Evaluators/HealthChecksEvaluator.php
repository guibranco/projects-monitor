<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention\Evaluators;

use GuiBranco\ProjectsMonitor\Library\HealthChecksIo;

/**
 * Healthchecks.io checks that are down or missed their ping.
 */
class HealthChecksEvaluator extends MonitorsEvaluator
{


    /**
     * The source name.
     *
     * @return string
     */
    public function getSource(): string
    {
        return "healthchecks";

    }//end getSource()


    /**
     * Reads every check.
     *
     * @return mixed
     */
    public function fetch(): mixed
    {
        $stats = (new HealthChecksIo())->getStats();
        return $stats["checks"];

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
            return "Healthcheck %s is down";
        }

        return "Healthcheck %s missed its ping (in grace period)";

    }//end getTitleFormat()


    /**
     * Where the checks are listed.
     *
     * @return string
     */
    protected function getLink(): string
    {
        return "ops.php#healthchecksio";

    }//end getLink()


    /**
     * The fingerprint prefix.
     *
     * @return string
     */
    protected function getPrefix(): string
    {
        return "hc";

    }//end getPrefix()


}//end class

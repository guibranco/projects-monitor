<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention\Evaluators;

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluation;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Attention\Fingerprint;

/**
 * Judges a list of uptime monitors as getStats() of HealthChecks.io and
 * UpTimeRobot returns them: critical when down, warning when late or degraded,
 * nothing when up or paused.
 */
abstract class MonitorsEvaluator implements AttentionEvaluator
{


    /**
     * The domain: monitors are ops.
     *
     * @return string
     */
    public function getDomain(): string
    {
        return "ops";

    }//end getDomain()


    /**
     * Decides which monitors need attention.
     *
     * @param mixed               $data       Rows with name, status (healthy, warning, critical, paused) and lastChange.
     * @param AttentionThresholds $thresholds Unused: a monitor's own status decides.
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
        foreach ((array) $data as $monitor) {
            $status   = (string) ($monitor["status"] ?? "");
            $severity = $this->mapSeverity($status);
            if ($severity === null) {
                $evaluation->addHealthy();
                continue;
            }

            $name = (string) ($monitor["name"] ?? "unknown");
            $item = new AttentionItem(
                $this->getSource(),
                $this->getDomain(),
                $severity,
                sprintf($this->getTitleFormat($status), $name),
                ($monitor["lastChange"] ?? null),
                $this->getLink(),
                $fingerprint->make($this->getPrefix(), $name)
            );
            $evaluation->addItem($item);
        }

        return $evaluation;

    }//end evaluate()


    /**
     * The severity of a monitor status.
     *
     * @param string $status The normalised status.
     *
     * @return string|null
     */
    private function mapSeverity(string $status): ?string
    {
        return match ($status) {
            "critical" => AttentionItem::CRITICAL,
            "warning" => AttentionItem::WARNING,
            default => null,
        };

    }//end mapSeverity()


    /**
     * The title format for a status, with %s for the monitor name.
     *
     * @param string $status The normalised status.
     *
     * @return string
     */
    abstract protected function getTitleFormat(string $status): string;


    /**
     * Where the monitors are listed.
     *
     * @return string
     */
    abstract protected function getLink(): string;


    /**
     * The fingerprint prefix.
     *
     * @return string
     */
    abstract protected function getPrefix(): string;


}//end class

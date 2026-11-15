<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention\Evaluators;

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluation;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Attention\Fingerprint;
use GuiBranco\ProjectsMonitor\Library\PublicStats;

/**
 * Servers and the database that the public stats worker (the cache script)
 * found critical, degraded or unreachable. Read from its cache file, so the
 * SSH round trips are not repeated.
 */
class ServerHealthEvaluator implements AttentionEvaluator
{


    /**
     * The source name.
     *
     * @return string
     */
    public function getSource(): string
    {
        return "servers";

    }//end getSource()


    /**
     * The domain.
     *
     * @return string
     */
    public function getDomain(): string
    {
        return "ops";

    }//end getDomain()


    /**
     * Reads the systemStatus rows of the public stats cache, none when it does not exist yet.
     *
     * @return mixed
     */
    public function fetch(): mixed
    {
        if (file_exists(PublicStats::CACHE_FILE) === false) {
            return [];
        }

        $stats = json_decode((string) file_get_contents(PublicStats::CACHE_FILE), true);
        return ($stats["systemStatus"] ?? []);

    }//end fetch()


    /**
     * Decides which servers need attention.
     *
     * @param mixed               $data       Rows with label and status (operational, warning, critical, unknown).
     * @param AttentionThresholds $thresholds Unused: the worker already applied the resource thresholds.
     * @param integer             $now        Unused.
     *
     * @return AttentionEvaluation
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function evaluate(mixed $data, AttentionThresholds $thresholds, int $now): AttentionEvaluation
    {
        $evaluation = new AttentionEvaluation();
        foreach ((array) $data as $server) {
            $label = (string) ($server["label"] ?? "unknown");
            $item  = $this->buildItem($label, (string) ($server["status"] ?? "unknown"));
            if ($item === null) {
                $evaluation->addHealthy();
                continue;
            }

            $evaluation->addItem($item);
        }

        return $evaluation;

    }//end evaluate()


    /**
     * The item for a server that is not operational.
     *
     * @param string $label  The server name.
     * @param string $status Its status.
     *
     * @return AttentionItem|null Null when it is operational.
     */
    private function buildItem(string $label, string $status): ?AttentionItem
    {
        $title = match ($status) {
            "critical" => sprintf("%s is critical", $label),
            "warning" => sprintf("%s is degraded (high resource usage)", $label),
            "unknown" => sprintf("%s is not reporting", $label),
            default => "",
        };

        if ($title === "") {
            return null;
        }

        $severity = AttentionItem::WARNING;
        if ($status === "critical") {
            $severity = AttentionItem::CRITICAL;
        }

        $fingerprint = new Fingerprint();
        return new AttentionItem($this->getSource(), $this->getDomain(), $severity, $title, null, "index.php#server_health", $fingerprint->make("server", $label));

    }//end buildItem()


}//end class

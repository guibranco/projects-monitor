<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention\Evaluators;

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluation;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\Attention\Fingerprint;
use GuiBranco\ProjectsMonitor\Library\RabbitMq;

/**
 * Broker queues whose backlog crossed the queue.messages thresholds.
 */
class QueuesEvaluator implements AttentionEvaluator
{


    /**
     * The source name.
     *
     * @return string
     */
    public function getSource(): string
    {
        return "queues";

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
     * Reads the queues that hold messages or have consumers.
     *
     * @return mixed
     */
    public function fetch(): mixed
    {
        return (new RabbitMq())->getQueueSummary();

    }//end fetch()


    /**
     * Decides which queues need attention.
     *
     * @param mixed               $data       Rows with name and messages.
     * @param AttentionThresholds $thresholds The queue.messages thresholds.
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
        foreach ((array) $data as $queue) {
            $name     = (string) ($queue["name"] ?? "unknown");
            $messages = (int) ($queue["messages"] ?? 0);
            $severity = $thresholds->getSeverity("queue.messages", $messages);
            if ($severity === null) {
                $evaluation->addHealthy();
                continue;
            }

            $title = sprintf("Queue %s has %d messages waiting", $name, $messages);
            $item  = new AttentionItem($this->getSource(), $this->getDomain(), $severity, $title, null, "index.php#queues", $fingerprint->make("queue", $name));
            $evaluation->addItem($item);
        }

        return $evaluation;

    }//end evaluate()


}//end class

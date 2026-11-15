<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

use GuiBranco\ProjectsMonitor\Library\LogStream;
use GuiBranco\ProjectsMonitor\Library\SecretsFileNotFoundException;

/**
 * Runs every evaluator and gathers what needs attention into one snapshot.
 * A source that is not configured is skipped; a source that fails becomes a
 * warning of its own, so a broken integration never passes for a healthy one.
 */
class AttentionCollector
{

    /**
     * The evaluators to run.
     *
     * @var AttentionEvaluator[]
     */
    private array $evaluators;


    /**
     * Creates the collector.
     *
     * @param AttentionEvaluator[] $evaluators The evaluators to run.
     */
    public function __construct(array $evaluators)
    {
        $this->evaluators = $evaluators;

    }//end __construct()


    /**
     * Runs the evaluators.
     *
     * @param AttentionThresholds $thresholds The thresholds.
     * @param integer             $now        The current Unix time.
     *
     * @return array The snapshot: generatedAt, items, sources and checked (how many checks ran).
     */
    public function collect(AttentionThresholds $thresholds, int $now): array
    {
        $time     = new TimeFormatter();
        $snapshot = [];

        $snapshot["generatedAt"] = $time->fromTimestamp($now);
        $snapshot["items"]       = [];
        $snapshot["sources"]     = [];
        $snapshot["checked"]     = 0;

        foreach ($this->evaluators as $evaluator) {
            $startedAt  = microtime(true);
            $source     = $this->runEvaluator($evaluator, $thresholds, $now);
            $evaluation = $source["evaluation"];
            unset($source["evaluation"]);

            $source["durationMs"]   = (int) round(((microtime(true) - $startedAt) * 1000));
            $snapshot["sources"][]  = $source;
            $snapshot["checked"]   += $evaluation->getChecked();
            foreach ($evaluation->getItems() as $item) {
                $snapshot["items"][] = $item->toArray();
            }
        }

        return $snapshot;

    }//end collect()


    /**
     * Runs one evaluator, turning a failure into a warning item.
     *
     * @param AttentionEvaluator  $evaluator  The evaluator.
     * @param AttentionThresholds $thresholds The thresholds.
     * @param integer             $now        The current Unix time.
     *
     * @return array The source's status row, with its evaluation under "evaluation".
     */
    private function runEvaluator(AttentionEvaluator $evaluator, AttentionThresholds $thresholds, int $now): array
    {
        $time   = new TimeFormatter();
        $source = [];

        $source["source"]     = $evaluator->getSource();
        $source["domain"]     = $evaluator->getDomain();
        $source["status"]     = "ok";
        $source["fetchedAt"]  = $time->fromTimestamp($now);
        $source["error"]      = null;
        $source["evaluation"] = new AttentionEvaluation();

        try {
            $source["evaluation"] = $evaluator->evaluate($evaluator->fetch(), $thresholds, $now);
        } catch (SecretsFileNotFoundException $exception) {
            $source["status"] = "not_configured";
            $source["error"]  = $exception->getMessage();
        } catch (\Throwable $exception) {
            $source["status"] = "error";
            $source["error"]  = $exception->getMessage();
            $source["evaluation"]->addItem(new AttentionItem($evaluator->getSource(), $evaluator->getDomain(), AttentionItem::WARNING, sprintf("Could not read %s", $evaluator->getSource()), null, "index.php", sprintf("source:%s", $evaluator->getSource())));
            (new LogStream())->warning("Attention source {source} failed: {reason}", ["source" => $evaluator->getSource(), "reason" => $exception->getMessage()], "attention");
        }

        return $source;

    }//end runEvaluator()


}//end class

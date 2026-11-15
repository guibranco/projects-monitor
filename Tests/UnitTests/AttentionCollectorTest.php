<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionCollector;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluation;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionEvaluator;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionSources;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionThresholds;
use GuiBranco\ProjectsMonitor\Library\SecretsFileNotFoundException;

/**
 * Tests for AttentionCollector and AttentionSources.
 */
class AttentionCollectorTest extends TestCase
{
    private const NOW = 1763221200;


    /**
     * An evaluator whose fetch() returns $data or throws $error.
     *
     * @param string          $source The source name.
     * @param mixed           $data   What fetch() returns.
     * @param \Throwable|null $error  What fetch() throws instead, if anything.
     *
     * @return AttentionEvaluator
     */
    private function createEvaluator(string $source, mixed $data, ?\Throwable $error): AttentionEvaluator
    {
        $evaluator = $this->createMock(AttentionEvaluator::class);
        $evaluator->method("getSource")->willReturn($source);
        $evaluator->method("getDomain")->willReturn("ops");
        if ($error !== null) {
            $evaluator->method("fetch")->willThrowException($error);
        }

        $evaluator->method("fetch")->willReturn($data);
        $evaluator->method("evaluate")->willReturnCallback(
            function ($rows) use ($source) {
                $evaluation = new AttentionEvaluation();
                $evaluation->addHealthy();
                foreach ($rows as $title) {
                    $evaluation->addItem(new AttentionItem($source, "ops", AttentionItem::CRITICAL, $title, null, "index.php", "x:$title"));
                }

                return $evaluation;
            }
        );

        return $evaluator;

    }//end createEvaluator()


    /**
     * Items and checks add up; a missing secrets file is "not_configured"
     * and silent, any other failure is an "error" with a warning item.
     *
     * @return void
     */
    public function testCollect(): void
    {
        $collector = new AttentionCollector(
            [
             $this->createEvaluator("good", ["Down"], null),
             $this->createEvaluator("absent", [], new SecretsFileNotFoundException("File not found: x.secrets.php")),
             $this->createEvaluator("broken", [], new RuntimeException("timeout")),
            ]
        );

        $snapshot = $collector->collect(new AttentionThresholds([]), self::NOW);

        $this->assertSame("2025-11-15T15:40:00Z", $snapshot["generatedAt"]);
        $this->assertSame(3, $snapshot["checked"]);
        $this->assertSame(["Down", "Could not read broken"], array_column($snapshot["items"], "title"));
        $this->assertSame(AttentionItem::WARNING, $snapshot["items"][1]["severity"]);
        $this->assertSame("source:broken", $snapshot["items"][1]["fingerprint"]);
        $this->assertSame(["ok", "not_configured", "error"], array_column($snapshot["sources"], "status"));
        $this->assertSame("timeout", $snapshot["sources"][2]["error"]);
        $this->assertArrayNotHasKey("evaluation", $snapshot["sources"][0]);

    }//end testCollect()


    /**
     * Every source has its own name and a known domain.
     *
     * @return void
     */
    public function testSources(): void
    {
        $evaluators = (new AttentionSources())->getEvaluators();
        $names      = array_map(fn ($evaluator) => $evaluator->getSource(), $evaluators);
        $domains    = array_values(array_unique(array_map(fn ($evaluator) => $evaluator->getDomain(), $evaluators)));

        $this->assertCount(count($evaluators), array_unique($names));
        $this->assertEqualsCanonicalizing(["ops", "pipeline", "errors", "ci", "github", "domains"], $domains);

    }//end testSources()


}//end class

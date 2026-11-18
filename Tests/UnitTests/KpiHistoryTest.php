<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\KpiHistory;

/**
 * Tests for KpiHistory's request handling (no database).
 */
class KpiHistoryTest extends TestCase
{


    /**
     * Only named metrics with finite numbers are kept, up to the per-request limit.
     *
     * @return void
     */
    public function testFilterSamples(): void
    {
        $history = new KpiHistory(null);

        $this->assertSame([], $history->filterSamples(null));
        $this->assertSame([], $history->filterSamples("gauge_chart_queues"));
        $this->assertSame(
            [
             "gauge_chart_queues" => 12.0,
             "gauge_chart_ssh_0_cpu" => 41.5,
            ],
            $history->filterSamples(
                [
                 "gauge_chart_queues"    => 12,
                 "gauge_chart_ssh_0_cpu" => 41.5,
                 "bad name; drop"        => 1,
                 "gauge_chart_text"      => "12",
                 "gauge_chart_null"      => null,
                 "0"                     => 3,
                ]
            )
        );

        $many = [];
        for ($i = 0; $i < 80; $i++) {
            $many["metric_".$i] = $i;
        }

        $this->assertCount(KpiHistory::MAX_SAMPLES_PER_REQUEST, $history->filterSamples($many));

    }//end testFilterSamples()


    /**
     * Metric lists are trimmed, validated and de-duplicated.
     *
     * @return void
     */
    public function testParseMetrics(): void
    {
        $history = new KpiHistory(null);

        $this->assertSame(["a", "b_2"], $history->parseMetrics(" a, b_2,a,,x y,../etc"));
        $this->assertSame([], $history->parseMetrics(""));

    }//end testParseMetrics()


    /**
     * Samples are stored per UTC minute and returned in milliseconds.
     *
     * @return void
     */
    public function testMinuteAndPoint(): void
    {
        $history = new KpiHistory(null);
        $now     = 1763460123;

        $this->assertSame("2025-11-18 10:02:00", $history->minuteOf($now));
        $this->assertSame(["t" => 1763460120000, "v" => 4.5], $history->toPoint("2025-11-18 10:02:00", 4.5));

    }//end testMinuteAndPoint()


}//end class

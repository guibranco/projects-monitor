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
             "gauge_chart_queues"    => 12.0,
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


    /**
     * A connection whose prepared statements all succeed, expecting $prepares statements and $executes executions.
     *
     * @param integer $prepares How many statements are prepared.
     * @param integer $executes How many times they execute.
     *
     * @return mysqli
     */
    private function getConnection(int $prepares, int $executes): mysqli
    {
        $statement = $this->createMock(mysqli_stmt::class);
        $statement->method("bind_param")->willReturn(true);
        $statement->expects($this->exactly($executes))->method("execute")->willReturn(true);

        $connection = $this->createMock(mysqli::class);
        $connection->expects($this->exactly($prepares))->method("prepare")->willReturn($statement);

        return $connection;

    }//end getConnection()


    /**
     * Each sample is upserted, then old samples are deleted.
     *
     * @return void
     */
    public function testRecord(): void
    {
        $history = new KpiHistory($this->getConnection(2, 3), 24);

        $this->assertSame(2, $history->record(array_merge(["a" => 1.0], ["b" => 2.0]), 1763460123));

    }//end testRecord()


    /**
     * Stored samples come back by metric as points; no metric means no query.
     *
     * @return void
     */
    public function testGet(): void
    {
        $rows   = [
                   array_merge(["metric" => "a", "sampled_at" => "2025-11-18 10:01:00"], ["value" => "4.5"]),
                   array_merge(["metric" => "a", "sampled_at" => "2025-11-18 10:02:00"], ["value" => "5"]),
                  ];
        $result = $this->createStub(mysqli_result::class);
        $result->method("fetch_assoc")->willReturnOnConsecutiveCalls(...array_merge($rows, [null]));

        $statement = $this->createStub(mysqli_stmt::class);
        $statement->method("bind_param")->willReturn(true);
        $statement->method("execute")->willReturn(true);
        $statement->method("get_result")->willReturn($result);

        $connection = $this->createMock(mysqli::class);
        $connection->expects($this->once())->method("prepare")->willReturn($statement);

        $history = new KpiHistory($connection, 24);
        $series  = $history->get(["a", "b"], 1763460123);

        $this->assertSame([["t" => 1763460060000, "v" => 4.5], ["t" => 1763460120000, "v" => 5.0]], $series["a"]);
        $this->assertSame([], $series["b"]);
        $this->assertSame([], $history->get([], 1763460123));

    }//end testGet()


    /**
     * The endpoint's answers: GET reads, POST stores, bad input is 400, other methods 405.
     *
     * @return void
     */
    public function testHandle(): void
    {
        $history = new KpiHistory($this->getConnection(2, 2), 24);

        $this->assertSame([200, ["recorded" => 1]], $history->handle("POST", "", "{\"samples\":{\"a\":1}}", 1763460123));
        $this->assertSame(400, $history->handle("POST", "", "not json", 1763460123)[0]);
        $this->assertSame(400, $history->handle("POST", "", "{\"samples\":{\"bad name\":1}}", 1763460123)[0]);
        $this->assertSame(405, $history->handle("DELETE", "", "", 1763460123)[0]);
        $this->assertEquals([200, (object) []], $history->handle("GET", "", "", 1763460123));

    }//end testHandle()


}//end class

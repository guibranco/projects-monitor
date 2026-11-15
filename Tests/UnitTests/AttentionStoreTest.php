<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionStore;

/**
 * Tests for AttentionStore against a mocked mysqli connection.
 */
class AttentionStoreTest extends TestCase
{
    private const NOW = 1763221200;


    /**
     * A query result that yields the given rows.
     *
     * @param array $rows The rows.
     *
     * @return mysqli_result
     */
    private function createResult(array $rows): mysqli_result
    {
        $result = $this->createMock(mysqli_result::class);
        $result->method("fetch_assoc")->willReturnOnConsecutiveCalls(...array_merge($rows, [null]));
        return $result;

    }//end createResult()


    /**
     * A prepared statement whose execute() returns true, expected to run $times times.
     *
     * @param integer $times How many times it must execute.
     *
     * @return mysqli_stmt
     */
    private function createStatement(int $times): mysqli_stmt
    {
        $statement = $this->createMock(mysqli_stmt::class);
        $statement->method("bind_param")->willReturn(true);
        $statement->expects($this->exactly($times))->method("execute")->willReturn(true);
        return $statement;

    }//end createStatement()


    /**
     * States are read by fingerprint, snooze ends as Unix time.
     *
     * @return void
     */
    public function testGetStates(): void
    {
        $rows = [
                 array_merge(["fingerprint" => "hc:a", "state" => "snoozed", "snoozed_until" => "2025-11-15 16:40:00", "acknowledged_since" => null]),
                 array_merge(["fingerprint" => "hc:b", "state" => "acknowledged", "snoozed_until" => null, "acknowledged_since" => "2025-11-15T15:00:00Z"]),
                 array_merge(["fingerprint" => "hc:c", "state" => "snoozed", "snoozed_until" => "garbage", "acknowledged_since" => null]),
                ];

        $connection = $this->createMock(mysqli::class);
        $connection->method("query")->willReturn($this->createResult($rows));

        $states = (new AttentionStore($connection))->getStates();

        $this->assertSame((self::NOW + 3600), $states["hc:a"]["snoozedUntil"]);
        $this->assertSame("acknowledged", $states["hc:b"]["state"]);
        $this->assertSame("2025-11-15T15:00:00Z", $states["hc:b"]["acknowledgedSince"]);
        $this->assertNull($states["hc:b"]["snoozedUntil"]);
        $this->assertNull($states["hc:c"]["snoozedUntil"]);

    }//end testGetStates()


    /**
     * Threshold overrides are read as floats by name.
     *
     * @return void
     */
    public function testGetThresholdOverrides(): void
    {
        $connection = $this->createMock(mysqli::class);
        $connection->method("query")->willReturn($this->createResult([array_merge(["name" => "queue.messages.warning", "value" => "500.00"])]));

        $this->assertSame(array_merge(["queue.messages.warning" => 500.0]), (new AttentionStore($connection))->getThresholdOverrides());

    }//end testGetThresholdOverrides()


    /**
     * Snooze, acknowledge and clear each run one statement.
     *
     * @return void
     */
    public function testWrites(): void
    {
        $connection = $this->createMock(mysqli::class);
        $connection->expects($this->exactly(3))->method("prepare")->willReturn($this->createStatement(3));

        $store = new AttentionStore($connection);

        $this->assertTrue($store->snooze("hc:a", (self::NOW + 60), 1));
        $this->assertTrue($store->acknowledge("hc:b", null, 1));
        $this->assertTrue($store->clear("hc:c"));

    }//end testWrites()


    /**
     * Prune clears ended snoozes and acknowledgements of items no longer
     * raised, and keeps the rest.
     *
     * @return void
     */
    public function testPrune(): void
    {
        $rows = [
                 array_merge(["fingerprint" => "ended", "state" => "snoozed", "snoozed_until" => "2025-11-15 15:00:00", "acknowledged_since" => null]),
                 array_merge(["fingerprint" => "running", "state" => "snoozed", "snoozed_until" => "2025-11-15 18:00:00", "acknowledged_since" => null]),
                 array_merge(["fingerprint" => "gone", "state" => "acknowledged", "snoozed_until" => null, "acknowledged_since" => null]),
                 array_merge(["fingerprint" => "raised", "state" => "acknowledged", "snoozed_until" => null, "acknowledged_since" => null]),
                ];

        $connection = $this->createMock(mysqli::class);
        $connection->method("query")->willReturn($this->createResult($rows));
        $connection->expects($this->exactly(2))->method("prepare")->with("DELETE FROM attention_states WHERE fingerprint = ?")->willReturn($this->createStatement(2));

        (new AttentionStore($connection))->prune(["raised"], self::NOW);

    }//end testPrune()


}//end class

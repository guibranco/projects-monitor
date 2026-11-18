<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\ErrorsInbox;

/**
 * Tests for ErrorsInbox.
 */
class ErrorsInboxTest extends TestCase
{

    /**
     * The time zone before the test.
     *
     * @var string
     */
    private string $timeZone;


    /**
     * Dates are shown in the request's zone; the tests use UTC.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->timeZone = date_default_timezone_get();
        date_default_timezone_set("UTC");

    }//end setUp()


    /**
     * Restores the time zone.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        date_default_timezone_set($this->timeZone);

    }//end tearDown()


    /**
     * A Logger::getGroupedMessageRows() row.
     *
     * @param string  $application The application.
     * @param string  $message     The message.
     * @param integer $count       How many.
     * @param string  $lastSeen    The latest one.
     * @param integer $sampleId    The group's sample.
     *
     * @return array
     */
    private function appRow(string $application, string $message, int $count, string $lastSeen, int $sampleId): array
    {
        return [
                "application" => $application,
                "message"     => $message,
                "count"       => $count,
                "lastSeen"    => $lastSeen,
                "sampleId"    => $sampleId,
               ];

    }//end appRow()


    /**
     * Numbers, UUIDs and hexadecimal ids are replaced and whitespace collapsed.
     *
     * @return void
     */
    public function testNormalizeRemovesVariableParts(): void
    {
        $inbox = new ErrorsInbox();

        $this->assertSame(
            "Order {n} for {uuid} failed at {hex} after {n} s",
            $inbox->normalize("Order 1234 for 3f2504e0-4f89-11d3-9a0c-0305e82c3301 failed\n at 0xdeadbeef   after 1.5 s")
        );
        $this->assertSame("commit {hex} missing", $inbox->normalize("commit 69436db200c2a87d6b65 missing"));
        $this->assertSame("Undefined index \"name\"", $inbox->normalize("Undefined index \"name\""));

    }//end testNormalizeRemovesVariableParts()


    /**
     * Repeats that only differ in their variable parts share a fingerprint; another place does not.
     *
     * @return void
     */
    public function testFingerprint(): void
    {
        $inbox = new ErrorsInbox();

        $first  = $inbox->fingerprint("app", "Billing", "Timeout after 30s calling 10.0.0.1");
        $second = $inbox->fingerprint("app", "Billing", "Timeout after 45s calling 10.0.0.2");

        $this->assertSame($first, $second);
        $this->assertStringStartsWith("app:", $first);
        $this->assertNotSame($first, $inbox->fingerprint("app", "Payments", "Timeout after 30s calling 10.0.0.1"));
        $this->assertNotSame($first, $inbox->fingerprint("php", "Billing", "Timeout after 30s calling 10.0.0.1"));

    }//end testFingerprint()


    /**
     * App message groups that differ only by user agent or numbers merge, adding counts and keeping every sample.
     *
     * @return void
     */
    public function testAppMessagesMerge(): void
    {
        $result = (new ErrorsInbox())->build(
            [
             $this->appRow("Billing", "Invoice 12 not found", 3, "2026-11-18 10:00:00", 7),
             $this->appRow("Billing", "Invoice 99 not found", 2, "2026-11-18 12:00:00", 9),
            ],
            [],
            []
        );

        $this->assertSame(1, $result["total"]);
        $this->assertSame(5, $result["occurrences"]);
        $group = $result["groups"][0];
        $this->assertSame(5, $group["count"]);
        $this->assertSame("2026-11-18 12:00:00", $group["lastSeen"]);
        $this->assertSame([7, 9], $group["actions"]["sampleIds"]);
        $this->assertSame(["app" => 5], $group["sources"]);
        $this->assertSame(1, $result["sources"]["app"]["groups"]);
        $this->assertSame(0, $result["sources"]["php"]["groups"]);

    }//end testAppMessagesMerge()


    /**
     * A PHP error still in an error_log file groups with the same error already in the database.
     *
     * @return void
     */
    public function testErrorLogEntryJoinsIngestedError(): void
    {
        $result = (new ErrorsInbox())->build(
            [],
            [
             [
              "error_log_path" => "/home/user/public_html/api/error_log",
              "date"           => "2026-11-18 09:00:00",
              "error"          => "PHP Warning:  Undefined variable \$x in /home/user/public_html/api/a.php on line 12",
              "file"           => "/home/user/public_html/api/a.php",
              "line"           => 12,
             ],
            ],
            [
             [
              "date"           => "18-Nov-2026 11:30:00 UTC",
              "directory"      => "public_html/api",
              "multilineError" => "PHP Warning:  Undefined variable \$x in /home/user/public_html/api/a.php on line 12\nStack trace:\n#0 {main}",
              "file"           => "public_html/api/a.php",
              "line"           => "12",
             ],
            ]
        );

        $this->assertSame(1, $result["total"]);
        $group = $result["groups"][0];
        $this->assertSame(["php" => 1, "error_log" => 1], $group["sources"]);
        $this->assertSame("public_html/api/a.php:12", $group["origin"]);
        $this->assertSame("2026-11-18 11:30:00", $group["lastSeen"]);
        $this->assertSame(["/home/user/public_html/api/error_log"], $group["actions"]["paths"]);
        $this->assertSame(["public_html/api"], $group["actions"]["directories"]);
        $this->assertStringNotContainsString("Stack trace", $group["message"]);

    }//end testErrorLogEntryJoinsIngestedError()


    /**
     * Groups come most recent first, then most frequent; unread sources are reported.
     *
     * @return void
     */
    public function testOrderAndUnreadSources(): void
    {
        $result = (new ErrorsInbox())->build(
            [
             $this->appRow("A", "old", 50, "2026-11-17 10:00:00", 1),
             $this->appRow("A", "new rare", 1, "2026-11-18 10:00:00", 2),
             $this->appRow("B", "new frequent", 9, "2026-11-18 10:00:00", 3),
            ],
            [],
            [],
            ["error_log" => "cPanel unreachable"]
        );

        $this->assertSame(["new frequent", "new rare", "old"], array_column($result["groups"], "message"));
        $this->assertSame("cPanel unreachable", $result["sources"]["error_log"]["error"]);
        $this->assertNull($result["sources"]["app"]["error"]);

    }//end testOrderAndUnreadSources()


    /**
     * collect() reads every source, reports the ones that fail and returns the error_log files.
     *
     * @return void
     */
    public function testCollect(): void
    {
        $inbox  = new ErrorsInbox();
        $result = $inbox->collect(
            fn () => [$this->appRow("Billing", "Invoice 1 not found", 2, "2026-11-18 10:00:00", 4)],
            function () {
                throw new RuntimeException("database down");
            },
            fn () => array_merge(["entries" => []], ["files" => [["Directory", "Size", "Creation time"]]])
        );

        $this->assertSame(1, $result["total"]);
        $this->assertSame("database down", $result["sources"]["php"]["error"]);
        $this->assertNull($result["sources"]["error_log"]["error"]);
        $this->assertSame([["Directory", "Size", "Creation time"]], $result["errorLogFiles"]);

    }//end testCollect()


}//end class

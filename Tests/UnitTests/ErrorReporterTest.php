<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\ErrorReporter;
use GuiBranco\ProjectsMonitor\Library\Logger;
use GuiBranco\ProjectsMonitor\Library\LogStream;
use GuiBranco\ProjectsMonitor\Library\RequestException;

/**
 * Tests that ErrorReporter sends every report to LogStream and to the
 * messages table, and that one failing sink never stops the other.
 */
class ErrorReporterTest extends TestCase
{
    /**
     * Trace id the LogStream stub answers.
     */
    private const TRACE_ID = "trace-1";

    /**
     * The entries sent to LogStream: level, message, context and category.
     *
     * @var array
     */
    private $entries = [];

    /**
     * The errors stored in the messages table: message, details and exception.
     *
     * @var array
     */
    private $rows = [];

    /**
     * The PHP error log setting before the test.
     *
     * @var string|false
     */
    private $errorLog = false;


    /**
     * Sends the PHP error log of the failing-sink tests nowhere.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->entries  = [];
        $this->rows     = [];
        $this->errorLog = ini_set("error_log", "/dev/null");

    }//end setUp()


    /**
     * Restores the PHP error log.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        ini_set("error_log", (string) $this->errorLog);

    }//end tearDown()


    /**
     * Builds a reporter whose sinks record what they receive.
     *
     * @param boolean $logStreamFails Whether LogStream throws.
     * @param boolean $loggerFails    Whether creating the Logger throws.
     *
     * @return ErrorReporter The reporter.
     */
    private function buildReporter(bool $logStreamFails, bool $loggerFails): ErrorReporter
    {
        $logStream = $this->createStub(LogStream::class);
        $logStream->method("getTraceId")->willReturn(self::TRACE_ID);
        $logStream->method("log")->willReturnCallback(
            function (string $level, string $message, ?array $context, string $category) use ($logStreamFails): void {
                if ($logStreamFails === true) {
                    throw new RuntimeException("unreachable");
                }

                $this->entries[] = compact("level", "message", "context", "category");
            }
        );

        $logger = $this->createStub(Logger::class);
        $logger->method("logError")->willReturnCallback(
            function (string $message, array $details, ?\Throwable $exception, ?array $origin): bool {
                $this->rows[] = compact("message", "details", "exception", "origin");
                return true;
            }
        );

        $reporter = new ErrorReporter();
        $reporter->setLogStream($logStream);
        $reporter->setLoggerFactory(
            function () use ($logger, $loggerFails): Logger {
                if ($loggerFails === true) {
                    throw new RuntimeException("database down");
                }

                return $logger;
            }
        );

        return $reporter;

    }//end buildReporter()


    /**
     * A report reaches both sinks, with the details the inbox shows.
     *
     * @return void
     */
    public function testReportsToLogStreamAndMessagesTable(): void
    {
        $exception = new RequestException("Code: 503 - Error: busy");
        $context   = [];
        $context["reason"]    = "busy";
        $context["exception"] = $exception;

        $this->buildReporter(false, false)->error("Releases failed to load: {reason}", $context, "webhooks");

        $this->assertCount(1, $this->entries);
        $this->assertSame("error", $this->entries[0]["level"]);
        $this->assertSame("Releases failed to load: {reason}", $this->entries[0]["message"]);
        $this->assertSame($context, $this->entries[0]["context"]);
        $this->assertSame("webhooks", $this->entries[0]["category"]);

        $this->assertCount(1, $this->rows);
        $this->assertSame("Releases failed to load: busy", $this->rows[0]["message"]);
        $this->assertSame($exception, $this->rows[0]["exception"]);

        $details = $this->rows[0]["details"];
        $this->assertSame("error", $details["level"]);
        $this->assertSame("webhooks", $details["category"]);
        $this->assertSame("Releases failed to load: {reason}", $details["message_template"]);
        $this->assertSame(self::TRACE_ID, $details["trace_id"]);
        $this->assertSame(RequestException::class, $details["context"]["exception"]["class"]);

    }//end testReportsToLogStreamAndMessagesTable()


    /**
     * Warning and critical keep their level in both sinks.
     *
     * @return void
     */
    public function testWarningAndCriticalLevels(): void
    {
        $reporter = $this->buildReporter(false, false);
        $reporter->warning("Disk at {percent}%", ["percent" => 91], "server");
        $reporter->critical("Down", [], "server");

        $this->assertSame("warning", $this->entries[0]["level"]);
        $this->assertSame("critical", $this->entries[1]["level"]);
        $this->assertSame("Disk at 91%", $this->rows[0]["message"]);
        $this->assertSame("critical", $this->rows[1]["details"]["level"]);

    }//end testWarningAndCriticalLevels()


    /**
     * A PHP error's file and line go to the messages table as its origin; other reports have none.
     *
     * @return void
     */
    public function testPassesPhpErrorOrigin(): void
    {
        $context           = [];
        $context["file"]   = "/x/github.php";
        $context["line"]   = 13;
        $context["reason"] = "Undefined array key";

        $reporter = $this->buildReporter(false, false);
        $reporter->warning("PHP Warning in {file}:{line}: {reason}", $context, "api");
        $reporter->error("No location", [], "api");

        $this->assertSame("/x/github.php", $this->rows[0]["origin"]["file"]);
        $this->assertSame(13, $this->rows[0]["origin"]["line"]);
        $this->assertNull($this->rows[1]["origin"]);

    }//end testPassesPhpErrorOrigin()


    /**
     * The messages table failing does not stop LogStream, nor throw.
     *
     * @return void
     */
    public function testMessagesTableFailureDoesNotStopLogStream(): void
    {
        $this->buildReporter(false, true)->critical("Something broke", [], "api");

        $this->assertCount(1, $this->entries);
        $this->assertCount(0, $this->rows);

    }//end testMessagesTableFailureDoesNotStopLogStream()


    /**
     * LogStream failing does not stop the messages table, nor throw.
     *
     * @return void
     */
    public function testLogStreamFailureDoesNotStopMessagesTable(): void
    {
        $this->buildReporter(true, false)->error("Webhooks API down", [], "webhooks");

        $this->assertCount(0, $this->entries);
        $this->assertCount(1, $this->rows);
        $this->assertSame("Webhooks API down", $this->rows[0]["message"]);

    }//end testLogStreamFailureDoesNotStopMessagesTable()


    /**
     * A report made while another one is in progress is skipped, not recursed into.
     *
     * @return void
     */
    public function testNestedReportIsSkipped(): void
    {
        $reporter  = $this->buildReporter(false, false);
        $logStream = $this->createStub(LogStream::class);
        $logStream->method("log")->willReturnCallback(
            function () use ($reporter): void {
                $reporter->error("Inner", [], "api");
            }
        );
        $reporter->setLogStream($logStream);

        $reporter->error("Outer", [], "api");

        $this->assertCount(1, $this->rows);
        $this->assertSame("Outer", $this->rows[0]["message"]);

    }//end testNestedReportIsSkipped()


    /**
     * A failing trace id leaves it empty in the details.
     *
     * @return void
     */
    public function testBuildDetailsWithoutTraceId(): void
    {
        $logStream = $this->createStub(LogStream::class);
        $logStream->method("getTraceId")->willThrowException(new RuntimeException("no trace"));
        $reporter = new ErrorReporter();
        $reporter->setLogStream($logStream);

        $details = $reporter->buildDetails("error", "Failed", [], "api");

        $this->assertNull($details["trace_id"]);

    }//end testBuildDetailsWithoutTraceId()


}//end class

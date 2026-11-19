<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\ErrorHandler;
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
     * A LogStream whose entries are recorded instead of sent.
     *
     * @param array $entries Receives [level, message, context, category] per entry.
     *
     * @return LogStream The stub.
     */
    private function createLogStream(array &$entries): LogStream
    {
        $logStream = $this->getMockBuilder(LogStream::class)->onlyMethods(["log", "getTraceId"])->getMock();
        $logStream->method("getTraceId")->willReturn("trace-1");
        $logStream->method("log")->willReturnCallback(
            function (string $level, string $message, ?array $context, string $category) use (&$entries): void {
                $entries[] = [$level, $message, $context, $category];
            }
        );

        return $logStream;

    }//end createLogStream()


    /**
     * A Logger whose stored errors are recorded instead of inserted.
     *
     * @param array $rows Receives [message, details, exception] per error.
     *
     * @return Logger The stub.
     */
    private function createLogger(array &$rows): Logger
    {
        $logger = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->onlyMethods(["logError"])->getMock();
        $logger->method("logError")->willReturnCallback(
            function (string $message, array $details, ?\Throwable $exception) use (&$rows): bool {
                $rows[] = [$message, $details, $exception];
                return true;
            }
        );

        return $logger;

    }//end createLogger()


    public function testReportsToLogStreamAndMessagesTable(): void
    {
        $entries   = [];
        $rows      = [];
        $exception = new RequestException("Code: 503 - Error: busy");
        $reporter  = new ErrorReporter($this->createLogStream($entries), function () use (&$rows): Logger {
            return $this->createLogger($rows);
        });

        $reporter->error("Releases failed to load: {reason}", ["reason" => "busy", "exception" => $exception], "webhooks");

        $this->assertSame([["error", "Releases failed to load: {reason}", ["reason" => "busy", "exception" => $exception], "webhooks"]], $entries);
        $this->assertCount(1, $rows);
        $this->assertSame("Releases failed to load: busy", $rows[0][0]);
        $this->assertSame($exception, $rows[0][2]);
        $this->assertSame("error", $rows[0][1]["level"]);
        $this->assertSame("webhooks", $rows[0][1]["category"]);
        $this->assertSame("Releases failed to load: {reason}", $rows[0][1]["message_template"]);
        $this->assertSame("trace-1", $rows[0][1]["trace_id"]);
        $this->assertSame(RequestException::class, $rows[0][1]["context"]["exception"]["class"]);
        $this->assertSame("Code: 503 - Error: busy", $rows[0][1]["context"]["exception"]["message"]);

    }//end testReportsToLogStreamAndMessagesTable()


    public function testMessagesTableFailureDoesNotStopLogStream(): void
    {
        $entries  = [];
        $reporter = new ErrorReporter(
            $this->createLogStream($entries),
            function (): Logger {
                throw new \RuntimeException("database down");
            }
        );

        $previous = ini_set("error_log", "/dev/null");
        $reporter->critical("Something broke", [], "api");
        ini_set("error_log", (string) $previous);

        $this->assertCount(1, $entries);
        $this->assertSame("critical", $entries[0][0]);

    }//end testMessagesTableFailureDoesNotStopLogStream()


    public function testLogStreamFailureDoesNotStopMessagesTable(): void
    {
        $rows      = [];
        $logStream = $this->getMockBuilder(LogStream::class)->onlyMethods(["log", "getTraceId"])->getMock();
        $logStream->method("log")->willThrowException(new \RuntimeException("unreachable"));
        $logStream->method("getTraceId")->willReturn("trace-2");
        $reporter = new ErrorReporter($logStream, function () use (&$rows): Logger {
            return $this->createLogger($rows);
        });

        $previous = ini_set("error_log", "/dev/null");
        $reporter->warning("Disk at {percent}%", ["percent" => 91], "server");
        ini_set("error_log", (string) $previous);

        $this->assertCount(1, $rows);
        $this->assertSame("Disk at 91%", $rows[0][0]);

    }//end testLogStreamFailureDoesNotStopMessagesTable()


    public function testRenderReplacesNestedAndKeepsUnknownPlaceholders(): void
    {
        $entries  = [];
        $reporter = new ErrorReporter($this->createLogStream($entries), fn () => null);

        $rendered = $reporter->render(
            "{service} {request.method} {missing} {flag} {nothing}",
            ["service" => "Webhooks API", "request" => ["method" => "GET"], "flag" => false, "nothing" => null]
        );

        $this->assertSame("Webhooks API GET {missing} false null", $rendered);

    }//end testRenderReplacesNestedAndKeepsUnknownPlaceholders()


    public function testSummarizeTurnsHtmlErrorPageIntoText(): void
    {
        $html = "<!DOCTYPE html>\n<html><head><title> 503 Service Unavailable\n</title><style>body{color:#444}</style></head>"
            ."<body><h1>503</h1>\n<h2>Service Unavailable</h2><p>The server is temporarily busy, try again later!</p></body></html>";

        $this->assertSame(
            "503 Service Unavailable The server is temporarily busy, try again later!",
            ErrorHandler::summarize($html)
        );
        $this->assertSame("{\"error\": \"forbidden\"}", ErrorHandler::summarize("{\"error\": \"forbidden\"}"));

    }//end testSummarizeTurnsHtmlErrorPageIntoText()


    public function testHandleErrorReportsEachLocationOncePerRequest(): void
    {
        $reported = [];
        $reporter = $this->getMockBuilder(ErrorReporter::class)->disableOriginalConstructor()->onlyMethods(["report"])->getMock();
        $reporter->method("report")->willReturnCallback(
            function (string $level, string $message, array $context) use (&$reported): void {
                $reported[] = [$level, $message, $context];
            }
        );
        $handler = new ErrorHandler("api", "/api/v1/github.php", $reporter);
        $previous = error_reporting(E_ALL);

        $this->assertFalse($handler->handleError(E_WARNING, "Undefined array key \"data\"", "/x/github.php", 13));
        $this->assertFalse($handler->handleError(E_WARNING, "Undefined array key \"data\"", "/x/github.php", 13));
        $this->assertFalse($handler->handleError(E_WARNING, "Undefined array key \"core\"", "/x/github.php", 14));
        error_reporting(0);
        $this->assertFalse($handler->handleError(E_WARNING, "Silenced with @", "/x/github.php", 15));
        error_reporting($previous);

        $this->assertCount(2, $reported);
        $this->assertSame("warning", $reported[0][0]);
        $this->assertSame("Warning", $reported[0][2]["level_name"]);
        $this->assertSame(13, $reported[0][2]["line"]);
        $this->assertSame("/api/v1/github.php", $reported[0][2]["script"]);

    }//end testHandleErrorReportsEachLocationOncePerRequest()


}//end class

<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\ErrorHandler;
use GuiBranco\ProjectsMonitor\Library\ErrorReporter;
use GuiBranco\ProjectsMonitor\Library\RequestException;

/**
 * Tests what ErrorHandler reports for warnings, uncaught exceptions and
 * fatal errors. register() is not called: it would replace PHPUnit's own handlers.
 */
class ErrorHandlerTest extends TestCase
{
    /**
     * Script the handler reports for.
     */
    private const SCRIPT = "/api/v1/github.php";

    /**
     * The reports received: level, message and context.
     *
     * @var array
     */
    private $reports = [];

    /**
     * The PHP error log setting before the test.
     *
     * @var string|false
     */
    private $errorLog = false;


    /**
     * Sends the PHP error log of the tests nowhere.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->reports  = [];
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
     * Builds a handler whose reporter records the reports.
     *
     * @return ErrorHandler The handler.
     */
    private function buildHandler(): ErrorHandler
    {
        $reporter = $this->createStub(ErrorReporter::class);
        $reporter->method("warning")->willReturnCallback(
            function (string $message, array $context): void {
                $level           = "warning";
                $this->reports[] = compact("level", "message", "context");
            }
        );
        $reporter->method("critical")->willReturnCallback(
            function (string $message, array $context): void {
                $level           = "critical";
                $this->reports[] = compact("level", "message", "context");
            }
        );

        $handler = new ErrorHandler("api", self::SCRIPT);
        $handler->setReporter($reporter);

        return $handler;

    }//end buildHandler()


    /**
     * A warning is reported once per location and request, then left to PHP.
     *
     * @return void
     */
    public function testHandleErrorReportsEachLocationOncePerRequest(): void
    {
        $handler  = $this->buildHandler();
        $previous = error_reporting(E_ALL);

        try {
            $this->assertFalse($handler->handleError(E_WARNING, "Undefined array key \"data\"", "/x/github.php", 13));
            $this->assertFalse($handler->handleError(E_WARNING, "Undefined array key \"data\"", "/x/github.php", 13));
            $this->assertFalse($handler->handleError(E_WARNING, "Undefined array key \"core\"", "/x/github.php", 14));
        } finally {
            error_reporting($previous);
        }

        $this->assertCount(2, $this->reports);
        $this->assertSame("warning", $this->reports[0]["level"]);
        $this->assertSame("PHP {level_name} in {file}:{line}: {reason}", $this->reports[0]["message"]);
        $this->assertSame("Warning", $this->reports[0]["context"]["level_name"]);
        $this->assertSame(13, $this->reports[0]["context"]["line"]);
        $this->assertSame(self::SCRIPT, $this->reports[0]["context"]["script"]);

    }//end testHandleErrorReportsEachLocationOncePerRequest()


    /**
     * A warning silenced with @ is not reported.
     *
     * @return void
     */
    public function testHandleErrorIgnoresSilencedWarnings(): void
    {
        $handler  = $this->buildHandler();
        $previous = error_reporting(0);

        try {
            $this->assertFalse($handler->handleError(E_WARNING, "Silenced", "/x/silenced.php", 1));
        } finally {
            error_reporting($previous);
        }

        $this->assertCount(0, $this->reports);

    }//end testHandleErrorIgnoresSilencedWarnings()


    /**
     * An uncaught exception is reported as critical, with its HTML body as text.
     *
     * @return void
     */
    public function testHandleExceptionReportsCritical(): void
    {
        $exception = new RequestException("Code: 503 - Error: <html><body><p>busy</p></body></html>");
        $this->expectOutputRegex('/"error":"Internal error"/');

        $handler = $this->buildHandler();
        $handler->handleException($exception);

        $uncaught            = [];
        $uncaught["type"]    = E_ERROR;
        $uncaught["message"] = "Uncaught RequestException: busy";
        $uncaught["file"]    = "/x/releases.php";
        $uncaught["line"]    = 9;
        $handler->reportFatal($uncaught);

        $this->assertCount(1, $this->reports);
        $this->assertSame("critical", $this->reports[0]["level"]);
        $this->assertSame(RequestException::class, $this->reports[0]["context"]["exception_class"]);
        $this->assertSame("Code: 503 - Error: busy", $this->reports[0]["context"]["reason"]);
        $this->assertSame($exception, $this->reports[0]["context"]["exception"]);

    }//end testHandleExceptionReportsCritical()


    /**
     * Without a fatal error the shutdown function reports nothing.
     *
     * @return void
     */
    public function testHandleShutdownIgnoresNonFatalErrors(): void
    {
        error_clear_last();
        @trigger_error("Only a notice", E_USER_NOTICE);

        $this->buildHandler()->handleShutdown();

        $this->assertCount(0, $this->reports);

    }//end testHandleShutdownIgnoresNonFatalErrors()


    /**
     * A fatal error, or an uncaught exception on the CLI, is reported as critical.
     *
     * @return void
     */
    public function testReportFatal(): void
    {
        $handler = $this->buildHandler();

        $fatal            = [];
        $fatal["type"]    = E_ERROR;
        $fatal["message"] = "Allowed memory size exhausted";
        $fatal["file"]    = "/x/releases.php";
        $fatal["line"]    = 9;
        $handler->reportFatal($fatal);

        $uncaught            = $fatal;
        $uncaught["message"] = "Uncaught RequestException: busy in /x/Webhooks.php:78\nStack trace:\n#0 {main}";
        $handler->reportFatal($uncaught);

        $this->assertCount(2, $this->reports);
        $this->assertSame("critical", $this->reports[0]["level"]);
        $this->assertSame("Fatal error", $this->reports[0]["context"]["level_name"]);
        $this->assertSame("Allowed memory size exhausted", $this->reports[0]["context"]["reason"]);
        $this->assertSame("Uncaught RequestException: busy in /x/Webhooks.php:78", $this->reports[1]["context"]["reason"]);
        $this->assertSame($uncaught["message"], $this->reports[1]["context"]["error_message"]);

    }//end testReportFatal()


    /**
     * Every level has a readable name.
     *
     * @return void
     */
    public function testGetLevelName(): void
    {
        $handler = $this->buildHandler();

        $this->assertSame("Warning", $handler->getLevelName(E_USER_WARNING));
        $this->assertSame("Notice", $handler->getLevelName(E_NOTICE));
        $this->assertSame("Deprecated", $handler->getLevelName(E_DEPRECATED));
        $this->assertSame("Recoverable error", $handler->getLevelName(E_RECOVERABLE_ERROR));
        $this->assertSame("Parse error", $handler->getLevelName(E_PARSE));
        $this->assertSame("Fatal error", $handler->getLevelName(E_ERROR));
        $this->assertSame("Error 0", $handler->getLevelName(0));

    }//end testGetLevelName()


}//end class

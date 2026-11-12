<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\LogContext;

/**
 * Tests the ambient context LogStream adds to every entry.
 */
class LogContextTest extends TestCase
{
    private const UUID_PATTERN = "/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/";

    /**
     * The context builder under test.
     *
     * @var LogContext
     */
    private $logContext;


    /**
     * Creates the context builder.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->logContext = new LogContext();

    }//end setUp()


    /**
     * A safe X-Request-Id wins, then a safe X-Trace-Id.
     *
     * @return void
     */
    public function testResolveTraceIdUsesSafeHeaders(): void
    {
        $this->assertSame("abc-123_def.456", $this->logContext->resolveTraceId(["HTTP_X_REQUEST_ID" => "abc-123_def.456"]));
        $this->assertSame("trace-0001", $this->logContext->resolveTraceId(["HTTP_X_TRACE_ID" => "trace-0001"]));

    }//end testResolveTraceIdUsesSafeHeaders()


    /**
     * An unsafe header is ignored and a new UUID v4 is generated each time.
     *
     * @return void
     */
    public function testResolveTraceIdGeneratesUuidForUnsafeHeader(): void
    {
        $traceId = $this->logContext->resolveTraceId(["HTTP_X_REQUEST_ID" => "bad id\r\nX-Injected: 1"]);

        $this->assertMatchesRegularExpression(self::UUID_PATTERN, $traceId);
        $this->assertNotSame($traceId, $this->logContext->resolveTraceId([]));

    }//end testResolveTraceIdGeneratesUuidForUnsafeHeader()


    /**
     * A web request gets its method, path without query string, script, host,
     * client IP, user agent and user, and no process data.
     *
     * @return void
     */
    public function testBuildAddsRequestDataForWebRequests(): void
    {
        $server = [];
        $server["REQUEST_METHOD"]  = "POST";
        $server["REQUEST_URI"]     = "/api/v1/workflow-runs/retry?token=secret";
        $server["SCRIPT_NAME"]     = "/api/v1/workflow-runs-retry.php";
        $server["HTTP_HOST"]       = "monitor.example.com";
        $server["REMOTE_ADDR"]     = "203.0.113.7";
        $server["HTTP_USER_AGENT"] = str_repeat("a", 300);

        $context = $this->logContext->build(["workflow_run_id" => 42], $server, 7, null, "fpm-fcgi");

        $this->assertSame(42, $context["workflow_run_id"]);
        $this->assertSame("POST", $context["request"]["method"]);
        $this->assertSame("/api/v1/workflow-runs/retry", $context["request"]["path"]);
        $this->assertSame("/api/v1/workflow-runs-retry.php", $context["request"]["script"]);
        $this->assertSame("monitor.example.com", $context["request"]["host"]);
        $this->assertSame("203.0.113.7", $context["request"]["client_ip"]);
        $this->assertSame(257, mb_strlen($context["request"]["user_agent"]));
        $this->assertSame(7, $context["request"]["user_id"]);
        $this->assertArrayNotHasKey("process", $context);
        $this->assertSame(["method" => "GET"], $this->logContext->getRequestContext(["REQUEST_METHOD" => "GET"], null));

    }//end testBuildAddsRequestDataForWebRequests()


    /**
     * A CLI run gets its script and arguments, and the runtime data.
     *
     * @return void
     */
    public function testBuildAddsProcessAndRuntimeDataForCli(): void
    {
        $server   = [];
        $argv     = [];
        $argv[]   = "/srv/Src/Worker/ProcessErrorLogs.php";
        $argv[]   = "--verbose";
        $server["argv"] = $argv;
        $server["REQUEST_TIME_FLOAT"] = (microtime(true) - 1.5);

        $context = $this->logContext->build(null, $server, 7, null, "cli");

        $this->assertSame("ProcessErrorLogs.php", $context["process"]["script"]);
        $this->assertSame(1, $context["process"]["arguments"]);
        $this->assertArrayNotHasKey("request", $context);
        $this->assertSame("cli", $context["runtime"]["sapi"]);
        $this->assertSame(PHP_VERSION, $context["runtime"]["php_version"]);
        $this->assertSame(getmypid(), $context["runtime"]["pid"]);
        $this->assertSame(gethostname(), $context["runtime"]["hostname"]);
        $this->assertIsFloat($context["runtime"]["memory_peak_mb"]);
        $this->assertGreaterThanOrEqual(1500, $context["runtime"]["elapsed_ms"]);
        $this->assertArrayNotHasKey("elapsed_ms", $this->logContext->getRuntimeContext([], "cli"));

    }//end testBuildAddsProcessAndRuntimeDataForCli()


    /**
     * Caller keys come first and win over ambient keys; the call site is added.
     *
     * @return void
     */
    public function testBuildKeepsCallerKeysFirst(): void
    {
        $source = ["file" => "Library/Webhooks.php"];

        $context = $this->logContext->build(["host" => "vinhedo1", "runtime" => "mine"], [], null, $source, "cli");

        $this->assertSame(["host", "runtime", "process", "source"], array_keys($context));
        $this->assertSame("mine", $context["runtime"]);
        $this->assertSame($source, $context["source"]);

    }//end testBuildKeepsCallerKeysFirst()


    /**
     * Throwables are expanded, with the previous one, and their trace has no call arguments.
     *
     * @return void
     */
    public function testBuildExpandsThrowablesWithoutArguments(): void
    {
        $exception = $this->throwWith("secret-value");

        $context = $this->logContext->build(["exception" => $exception], [], null, null, "cli");

        $this->assertSame("RuntimeException", $context["exception"]["type"]);
        $this->assertSame("outer", $context["exception"]["message"]);
        $this->assertSame(12, $context["exception"]["code"]);
        $this->assertSame(__FILE__, $context["exception"]["file"]);
        $this->assertIsInt($context["exception"]["line"]);
        $this->assertStringContainsString("LogContextTest::throwWith()", $context["exception"]["trace"]);
        $this->assertStringNotContainsString("secret-value", $context["exception"]["trace"]);
        $this->assertSame("InvalidArgumentException", $context["exception"]["previous"]["type"]);
        $this->assertSame("inner", $context["exception"]["previous"]["message"]);
        $this->assertArrayNotHasKey("previous", $this->logContext->describeThrowable(new RuntimeException("alone")));

    }//end testBuildExpandsThrowablesWithoutArguments()


    /**
     * The call site is the first frame outside the logging files, named after its enclosing method.
     *
     * @return void
     */
    public function testGetCallSiteSkipsLoggingFrames(): void
    {
        $root = dirname((new ReflectionClass(LogContext::class))->getFileName(), 2);

        $logging = [];
        $logging["file"]     = "/logging/LogStream.php";
        $logging["function"] = "write";

        $caller = [];
        $caller["file"]     = "{$root}/Library/Webhooks.php";
        $caller["line"]     = 54;
        $caller["function"] = "debug";

        $enclosing = [];
        $enclosing["file"]     = "{$root}/api/v1/webhooks.php";
        $enclosing["function"] = "doRequest";
        $enclosing["class"]    = "GuiBranco\\ProjectsMonitor\\Library\\Webhooks";

        $frames = [
                   $logging,
                   $caller,
                   $enclosing,
                  ];

        $callSite = $this->logContext->getCallSite($frames, ["/logging/LogStream.php"]);

        $this->assertSame(["file" => "Library/Webhooks.php", "line" => 54, "function" => "Webhooks::doRequest"], $callSite);
        $this->assertNull($this->logContext->getCallSite([["function" => "internal"]], []));

    }//end testGetCallSiteSkipsLoggingFrames()


    /**
     * Top-level code is {main}, plain functions keep their name.
     *
     * @return void
     */
    public function testGetFunctionNameNamesEveryKindOfFrame(): void
    {
        $this->assertSame("{main}", $this->logContext->getFunctionName([]));
        $this->assertSame("workerLog", $this->logContext->getFunctionName(["function" => "workerLog"]));
        $this->assertSame("Plain::run", $this->logContext->getFunctionName(["function" => "run", "class" => "Plain"]));

    }//end testGetFunctionNameNamesEveryKindOfFrame()


    /**
     * Long values are cut and marked, short ones kept; paths outside Src are kept as they are.
     *
     * @return void
     */
    public function testTruncateAndRelativePathKeepWhatFits(): void
    {
        $this->assertSame("abc…", $this->logContext->truncate("abcdef", 3));
        $this->assertSame("abc", $this->logContext->truncate("abc", 3));
        $this->assertSame("/elsewhere/worker.php", $this->logContext->getRelativePath("/elsewhere/worker.php"));

    }//end testTruncateAndRelativePathKeepWhatFits()


    /**
     * Throws an exception with a previous one from a call that has an argument.
     *
     * @param string $secret The argument that must not appear in the trace.
     *
     * @return Throwable The exception.
     */
    private function throwWith(string $secret): Throwable
    {
        try {
            throw new RuntimeException("outer", 12, new InvalidArgumentException("inner"));
        } catch (RuntimeException $exception) {
            $this->assertNotSame("", $secret);
            return $exception;
        }

    }//end throwWith()


}//end class

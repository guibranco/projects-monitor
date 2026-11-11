<?php

declare(strict_types=1);

use GuiBranco\ProjectsMonitor\Library\LogStream;
use PHPUnit\Framework\TestCase;

class LogStreamTest extends TestCase
{
    // -------------------------------------------------------------------------
    // resolveTraceId
    // -------------------------------------------------------------------------

    public function testResolveTraceIdUsesValidRequestIdHeader(): void
    {
        $this->assertSame('abc-123_def.456', LogStream::resolveTraceId(['HTTP_X_REQUEST_ID' => 'abc-123_def.456']));
    }

    public function testResolveTraceIdFallsBackToTraceIdHeader(): void
    {
        $this->assertSame('trace-0001', LogStream::resolveTraceId(['HTTP_X_TRACE_ID' => 'trace-0001']));
    }

    public function testResolveTraceIdIgnoresUnsafeHeaderAndGeneratesUuid(): void
    {
        $traceId = LogStream::resolveTraceId(['HTTP_X_REQUEST_ID' => "bad id\r\nX-Injected: 1"]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $traceId
        );
    }

    public function testResolveTraceIdGeneratesDifferentUuids(): void
    {
        $this->assertNotSame(LogStream::resolveTraceId([]), LogStream::resolveTraceId([]));
    }

    public function testGetTraceIdIsStableWithinProcess(): void
    {
        $this->assertSame(LogStream::getTraceId(), LogStream::getTraceId());
    }

    // -------------------------------------------------------------------------
    // buildContext
    // -------------------------------------------------------------------------

    public function testBuildContextAddsRequestDataForWebRequests(): void
    {
        $server = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/workflow-runs/retry?token=secret',
            'SCRIPT_NAME' => '/api/v1/workflow-runs-retry.php',
            'HTTP_HOST' => 'monitor.example.com',
            'REMOTE_ADDR' => '203.0.113.7',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'HTTP_REFERER' => 'https://monitor.example.com/index.php?x=1',
            'REQUEST_TIME_FLOAT' => microtime(true),
        ];

        $context = LogStream::buildContext(['workflow_run_id' => 42], $server, ['user_id' => 7], null, 'fpm-fcgi');

        $this->assertSame(42, $context['workflow_run_id']);
        $this->assertSame('POST', $context['request']['method']);
        $this->assertSame('/api/v1/workflow-runs/retry', $context['request']['path'], 'query string must be dropped');
        $this->assertSame('/api/v1/workflow-runs-retry.php', $context['request']['script']);
        $this->assertSame('monitor.example.com', $context['request']['host']);
        $this->assertSame('203.0.113.7', $context['request']['client_ip']);
        $this->assertSame('Mozilla/5.0', $context['request']['user_agent']);
        $this->assertSame('https://monitor.example.com/index.php', $context['request']['referer']);
        $this->assertSame(7, $context['request']['user_id']);
        $this->assertArrayNotHasKey('process', $context);
    }

    public function testBuildContextOmitsMissingRequestFields(): void
    {
        $context = LogStream::buildContext(null, ['REQUEST_METHOD' => 'GET'], null, null, 'fpm-fcgi');

        $this->assertSame(['method' => 'GET'], $context['request']);
    }

    public function testBuildContextAddsProcessDataForCli(): void
    {
        $server = ['argv' => ['/srv/Src/Worker/ProcessErrorLogs.php', '--verbose']];

        $context = LogStream::buildContext(null, $server, null, null, 'cli');

        $this->assertSame(['script' => 'ProcessErrorLogs.php', 'arguments' => 1], $context['process']);
        $this->assertArrayNotHasKey('request', $context);
    }

    public function testBuildContextAddsRuntimeData(): void
    {
        $context = LogStream::buildContext(null, ['REQUEST_TIME_FLOAT' => microtime(true) - 1.5], null, null, 'cli');

        $this->assertSame('cli', $context['runtime']['sapi']);
        $this->assertSame(PHP_VERSION, $context['runtime']['php_version']);
        $this->assertSame(getmypid(), $context['runtime']['pid']);
        $this->assertIsFloat($context['runtime']['memory_peak_mb']);
        $this->assertGreaterThanOrEqual(1500, $context['runtime']['elapsed_ms']);
    }

    public function testBuildContextAddsSourceWhenGiven(): void
    {
        $source = ['file' => 'Library/Webhooks.php', 'line' => 10, 'function' => 'Webhooks::doRequest'];

        $context = LogStream::buildContext(null, [], null, $source, 'cli');

        $this->assertSame($source, $context['source']);
    }

    public function testBuildContextCallerKeysWinOverAmbientKeys(): void
    {
        $context = LogStream::buildContext(['runtime' => 'mine'], [], null, null, 'cli');

        $this->assertSame('mine', $context['runtime']);
    }

    public function testBuildContextKeepsCallerKeysFirst(): void
    {
        $context = LogStream::buildContext(['host' => 'vinhedo1', 'reason' => 'timeout'], [], null, null, 'cli');

        $this->assertSame(['host', 'reason', 'process', 'runtime'], array_keys($context));
    }

    public function testBuildContextExpandsThrowables(): void
    {
        $previous = new InvalidArgumentException('inner');
        $exception = new RuntimeException('outer', 12, $previous);

        $context = LogStream::buildContext(['exception' => $exception], [], null, null, 'cli');

        $this->assertSame('RuntimeException', $context['exception']['type']);
        $this->assertSame('outer', $context['exception']['message']);
        $this->assertSame(12, $context['exception']['code']);
        $this->assertSame(__FILE__, $context['exception']['file']);
        $this->assertIsInt($context['exception']['line']);
        $this->assertIsString($context['exception']['trace']);
        $this->assertSame('InvalidArgumentException', $context['exception']['previous']['type']);
        $this->assertSame('inner', $context['exception']['previous']['message']);
    }

    public function testDescribeThrowableTruncatesLongTraces(): void
    {
        $exception = $this->deepException(200);

        $description = LogStream::describeThrowable($exception);

        $this->assertLessThanOrEqual(4001, mb_strlen($description['trace']));
        $this->assertArrayNotHasKey('previous', $description);
    }

    // -------------------------------------------------------------------------
    // callSite
    // -------------------------------------------------------------------------

    public function testCallSiteSkipsLogStreamFramesAndNamesEnclosingMethod(): void
    {
        $logStreamFile = (new ReflectionClass(LogStream::class))->getFileName();
        $root = dirname(dirname($logStreamFile)) . DIRECTORY_SEPARATOR;

        $frames = [
            ['file' => $logStreamFile, 'line' => 90, 'function' => 'write', 'class' => LogStream::class],
            ['file' => $root . 'Library/Webhooks.php', 'line' => 54, 'function' => 'debug', 'class' => LogStream::class],
            ['file' => $root . 'api/v1/webhooks.php', 'line' => 16, 'function' => 'doRequest', 'class' => 'GuiBranco\\ProjectsMonitor\\Library\\Webhooks'],
        ];

        $this->assertSame(
            ['file' => 'Library/Webhooks.php', 'line' => 54, 'function' => 'Webhooks::doRequest'],
            LogStream::callSite($frames)
        );
    }

    public function testCallSiteReportsMainForTopLevelScripts(): void
    {
        $logStreamFile = (new ReflectionClass(LogStream::class))->getFileName();

        $frames = [
            ['file' => $logStreamFile, 'line' => 90, 'function' => 'write', 'class' => LogStream::class],
            ['file' => '/elsewhere/worker.php', 'line' => 27, 'function' => 'info', 'class' => LogStream::class],
        ];

        $this->assertSame(
            ['file' => '/elsewhere/worker.php', 'line' => 27, 'function' => '{main}'],
            LogStream::callSite($frames)
        );
    }

    public function testCallSiteNamesPlainFunctions(): void
    {
        $frames = [
            ['file' => '/elsewhere/worker.php', 'line' => 5, 'function' => 'info', 'class' => LogStream::class],
            ['file' => '/elsewhere/worker.php', 'line' => 30, 'function' => 'workerStarted'],
        ];

        $this->assertSame('workerStarted', LogStream::callSite($frames)['function']);
    }

    public function testCallSiteReturnsNullWithoutExternalFrames(): void
    {
        $this->assertNull(LogStream::callSite([]));
    }

    // -------------------------------------------------------------------------
    // Logging without a configured client
    // -------------------------------------------------------------------------

    public function testLoggingWithoutSecretsIsANoOp(): void
    {
        LogStream::info("API request {endpoint} received", ["endpoint" => "GET /api/v1/test"], "api");
        LogStream::log('unknown-level', "Fallback {level}", ["level" => "x"]);

        $this->addToAssertionCount(1);
    }

    private function deepException(int $depth): Throwable
    {
        if ($depth === 0) {
            return new RuntimeException('deep');
        }

        return $this->deepException($depth - 1);
    }
}

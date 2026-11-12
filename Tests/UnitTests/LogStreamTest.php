<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\Pancake\LogStream as PancakeLogStream;
use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use GuiBranco\ProjectsMonitor\Library\LogStream;

/**
 * Tests what LogStream sends: the level, the message template, the caller
 * context and the ambient context, through a Pancake client whose HTTP
 * request is stubbed.
 */
class LogStreamTest extends TestCase
{
    private const URL = "https://webhooks.example.com/api/v1/github/workflow?feedOptionsFilter=mine";

    /**
     * The decoded bodies the client posted.
     *
     * @var array
     */
    private $sent = [];

    /**
     * The logger under test.
     *
     * @var LogStream
     */
    private $logStream;


    /**
     * Gives LogStream a client whose request records what it posts.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->sent      = [];
        $this->logStream = new LogStream();
        $this->logStream->setServer(["HTTP_X_REQUEST_ID" => "trace-from-test"]);
        $this->logStream->setClient($this->createClient(false));

    }//end setUp()


    /**
     * Leaves LogStream without a client, as when no secrets file exists.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->logStream->setClient(null);
        $this->logStream->setServer([]);

    }//end tearDown()


    /**
     * Every level sends one entry with the template, the caller context, the
     * ambient context, the call site and the trace id.
     *
     * @return void
     */
    public function testEveryLevelSendsTheTemplateAndContext(): void
    {
        $this->logStream->debug("Fetching {resource}", ["resource" => "workers"], "webhooks");
        $this->logStream->info("Fetching {resource}", ["resource" => "workers"], "webhooks");
        $this->logStream->notice("Fetching {resource}", ["resource" => "workers"], "webhooks");
        $this->logStream->warning("Fetching {resource}", ["resource" => "workers"], "webhooks");
        $this->logStream->error("Fetching {resource}", ["resource" => "workers"], "webhooks");
        $this->logStream->critical("Fetching {resource}", ["resource" => "workers"], "webhooks");

        $this->assertSame(["debug", "info", "notice", "warning", "error", "critical"], array_column($this->sent, "level"));

        $entry = $this->sent[0];
        $this->assertSame("Fetching {resource}", $entry["message"]);
        $this->assertSame("webhooks", $entry["category"]);
        $this->assertSame("trace-from-test", $entry["trace_id"]);
        $this->assertSame("workers", $entry["context"]["resource"]);
        $this->assertSame("cli", $entry["context"]["runtime"]["sapi"]);
        $this->assertSame(__FILE__, $entry["context"]["source"]["file"]);
        $this->assertSame("LogStreamTest::testEveryLevelSendsTheTemplateAndContext", $entry["context"]["source"]["function"]);
        $this->assertSame("trace-from-test", $this->logStream->getTraceId());

    }//end testEveryLevelSendsTheTemplateAndContext()


    /**
     * A level chosen at runtime is used when known, otherwise the entry is an error.
     *
     * @return void
     */
    public function testLogUsesTheGivenLevelOrError(): void
    {
        $this->logStream->log("warning", "Worker {worker} done", ["worker" => "ProcessErrorLogs"], "worker");
        $this->logStream->log("loud", "Worker {worker} done", ["worker" => "ProcessErrorLogs"], "worker");

        $this->assertSame(["warning", "error"], array_column($this->sent, "level"));
        $this->assertSame(__FILE__, $this->sent[1]["context"]["source"]["file"]);

    }//end testLogUsesTheGivenLevelOrError()


    /**
     * A successful GET is debug and a successful POST is info, with the
     * endpoint, status code and duration.
     *
     * @return void
     */
    public function testLogResponseLogsSuccessByMethod(): void
    {
        $response = new Response(true, "{}", "", 200, self::URL, []);

        $this->logStream->logResponse("Webhooks API", "get", $response, microtime(true), "webhooks");
        $this->logStream->logResponse("Webhooks API", "post", $response, microtime(true), "webhooks");

        $this->assertSame(["debug", "info"], array_column($this->sent, "level"));
        $entry = $this->sent[0];
        $this->assertSame("{service} {method} {endpoint} responded {status_code} in {duration_ms} ms", $entry["message"]);
        $this->assertSame("Webhooks API", $entry["context"]["service"]);
        $this->assertSame("GET", $entry["context"]["method"]);
        $this->assertSame("/api/v1/github/workflow?feedOptionsFilter=mine", $entry["context"]["endpoint"]);
        $this->assertSame(200, $entry["context"]["status_code"]);
        $this->assertIsInt($entry["context"]["duration_ms"]);
        $this->assertArrayNotHasKey("error", $entry["context"]);

    }//end testLogResponseLogsSuccessByMethod()


    /**
     * A 4xx answer is a warning and a 5xx answer an error, both with the body.
     *
     * @return void
     */
    public function testLogResponseLogsErrorAnswersWithTheBody(): void
    {
        $this->logStream->logResponse("Webhooks API", "DELETE", new Response(false, "Not found", "", 404, self::URL, []), microtime(true), "webhooks");
        $this->logStream->logResponse("GStraccini bot", "POST", new Response(false, str_repeat("x", 600), "", 503, self::URL, []), microtime(true), "gstraccini-bot");

        $this->assertSame(["warning", "error"], array_column($this->sent, "level"));
        $this->assertSame("{service} {method} {endpoint} responded {status_code} in {duration_ms} ms: {error}", $this->sent[0]["message"]);
        $this->assertSame("Not found", $this->sent[0]["context"]["error"]);
        $this->assertSame(501, mb_strlen($this->sent[1]["context"]["error"]));
        $this->assertSame("gstraccini-bot", $this->sent[1]["category"]);

    }//end testLogResponseLogsErrorAnswersWithTheBody()


    /**
     * A transport failure is an error with the transport message.
     *
     * @return void
     */
    public function testLogResponseLogsTransportFailures(): void
    {
        $response = new Response(false, null, "Could not resolve host", -1, self::URL, null);

        $this->logStream->logResponse("Webhooks API", "GET", $response, microtime(true), "webhooks");

        $this->assertSame("error", $this->sent[0]["level"]);
        $this->assertSame("{service} {method} {endpoint} failed after {duration_ms} ms: {error}", $this->sent[0]["message"]);
        $this->assertSame("Could not resolve host", $this->sent[0]["context"]["error"]);

    }//end testLogResponseLogsTransportFailures()


    /**
     * A failure to send does not reach the caller.
     *
     * @return void
     */
    public function testSendFailureIsSwallowed(): void
    {
        $this->logStream->setClient($this->createClient(true));
        $errorLog = ini_set("error_log", "/dev/null");

        $this->logStream->error("Failed to parse {path}", ["path" => "error_log"], "worker");

        ini_set("error_log", (string) $errorLog);
        $this->assertSame([], $this->sent);

    }//end testSendFailureIsSwallowed()


    /**
     * Without a client nothing is sent and a new trace id follows a new server registration.
     *
     * @return void
     */
    public function testNothingIsSentWithoutClient(): void
    {
        $this->logStream->setClient(null);
        $this->logStream->setServer([]);

        $this->logStream->info("API request {request.method} {request.path} received", null, "api");

        $this->assertSame([], $this->sent);
        $this->assertNotSame("trace-from-test", $this->logStream->getTraceId());

    }//end testNothingIsSentWithoutClient()


    /**
     * The client is created only from a secrets file with a server and a token.
     *
     * @return void
     */
    public function testCreateClientNeedsServerAndToken(): void
    {
        $directory = sys_get_temp_dir();
        $partial   = tempnam($directory, "logstream");
        $complete  = tempnam($directory, "logstream");
        file_put_contents($partial, "<?php\n\$logStreamServer = \"https://logs.example.com\";\n\$logStreamApiKey = \"\";\n\$logStreamApiToken = \"\";\n");
        file_put_contents($complete, "<?php\n\$logStreamServer = \"https://logs.example.com\";\n\$logStreamApiKey = \"\";\n\$logStreamApiToken = \"lss_token\";\n");

        $this->assertNull($this->logStream->createClient("{$directory}/missing.secrets.php"));
        $this->assertNull($this->logStream->createClient($partial));
        $this->assertInstanceOf(PancakeLogStream::class, $this->logStream->createClient($complete));

        unlink($partial);
        unlink($complete);

    }//end testCreateClientNeedsServerAndToken()


    /**
     * A Pancake client whose request records each posted body, or throws.
     *
     * @param boolean $failing Whether posting throws.
     *
     * @return PancakeLogStream The client.
     */
    private function createClient(bool $failing): PancakeLogStream
    {
        $request = $this->createStub(Request::class);
        $request->method("post")->willReturnCallback(
            function (string $url, array $headers, string $body) use ($failing): Response {
                if ($failing === true) {
                    throw new RuntimeException("LogStream is down");
                }

                $this->assertContains("X-Api-Token: test-token", $headers);
                $this->sent[] = json_decode($body, true);
                return new Response(true, "{\"saved\":1}", "", 201, $url, []);
            }
        );

        return new PancakeLogStream("https://logs.example.com", "projects-monitor", "1.0.0", PancakeLogStream::AUTH_API_KEY, "", "projects-monitor", "test-token", $request);

    }//end createClient()


}//end class

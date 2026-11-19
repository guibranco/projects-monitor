<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use GuiBranco\ProjectsMonitor\Library\GitHub;

/**
 * Tests that GitHub::getApiUsage() always returns core and data, so
 * api/v1/github.php never reads missing keys when rate_limit fails.
 */
class GitHubApiUsageTest extends TestCase
{
    /**
     * The rate_limit URL the client calls.
     */
    private const URL = "https://api.github.com/rate_limit";

    /**
     * The PHP error log setting before the test.
     *
     * @var string|false
     */
    private $errorLog = false;


    /**
     * Sends the PHP error log of the reports (no database in tests) nowhere.
     *
     * @return void
     */
    protected function setUp(): void
    {
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
     * A client whose rate_limit call answers the given response.
     *
     * @param Response $response The response.
     *
     * @return GitHub The client.
     */
    private function buildGitHub(Response $response): GitHub
    {
        $request = $this->createStub(Request::class);
        $request->method("get")->willReturn($response);

        $gitHub = (new ReflectionClass(GitHub::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(GitHub::class, "apiUrl"))->setValue($gitHub, "https://api.github.com/");
        (new ReflectionProperty(GitHub::class, "request"))->setValue($gitHub, $request);
        (new ReflectionProperty(GitHub::class, "headers"))->setValue($gitHub, []);

        return $gitHub;

    }//end buildGitHub()


    /**
     * A 503 answer gives the header row only and no core usage.
     *
     * @return void
     */
    public function testFailedRequestReturnsHeaderOnly(): void
    {
        $body   = "<!DOCTYPE html><html><body><h1>503</h1><p>busy</p></body></html>";
        $gitHub = $this->buildGitHub(new Response(false, $body, "", 503, self::URL, []));

        $usage = $gitHub->getApiUsage();

        $this->assertNull($usage["core"]);
        $this->assertCount(1, $usage["data"]);
        $this->assertSame("Resource", $usage["data"][0][0]);

    }//end testFailedRequestReturnsHeaderOnly()


    /**
     * A transport failure (status -1) gives the header row only and no core usage.
     *
     * @return void
     */
    public function testTransportFailureReturnsHeaderOnly(): void
    {
        $gitHub = $this->buildGitHub(new Response(false, null, "Could not resolve host", -1, self::URL, []));

        $usage = $gitHub->getApiUsage();

        $this->assertNull($usage["core"]);
        $this->assertCount(1, $usage["data"]);

    }//end testTransportFailureReturnsHeaderOnly()


    /**
     * A 200 answer without resources gives the header row only and no core usage.
     *
     * @return void
     */
    public function testMalformedBodyReturnsHeaderOnly(): void
    {
        $gitHub = $this->buildGitHub(new Response(true, "{\"message\": \"unexpected\"}", "", 200, self::URL, []));

        $usage = $gitHub->getApiUsage();

        $this->assertNull($usage["core"]);
        $this->assertCount(1, $usage["data"]);

    }//end testMalformedBodyReturnsHeaderOnly()


}//end class

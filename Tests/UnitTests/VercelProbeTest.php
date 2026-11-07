<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\Pancake\Response;
use GuiBranco\ProjectsMonitor\Library\VercelProbe;

/**
 * Tests the probe of the Vercel production URLs: what each URL answered,
 * sent in batches, and the per-URL cache that spares the quota.
 */
class VercelProbeTest extends TestCase
{

    /**
     * The probe, with the requests mocked and the cache in a temporary folder.
     *
     * @var VercelProbe
     */
    private $probe;

    /**
     * The cache file of this test, in a folder that does not exist yet.
     *
     * @var string
     */
    private $cachePath;


    /**
     * Mocks the batch requests and points the cache at a fresh temporary folder.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $folder          = sys_get_temp_dir()."/vercel-probe-".uniqid("", true);
        $this->cachePath = "{$folder}/cache/vercel_probe.json";

        $builder = $this->getMockBuilder(VercelProbe::class);
        $builder->setConstructorArgs([$this->cachePath]);
        $builder->onlyMethods(['sendBatch']);

        $this->probe = $builder->getMock();

    }//end setUp()


    /**
     * Removes the cache file and its folders.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (file_exists($this->cachePath) === true) {
            unlink($this->cachePath);
        }

        $folder = dirname($this->cachePath);
        if (is_dir($folder) === true) {
            rmdir($folder);
            rmdir(dirname($folder));
        }

    }//end tearDown()


    /**
     * A Pancake response for one URL.
     *
     * @param string      $url        The URL requested.
     * @param integer     $statusCode The status code, -1 for a failed request.
     * @param string|null $error      The value of the error header, if any.
     * @param string      $header     The name of the error header, in any case.
     *
     * @return Response
     */
    private function getResponse(string $url, int $statusCode, ?string $error=null, string $header="x-vercel-error"): Response
    {
        if ($statusCode < 0) {
            return new Response(false, null, "Could not resolve host", $statusCode, $url, null);
        }

        $headers = [];

        $headers["http_code"]    = "HTTP/1.1 {$statusCode}";
        $headers["content-type"] = "text/html";
        if ($error !== null) {
            $headers[$header] = $error;
        }

        return new Response(true, "", "", $statusCode, $url, $headers);

    }//end getResponse()


    /**
     * The links of four projects, keyed by name.
     *
     * @return array
     */
    private function getLinks(): array
    {
        $links = [];

        $links["readme-stats"] = "https://github-readme-stats-guibranco.vercel.app";
        $links["progressbar"]  = "https://progress-bar.xyz";
        $links["gone"]         = "https://this-deployment-does-not-exist.vercel.app";
        $links["offline"]      = "https://offline.example.invalid";

        return $links;

    }//end getLinks()


    /**
     * The answers of the four links, whatever the case of the error header.
     *
     * @return array Responses keyed by URL.
     */
    private function getResponses(): array
    {
        $responses = [];

        $responses["https://github-readme-stats-guibranco.vercel.app"]  = $this->getResponse("https://github-readme-stats-guibranco.vercel.app", 402, "DEPLOYMENT_DISABLED");
        $responses["https://progress-bar.xyz"]                          = $this->getResponse("https://progress-bar.xyz", 200);
        $responses["https://this-deployment-does-not-exist.vercel.app"] = $this->getResponse("https://this-deployment-does-not-exist.vercel.app", 404, "DEPLOYMENT_NOT_FOUND", "X-Vercel-Error");
        $responses["https://offline.example.invalid"]                   = $this->getResponse("https://offline.example.invalid", -1);

        return $responses;

    }//end getResponses()


    /**
     * Each link gets its status code and the platform's error code, keyed by
     * project name; the failed request is answered but not cached.
     *
     * @return void
     */
    public function testDescribesWhatEachLinkAnswered(): void
    {
        $this->probe->method('sendBatch')->willReturn($this->getResponses());

        $answers = $this->probe->probe($this->getLinks());

        $this->assertCount(4, $answers);
        $this->assertSame(402, $answers["readme-stats"]["statusCode"]);
        $this->assertSame("DEPLOYMENT_DISABLED", $answers["readme-stats"]["vercelError"]);
        $this->assertSame(200, $answers["progressbar"]["statusCode"]);
        $this->assertNull($answers["progressbar"]["vercelError"]);
        $this->assertSame("DEPLOYMENT_NOT_FOUND", $answers["gone"]["vercelError"]);
        $this->assertSame(-1, $answers["offline"]["statusCode"]);
        $this->assertNull($answers["offline"]["vercelError"]);
        $this->assertEqualsWithDelta(time(), $answers["gone"]["checkedAt"], 5);

        $cached = json_decode(file_get_contents($this->cachePath), true);
        $this->assertCount(3, $cached);
        $this->assertArrayNotHasKey("https://offline.example.invalid", $cached);
        $this->assertSame("DEPLOYMENT_DISABLED", $cached["https://github-readme-stats-guibranco.vercel.app"]["vercelError"]);

    }//end testDescribesWhatEachLinkAnswered()


    /**
     * A fresh entry is served from the cache; expired and missing links are
     * requested again and the file rewritten with all of them.
     *
     * @return void
     */
    public function testServesFreshAnswersFromTheCacheAndRequestsTheRest(): void
    {
        $fresh = [];
        $stale = [];

        $fresh["checkedAt"]   = (time() - 10);
        $fresh["statusCode"]  = 402;
        $fresh["vercelError"] = "DEPLOYMENT_DISABLED";
        $stale["checkedAt"]   = (time() - VercelProbe::CACHE_TTL_SECONDS - 1);
        $stale["statusCode"]  = 200;
        $stale["vercelError"] = null;

        $cached = [];

        $cached["https://github-readme-stats-guibranco.vercel.app"] = $fresh;
        $cached["https://progress-bar.xyz"]                         = $stale;

        mkdir(dirname($this->cachePath), 0755, true);
        file_put_contents($this->cachePath, json_encode($cached));

        $pending   = [];
        $pending[] = "https://progress-bar.xyz";
        $pending[] = "https://this-deployment-does-not-exist.vercel.app";
        $pending[] = "https://offline.example.invalid";

        $responses = $this->getResponses();
        unset($responses["https://github-readme-stats-guibranco.vercel.app"]);

        $this->probe->expects($this->once())->method('sendBatch')->with($pending)->willReturn($responses);

        $answers = $this->probe->probe($this->getLinks());

        $this->assertSame(402, $answers["readme-stats"]["statusCode"]);
        $this->assertSame(200, $answers["progressbar"]["statusCode"]);
        $this->assertSame("DEPLOYMENT_NOT_FOUND", $answers["gone"]["vercelError"]);
        $this->assertSame(-1, $answers["offline"]["statusCode"]);

        $rewritten = json_decode(file_get_contents($this->cachePath), true);
        $this->assertCount(3, $rewritten);
        $this->assertSame($fresh, $rewritten["https://github-readme-stats-guibranco.vercel.app"]);
        $this->assertGreaterThan($stale["checkedAt"], $rewritten["https://progress-bar.xyz"]["checkedAt"]);

    }//end testServesFreshAnswersFromTheCacheAndRequestsTheRest()


    /**
     * Nothing is requested while every link is fresh in the cache.
     *
     * @return void
     */
    public function testRequestsNothingWhileTheCacheIsFresh(): void
    {
        $this->probe->method('sendBatch')->willReturn($this->getResponses());
        $this->probe->probe($this->getLinks());

        $links = $this->getLinks();
        unset($links["offline"]);

        $builder = $this->getMockBuilder(VercelProbe::class);
        $builder->setConstructorArgs([$this->cachePath]);
        $builder->onlyMethods(['sendBatch']);

        $second = $builder->getMock();
        $second->expects($this->never())->method('sendBatch');

        $answers = $second->probe($links);

        $this->assertCount(3, $answers);
        $this->assertSame("DEPLOYMENT_DISABLED", $answers["readme-stats"]["vercelError"]);

    }//end testRequestsNothingWhileTheCacheIsFresh()


    /**
     * Links are requested ten at a time, and a malformed cache file counts
     * as empty.
     *
     * @return void
     */
    public function testRequestsTenLinksAtATimeAndIgnoresAMalformedCache(): void
    {
        mkdir(dirname($this->cachePath), 0755, true);
        file_put_contents($this->cachePath, "{not json");

        $links = [];
        for ($index = 1; $index <= 12; $index++) {
            $links["project-{$index}"] = "https://project-{$index}.vercel.app";
        }

        $batches = [];
        $answer  = function (array $urls) use (&$batches): array {
            $batches[] = count($urls);
            $responses = [];
            foreach ($urls as $url) {
                $responses[$url] = $this->getResponse($url, 200);
            }

            return $responses;
        };

        $this->probe->expects($this->exactly(2))->method('sendBatch')->willReturnCallback($answer);

        $answers = $this->probe->probe($links);

        $this->assertSame([10, 2], $batches);
        $this->assertCount(12, $answers);
        $this->assertSame(200, $answers["project-12"]["statusCode"]);

    }//end testRequestsTenLinksAtATimeAndIgnoresAMalformedCache()


}//end class

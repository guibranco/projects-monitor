<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use GuiBranco\ProjectsMonitor\Library\CloudflarePages;
use GuiBranco\ProjectsMonitor\Library\RequestException;
use GuiBranco\ProjectsMonitor\Library\SecretsFileNotFoundException;

/**
 * Tests the Cloudflare Pages tile: every project of the account, across all
 * pages, sorted by name, with the state of its latest production deployment.
 */
class CloudflarePagesTest extends TestCase
{
    private const ACCOUNT_ID = "account123";

    private const API_URL = "https://api.cloudflare.com/client/v4/accounts/account123/pages/projects";

    /**
     * The URLs the tile requested, in order.
     *
     * @var array
     */
    private $urls = [];


    /**
     * A deployment as the projects endpoint embeds it, trimmed to the fields
     * the tile reads.
     *
     * @param string      $stage   The latest stage name.
     * @param string      $status  The latest stage status.
     * @param string|null $endedOn When the stage ended, null while running.
     * @param string|null $branch  The branch deployed.
     *
     * @return array
     */
    private function getDeployment(string $stage, string $status, ?string $endedOn, ?string $branch=null): array
    {
        $latestStage             = [];
        $latestStage["name"]     = $stage;
        $latestStage["status"]   = $status;
        $latestStage["ended_on"] = $endedOn;

        $deployment                 = [];
        $deployment["created_on"]   = "2026-11-20T10:00:00Z";
        $deployment["latest_stage"] = $latestStage;

        if ($branch !== null) {
            $deployment["deployment_trigger"] = ["metadata" => ["branch" => $branch]];
        }

        return $deployment;

    }//end getDeployment()


    /**
     * A project as GET /accounts/{id}/pages/projects lists it.
     *
     * @param string     $name      The project name.
     * @param array|null $canonical The latest production deployment.
     * @param array|null $latest    The latest deployment of any environment.
     * @param array      $domains   The project's domains.
     *
     * @return array
     */
    private function getProject(string $name, ?array $canonical, ?array $latest=null, array $domains=[]): array
    {
        $project                         = [];
        $project["id"]                   = md5($name);
        $project["name"]                 = $name;
        $project["subdomain"]            = "{$name}.pages.dev";
        $project["domains"]              = $domains;
        $project["production_branch"]    = "main";
        $project["canonical_deployment"] = $canonical;
        $project["latest_deployment"]    = $latest;

        return $project;

    }//end getProject()


    /**
     * One page of the projects endpoint.
     *
     * @param array $projects The projects of the page.
     * @param array $info     The result_info of the page.
     *
     * @return Response
     */
    private function getPage(array $projects, array $info): Response
    {
        $answer                = [];
        $answer["success"]     = true;
        $answer["errors"]      = [];
        $answer["result"]      = $projects;
        $answer["result_info"] = $info;

        return new Response(true, json_encode($answer), "", 200, self::API_URL, []);

    }//end getPage()


    /**
     * The tile with its constructor skipped and the HTTP client answering
     * the given responses in order.
     *
     * @param Response ...$responses The answers of the projects endpoint.
     *
     * @return CloudflarePages
     */
    private function getTile(Response ...$responses): CloudflarePages
    {
        $reflection = new ReflectionClass(CloudflarePages::class);
        $tile       = $reflection->newInstanceWithoutConstructor();

        $this->urls = [];

        $request = $this->createMock(Request::class);
        $request->method('get')->willReturnCallback(
            function (string $url) use (&$responses): Response {
                $this->urls[] = $url;
                return array_shift($responses);
            }
        );

        foreach (["request" => $request, "headers" => [], "accountId" => self::ACCOUNT_ID] as $name => $value) {
            $property = new ReflectionProperty(CloudflarePages::class, $name);
            $property->setValue($tile, $value);
        }

        return $tile;

    }//end getTile()


    /**
     * A page of ten projects named p00 to p09.
     *
     * @param array $info The result_info of the page.
     *
     * @return Response
     */
    private function getFullPage(array $info): Response
    {
        $projects = [];
        for ($index = 0; $index < 10; $index++) {
            $projects[] = $this->getProject(sprintf("p%02d", $index), $this->getDeployment("deploy", "success", null));
        }

        return $this->getPage($projects, $info);

    }//end getFullPage()


    /**
     * Every page is read, the rows come sorted by name after the header, and
     * the state comes from the latest stage of the production deployment.
     *
     * @return void
     */
    public function testListsEveryProjectSortedWithItsState(): void
    {
        $first   = [];
        $first[] = $this->getProject("zeta", $this->getDeployment("deploy", "success", "2026-11-21T12:00:00Z", "main"));
        $first[] = $this->getProject("alpha", $this->getDeployment("build", "failure", "2026-11-21T11:00:00Z"));

        $second   = [];
        $second[] = $this->getProject("mid", $this->getDeployment("build", "active", null));
        $second[] = $this->getProject("never", null);

        $firstPage  = $this->getPage($first, ["total_pages" => 2]);
        $secondPage = $this->getPage($second, ["total_pages" => 2]);
        $rows       = $this->getTile($firstPage, $secondPage)->getProjects();

        $this->assertSame(self::API_URL."?per_page=10&page=1", $this->urls[0]);
        $this->assertSame(self::API_URL."?per_page=10&page=2", $this->urls[1]);

        $this->assertSame(["Project", "Status", "Branch", "Last Deployment"], $rows[0]);
        $this->assertCount(5, $rows);
        $this->assertStringContainsString("alt='alpha'", $rows[1][0]);
        $this->assertSame("BUILD_FAILURE", $rows[1][1]);
        $this->assertStringContainsString("red", $rows[1][0]);
        $this->assertSame("main", $rows[1][2]);
        $this->assertSame("BUILD_ACTIVE", $rows[2][1]);
        $this->assertStringContainsString("yellow", $rows[2][0]);
        $this->assertSame(date("H:i:s d/m/Y", strtotime("2026-11-20T10:00:00Z")), $rows[2][3]);
        $this->assertSame("NOT_DEPLOYED", $rows[3][1]);
        $this->assertSame("-", $rows[3][3]);
        $this->assertSame("SUCCESS", $rows[4][1]);
        $this->assertStringContainsString("green", $rows[4][0]);

    }//end testListsEveryProjectSortedWithItsState()


    /**
     * A stage that succeeded before the deploy stage means the deployment
     * is still going: not green.
     *
     * @return void
     */
    public function testEarlierStageSuccessIsStillInProgress(): void
    {
        $project = $this->getProject("half", $this->getDeployment("build", "success", "2026-11-21T12:00:00Z"));

        $rows = $this->getTile($this->getPage([$project], ["total_pages" => 1]))->getProjects();

        $this->assertSame("BUILD_SUCCESS", $rows[1][1]);
        $this->assertStringContainsString("yellow", $rows[1][0]);
        $this->assertStringNotContainsString("green", $rows[1][0]);

    }//end testEarlierStageSuccessIsStillInProgress()


    /**
     * A project never deployed to production falls back to its latest
     * deployment of any environment.
     *
     * @return void
     */
    public function testFallsBackToLatestDeployment(): void
    {
        $project = $this->getProject("preview", null, $this->getDeployment("queued", "idle", null));

        $rows = $this->getTile($this->getPage([$project], ["total_pages" => 1]))->getProjects();

        $this->assertSame("QUEUED_IDLE", $rows[1][1]);
        $this->assertStringContainsString("blue", $rows[1][0]);

    }//end testFallsBackToLatestDeployment()


    /**
     * The badge links to the first custom domain, and to the pages.dev
     * subdomain when there is none.
     *
     * @return void
     */
    public function testLinksToCustomDomainThenSubdomain(): void
    {
        $deployment = $this->getDeployment("deploy", "success", "2026-11-21T12:00:00Z");
        $projects   = [];
        $projects[] = $this->getProject("custom", $deployment, null, ["custom.pages.dev", "www.example.com"]);
        $projects[] = $this->getProject("plain", $deployment, null, ["plain.pages.dev"]);

        $rows = $this->getTile($this->getPage($projects, ["total_pages" => 1]))->getProjects();

        $this->assertStringContainsString("href='https://www.example.com'", $rows[1][0]);
        $this->assertStringContainsString("href='https://plain.pages.dev'", $rows[2][0]);

    }//end testLinksToCustomDomainThenSubdomain()


    /**
     * Without total_pages, the page count comes from total_count and
     * per_page.
     *
     * @return void
     */
    public function testDerivesPagesFromTotalCount(): void
    {
        $info                = [];
        $info["per_page"]    = 10;
        $info["total_count"] = 11;

        $last = $this->getPage([$this->getProject("p10", $this->getDeployment("deploy", "success", null))], $info);

        $rows = $this->getTile($this->getFullPage($info), $last)->getProjects();

        $this->assertCount(2, $this->urls);
        $this->assertCount(12, $rows);

    }//end testDerivesPagesFromTotalCount()


    /**
     * Without any count, pages are read while they come back full, and a
     * project listed on two pages (the listing shifted) is kept once.
     *
     * @return void
     */
    public function testReadsWhileFullAndKeepsDuplicatesOnce(): void
    {
        $deployment = $this->getDeployment("deploy", "success", null);
        $second     = [];
        $second[]   = $this->getProject("p09", $deployment);
        $second[]   = $this->getProject("p10", $deployment);

        $rows = $this->getTile($this->getFullPage([]), $this->getPage($second, []))->getProjects();

        $this->assertCount(2, $this->urls);
        $this->assertCount(12, $rows);

    }//end testReadsWhileFullAndKeepsDuplicatesOnce()


    /**
     * An answer other than 200 fails with its status code and body.
     *
     * @return void
     */
    public function testFailsOnHttpError(): void
    {
        $denied = new Response(true, "{\"success\":false}", "", 403, self::API_URL, []);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Code: 403");

        $this->getTile($denied)->getProjects();

    }//end testFailsOnHttpError()


    /**
     * A transport failure fails with the transport error.
     *
     * @return void
     */
    public function testFailsOnTransportError(): void
    {
        $down = new Response(false, null, "Could not resolve host", -1, self::API_URL, null);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Could not resolve host");

        $this->getTile($down)->getProjects();

    }//end testFailsOnTransportError()


    /**
     * A 200 answer without success fails with the API errors.
     *
     * @return void
     */
    public function testFailsWhenTheApiReportsErrors(): void
    {
        $body   = "{\"success\":false,\"errors\":[{\"code\":10000,\"message\":\"Authentication error\"}]}";
        $answer = new Response(true, $body, "", 200, self::API_URL, []);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Authentication error");

        $this->getTile($answer)->getProjects();

    }//end testFailsWhenTheApiReportsErrors()


    /**
     * Without the secrets file the real constructor fails closed, like every
     * other integration.
     *
     * @return void
     */
    public function testConstructorFailsClosedWithoutSecretsFile(): void
    {
        if (file_exists(__DIR__.'/../../Src/secrets/cloudflare.secrets.php') === true) {
            $this->markTestSkipped('cloudflare.secrets.php exists in this checkout.');
        }

        $this->expectException(SecretsFileNotFoundException::class);
        $this->expectExceptionMessage("File not found: cloudflare.secrets.php");

        new CloudflarePages();

    }//end testConstructorFailsClosedWithoutSecretsFile()


}//end class

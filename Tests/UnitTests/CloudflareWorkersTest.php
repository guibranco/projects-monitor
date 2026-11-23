<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use GuiBranco\ProjectsMonitor\Library\CloudflareWorkers;
use GuiBranco\ProjectsMonitor\Library\ErrorReporter;
use GuiBranco\ProjectsMonitor\Library\SecretsFileNotFoundException;

/**
 * Tests the Cloudflare Workers tile: every Worker of the account sorted by
 * name, with the state of its latest Workers Builds build, linked to its
 * custom domain or workers.dev address.
 */
class CloudflareWorkersTest extends TestCase
{
    private const BASE_URL = "https://api.cloudflare.com/client/v4/accounts/acct42/";


    /**
     * A successful API answer.
     *
     * @param mixed $result The result.
     *
     * @return Response
     */
    private function answer($result): Response
    {
        $answer            = [];
        $answer["success"] = true;
        $answer["errors"]  = [];
        $answer["result"]  = $result;

        return new Response(true, json_encode($answer), "", 200, self::BASE_URL, []);

    }//end answer()


    /**
     * A failed API answer.
     *
     * @param integer $code The HTTP status code.
     *
     * @return Response
     */
    private function failed(int $code): Response
    {
        $error            = [];
        $error["code"]    = 10000;
        $error["message"] = "Authentication error";

        $answer            = [];
        $answer["success"] = false;
        $answer["errors"]  = [$error];

        return new Response(true, json_encode($answer), "", $code, self::BASE_URL, []);

    }//end failed()


    /**
     * A Worker script as GET /workers/scripts lists it.
     *
     * @param string $name       The Worker name.
     * @param string $modifiedOn When it last changed.
     *
     * @return array
     */
    private function script(string $name, string $modifiedOn="2026-11-01T08:00:00Z"): array
    {
        $script                = [];
        $script["id"]          = $name;
        $script["tag"]         = "tag-{$name}";
        $script["modified_on"] = $modifiedOn;

        return $script;

    }//end script()


    /**
     * A Workers Builds build.
     *
     * @param string      $status    queued, initializing, running or stopped.
     * @param string|null $outcome   The outcome once stopped.
     * @param string      $createdOn When it was created.
     * @param string|null $branch    The branch built.
     *
     * @return array
     */
    private function build(string $status, ?string $outcome, string $createdOn, ?string $branch=null): array
    {
        $build                  = [];
        $build["status"]        = $status;
        $build["build_outcome"] = $outcome;
        $build["created_on"]    = $createdOn;
        $build["stopped_on"]    = null;
        if ($status === "stopped") {
            $build["stopped_on"] = $createdOn;
        }

        if ($branch !== null) {
            $build["build_trigger_metadata"] = ["branch" => $branch];
        }

        return $build;

    }//end build()


    /**
     * The tile with its constructor skipped and the HTTP client answering
     * by path; a path without an answer fails with 404.
     *
     * @param array $answers The answers, by path after /accounts/{id}/.
     *
     * @return CloudflareWorkers
     */
    private function getTile(array $answers): CloudflareWorkers
    {
        $reflection = new ReflectionClass(CloudflareWorkers::class);
        $tile       = $reflection->newInstanceWithoutConstructor();

        $request = $this->createMock(Request::class);
        $request->method('get')->willReturnCallback(
            function (string $url) use ($answers): Response {
                $path = strtok(substr($url, strlen(self::BASE_URL)), "?");
                return ($answers[$path] ?? $this->failed(404));
            }
        );

        foreach (["request" => $request, "headers" => [], "accountId" => "acct42"] as $name => $value) {
            $property = new ReflectionProperty(CloudflareWorkers::class, $name);
            $property->setValue($tile, $value);
        }

        return $tile;

    }//end getTile()


    /**
     * Workers come sorted by name with the state of their latest build; a
     * Worker without builds, or whose builds cannot be read, is DEPLOYED.
     *
     * @return void
     */
    public function testListsEveryWorkerSortedWithItsState(): void
    {
        $scripts   = [];
        $scripts[] = $this->script("zeta");
        $scripts[] = $this->script("alpha");
        $scripts[] = $this->script("mid");
        $scripts[] = $this->script("plain", "2026-11-02T09:30:00Z");
        $scripts[] = $this->script("locked");

        $domain             = [];
        $domain["hostname"] = "zeta.example.com";
        $domain["service"]  = "zeta";

        $zetaBuilds   = [];
        $zetaBuilds[] = $this->build("stopped", "fail", "2026-11-20T10:00:00Z");
        $zetaBuilds[] = $this->build("stopped", "success", "2026-11-21T10:00:00Z", "main");

        $answers                                    = [];
        $answers["workers/scripts"]                 = $this->answer($scripts);
        $answers["workers/subdomain"]               = $this->answer(["subdomain" => "guibranco"]);
        $answers["workers/domains"]                 = $this->answer([$domain]);
        $answers["builds/workers/tag-zeta/builds"]  = $this->answer($zetaBuilds);
        $answers["builds/workers/tag-alpha/builds"] = $this->answer([$this->build("stopped", "fail", "2026-11-21T11:00:00Z", "dev")]);
        $answers["builds/workers/tag-mid/builds"]   = $this->answer([$this->build("running", null, "2026-11-22T12:00:00Z")]);
        $answers["builds/workers/tag-plain/builds"] = $this->answer([]);

        $rows = $this->getTile($answers)->getWorkers();

        $this->assertSame(["Worker", "Status", "Branch", "Last Deployment"], $rows[0]);
        $this->assertCount(6, $rows);

        $this->assertStringContainsString("alt='alpha'", $rows[1][0]);
        $this->assertSame("BUILD_FAILURE", $rows[1][1]);
        $this->assertStringContainsString("red", $rows[1][0]);
        $this->assertSame("dev", $rows[1][2]);

        $this->assertSame("DEPLOYED", $rows[2][1]);
        $this->assertSame("-", $rows[2][2]);

        $this->assertSame("BUILD_RUNNING", $rows[3][1]);
        $this->assertStringContainsString("yellow", $rows[3][0]);

        $this->assertSame("DEPLOYED", $rows[4][1]);
        $this->assertStringContainsString("green", $rows[4][0]);
        $this->assertSame(date("H:i:s d/m/Y", strtotime("2026-11-02T09:30:00Z")), $rows[4][3]);
        $this->assertStringContainsString("href='https://plain.guibranco.workers.dev'", $rows[4][0]);

        $this->assertSame("SUCCESS", $rows[5][1]);
        $this->assertSame("main", $rows[5][2]);
        $this->assertStringContainsString("href='https://zeta.example.com'", $rows[5][0]);

    }//end testListsEveryWorkerSortedWithItsState()


    /**
     * Without subdomain nor custom domains the badge links to the dashboard,
     * and a cancelled build is gray.
     *
     * @return void
     */
    public function testLinksToTheDashboardWithoutAddresses(): void
    {
        $answers                                   = [];
        $answers["workers/scripts"]                = $this->answer([$this->script("solo")]);
        $answers["builds/workers/tag-solo/builds"] = $this->answer([$this->build("stopped", "cancelled", "2026-11-21T10:00:00Z")]);

        $rows = $this->getTile($answers)->getWorkers();

        $this->assertSame("BUILD_CANCELED", $rows[1][1]);
        $this->assertStringContainsString("gray", $rows[1][0]);
        $this->assertStringContainsString("href='https://dash.cloudflare.com/acct42/workers/services/view/solo/production'", $rows[1][0]);

    }//end testLinksToTheDashboardWithoutAddresses()


    /**
     * An account without Workers shows a note instead of an empty table.
     *
     * @return void
     */
    public function testShowsANoteWhenThereAreNoWorkers(): void
    {
        $rows = $this->getTile(["workers/scripts" => $this->answer([])])->getWorkers();

        $this->assertCount(2, $rows);
        $this->assertSame("No Workers in this account.", $rows[1][0]);

    }//end testShowsANoteWhenThereAreNoWorkers()


    /**
     * When the Workers cannot be listed the tile shows the reason in place
     * of the rows, and reports the failure.
     *
     * @return void
     */
    public function testShowsAndReportsTheReasonWhenTheApiFails(): void
    {
        $reporter = $this->createMock(ErrorReporter::class);
        $reporter->expects($this->once())->method('error')->with(
            $this->stringContains("Cloudflare Workers"),
            $this->callback(fn (array $context) => str_contains($context["reason"], "Code: 403")),
            "api"
        );

        $rows = $this->getTile(["workers/scripts" => $this->failed(403)])->getRowsOrError($reporter);

        $this->assertCount(2, $rows);
        $this->assertStringContainsString("Could not read Cloudflare Workers", $rows[1][0]);
        $this->assertStringContainsString("Authentication error", $rows[1][0]);

    }//end testShowsAndReportsTheReasonWhenTheApiFails()


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

        new CloudflareWorkers();

    }//end testConstructorFailsClosedWithoutSecretsFile()


}//end class

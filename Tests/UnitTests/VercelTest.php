<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use GuiBranco\ProjectsMonitor\Library\RequestException;
use GuiBranco\ProjectsMonitor\Library\SecretsFileNotFoundException;
use GuiBranco\ProjectsMonitor\Library\Vercel;
use GuiBranco\ProjectsMonitor\Library\VercelProbe;

/**
 * Tests the Vercel tile: the state shown for each project comes from the
 * paused flag, then from what the production URL answers, then from the
 * deployment state.
 */
class VercelTest extends TestCase
{
    private const API_URL = "https://api.vercel.com/v10/projects?limit=100";

    /**
     * The tile, with the API and the probe mocked.
     *
     * @var Vercel
     */
    private $vercel;


    /**
     * Mocks the API call and the probe of the production URLs.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $builder = $this->getMockBuilder(Vercel::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods(['fetchProjects', 'getProbe']);

        $this->vercel = $builder->getMock();
        $this->vercel->method('fetchProjects')->willReturn($this->getProjectsResponse());

    }//end setUp()


    /**
     * A deployment as GET /v10/projects lists it, trimmed to the fields the tile reads.
     *
     * @param string      $readyState The deployment state.
     * @param string      $hostname   The deployment's own hostname.
     * @param string|null $alias      The alias assigned to it, or null.
     *
     * @return string JSON.
     */
    private function getDeployment(string $readyState, string $hostname, ?string $alias): string
    {
        $aliases = "[]";
        if ($alias !== null) {
            $aliases = "[\"{$alias}\"]";
        }

        return "{\"readyState\": \"{$readyState}\", \"alias\": {$aliases}, \"url\": \"{$hostname}\", \"createdAt\": 1762343999000}";

    }//end getDeployment()


    /**
     * The shape of GET /v10/projects. A project paused by Vercel keeps its
     * deployment in the READY state and only gets "paused": true.
     *
     * @return object The decoded response.
     */
    private function getProjectsResponse(): object
    {
        $live       = $this->getDeployment("READY", "live-project-abc123.vercel.app", "live-project.vercel.app");
        $paused     = $this->getDeployment("READY", "paused-project-abc123.vercel.app", "paused-project.vercel.app");
        $resumed    = $this->getDeployment("ERROR", "resumed-project-abc123.vercel.app", null);
        $production = $this->getDeployment("READY", "preview-project-prod123.vercel.app", "preview-project.vercel.app");
        $preview    = $this->getDeployment("ERROR", "preview-project-git-feature.vercel.app", null);

        $projects   = [];
        $projects[] = "{\"name\": \"paused-project\", \"paused\": true, \"latestDeployments\": [{$paused}]}";
        $projects[] = "{\"name\": \"live-project\", \"latestDeployments\": [{$live}]}";
        $projects[] = "{\"name\": \"resumed-project\", \"paused\": false, \"latestDeployments\": [{$resumed}]}";
        $projects[] = "{\"name\": \"never-deployed\", \"latestDeployments\": []}";
        $projects[] = "{\"name\": \"preview-project\", \"targets\": {\"production\": {$production}}, \"latestDeployments\": [{$preview}]}";

        $list = implode(", ", $projects);

        return json_decode("{\"projects\": [{$list}], \"pagination\": {\"count\": 5, \"next\": null, \"prev\": null}}");

    }//end getProjectsResponse()


    /**
     * The answer of one URL as VercelProbe describes it.
     *
     * @param integer     $statusCode     The status code, -1 for a failed request.
     * @param string|null $vercelError    The x-vercel-error header, or null.
     * @param boolean     $servedByVercel Whether the x-vercel-id header came back.
     *
     * @return array
     */
    private function getAnswer(int $statusCode, ?string $vercelError, bool $servedByVercel=true): array
    {
        $answer = [];

        $answer["checkedAt"]      = time();
        $answer["statusCode"]     = $statusCode;
        $answer["vercelError"]    = $vercelError;
        $answer["servedByVercel"] = $servedByVercel;

        return $answer;

    }//end getAnswer()


    /**
     * Answers keyed by project name: every URL up, except the paused project's.
     *
     * @return array
     */
    private function getHealthyAnswers(): array
    {
        $answers = [];

        $answers["live-project"]    = $this->getAnswer(200, null);
        $answers["paused-project"]  = $this->getAnswer(503, "DEPLOYMENT_PAUSED");
        $answers["resumed-project"] = $this->getAnswer(200, null);
        $answers["preview-project"] = $this->getAnswer(200, null);

        return $answers;

    }//end getHealthyAnswers()


    /**
     * Plugs a probe that answers the given results into the tile. A key
     * without a prefix is the project's *.vercel.app URL.
     *
     * @param array $answers Answers keyed by project name, or by "domain:" and project name.
     *
     * @return VercelProbe The mocked probe.
     */
    private function getProbeAnswering(array $answers): VercelProbe
    {
        $keyed = [];
        foreach ($answers as $key => $answer) {
            if (str_contains($key, ":") === false) {
                $key = "vercel:{$key}";
            }

            $keyed[$key] = $answer;
        }

        $probe = $this->createMock(VercelProbe::class);
        $probe->method('probe')->willReturn($keyed);

        $this->vercel->method('getProbe')->willReturn($probe);

        return $probe;

    }//end getProbeAnswering()


    /**
     * Sets a private property of the tile (test-only reflection).
     *
     * @param object $vercel   The tile to modify.
     * @param string $property The property name.
     * @param mixed  $value    The value to set.
     *
     * @return void
     */
    private function setPrivateProperty(object $vercel, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty(Vercel::class, $property);
        $reflection->setValue($vercel, $value);

    }//end setPrivateProperty()


    /**
     * The API's answer is decoded, and anything but a 200 fails the tile
     * with the status code and the body, or the transport error, in the
     * message.
     *
     * @return void
     */
    public function testFetchesTheProjectsFromTheApiOrFails(): void
    {
        $builder = $this->getMockBuilder(Vercel::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods(['getProbe']);

        $vercel = $builder->getMock();
        $vercel->method('getProbe')->willReturn($this->createMock(VercelProbe::class));

        $live      = $this->getDeployment("READY", "live-project-abc123.vercel.app", "live-project.vercel.app");
        $listed    = new Response(true, "{\"projects\": [{\"name\": \"live-project\", \"latestDeployments\": [{$live}]}]}", "", 200, self::API_URL, []);
        $forbidden = new Response(true, "{\"error\": \"forbidden\"}", "", 403, self::API_URL, []);
        $failed    = new Response(false, null, "Could not resolve host", -1, self::API_URL, null);

        $request = $this->createMock(Request::class);
        $request->method('get')->willReturnOnConsecutiveCalls($listed, $forbidden, $failed);

        $this->setPrivateProperty($vercel, 'request', $request);
        $this->setPrivateProperty($vercel, 'headers', []);

        $projects = $vercel->getProjects();
        $this->assertCount(2, $projects);
        $this->assertStringContainsString("live--project", $projects[1][0]);
        $this->assertEquals("READY", $projects[1][1]);

        try {
            $vercel->getProjects();
            $this->fail("A 403 must fail the tile.");
        } catch (RequestException $denied) {
            $this->assertSame("Code: 403 - Error: {\"error\": \"forbidden\"}", $denied->getMessage());
        }

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Code: -1 - Error: Could not resolve host");

        $vercel->getProjects();

    }//end testFetchesTheProjectsFromTheApiOrFails()


    /**
     * Without the secrets file the real constructor fails closed, like every
     * other integration.
     *
     * @return void
     */
    public function testConstructorFailsClosedWithoutSecretsFile(): void
    {
        if (file_exists(__DIR__.'/../../Src/secrets/vercel.secrets.php') === true) {
            $this->markTestSkipped('vercel.secrets.php exists in this checkout.');
        }

        $this->expectException(SecretsFileNotFoundException::class);

        $vercel = new Vercel();
        $this->assertNull($vercel);

    }//end testConstructorFailsClosedWithoutSecretsFile()


    /**
     * The probe is built on first use and kept.
     *
     * @return void
     */
    public function testBuildsTheProbeOnce(): void
    {
        $builder = $this->getMockBuilder(Vercel::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods(['fetchProjects']);

        $vercel = $builder->getMock();

        $method = new ReflectionMethod(Vercel::class, 'getProbe');
        $first  = $method->invoke($vercel);
        $second = $method->invoke($vercel);

        $this->assertInstanceOf(VercelProbe::class, $first);
        $this->assertSame($first, $second);

    }//end testBuildsTheProbeOnce()


    /**
     * A project with "paused": true is PAUSED although its deployment is READY.
     *
     * @return void
     */
    public function testReportsPausedProjectsAsPaused(): void
    {
        $this->getProbeAnswering($this->getHealthyAnswers());

        $projects = $this->vercel->getProjects();

        // A header row, then one row per project sorted by name.
        $this->assertCount(6, $projects);
        $this->assertEquals(["Project", "Status", "Last Deployment"], $projects[0]);

        $this->assertStringContainsString("live--project", $projects[1][0]);
        $this->assertEquals("READY", $projects[1][1]);

        $this->assertStringContainsString("never--deployed", $projects[2][0]);
        $this->assertEquals("UNKNOWN", $projects[2][1]);
        $this->assertEquals("-", $projects[2][2]);

        $this->assertStringContainsString("paused--project", $projects[3][0]);
        $this->assertEquals("PAUSED", $projects[3][1]);
        $this->assertStringContainsString("title='PAUSED'", $projects[3][0]);
        $this->assertStringContainsString("-red", $projects[3][0]);
        $this->assertStringContainsString("href='https://paused-project.vercel.app'", $projects[3][0]);

        // The flag set back to false does not hide a failed deployment.
        $this->assertStringContainsString("resumed--project", $projects[5][0]);
        $this->assertEquals("ERROR", $projects[5][1]);
        $this->assertStringContainsString("href='https://resumed-project-abc123.vercel.app'", $projects[5][0]);

    }//end testReportsPausedProjectsAsPaused()


    /**
     * The production deployment is what visitors get: a failed preview, even
     * when it is the latest deployment, does not mark the project as ERROR.
     *
     * @return void
     */
    public function testUsesTheProductionDeploymentOverTheLatestPreview(): void
    {
        $this->getProbeAnswering($this->getHealthyAnswers());

        $projects = $this->vercel->getProjects();

        $this->assertStringContainsString("preview--project", $projects[4][0]);
        $this->assertEquals("READY", $projects[4][1]);
        $this->assertStringContainsString("href='https://preview-project.vercel.app'", $projects[4][0]);
        $this->assertStringNotContainsString("git-feature", $projects[4][0]);

    }//end testUsesTheProductionDeploymentOverTheLatestPreview()


    /**
     * Every project with a URL is probed, keyed by name, in the order shown.
     *
     * @return void
     */
    public function testProbesTheProductionLinks(): void
    {
        $links = [];

        $links["vercel:live-project"]    = "https://live-project.vercel.app";
        $links["vercel:paused-project"]  = "https://paused-project.vercel.app";
        $links["vercel:preview-project"] = "https://preview-project.vercel.app";
        $links["vercel:resumed-project"] = "https://resumed-project-abc123.vercel.app";

        $probe = $this->createMock(VercelProbe::class);
        $probe->expects($this->once())->method('probe')->with($links)->willReturn($this->getHealthyAnswers());

        $this->vercel->method('getProbe')->willReturn($probe);

        $this->vercel->getProjects();

    }//end testProbesTheProductionLinks()


    /**
     * What the URL answered wins over the deployment state: the platform's
     * error code, a failed request or a 5xx of the project itself.
     *
     * @return void
     */
    public function testTrustsTheProbeOverTheDeploymentState(): void
    {
        $answers = [];

        // Disabled by Vercel for exceeding the plan's usage limits: READY for the API.
        $answers["live-project"]    = $this->getAnswer(402, "DEPLOYMENT_DISABLED");
        $answers["paused-project"]  = $this->getAnswer(503, "DEPLOYMENT_PAUSED");
        $answers["resumed-project"] = $this->getAnswer(500, null);
        $answers["preview-project"] = $this->getAnswer(404, "DEPLOYMENT_NOT_FOUND");

        $this->getProbeAnswering($answers);

        $projects = $this->vercel->getProjects();

        $this->assertEquals("DEPLOYMENT_DISABLED", $projects[1][1]);
        $this->assertStringContainsString("title='DEPLOYMENT_DISABLED'", $projects[1][0]);
        $this->assertStringContainsString("-red", $projects[1][0]);
        $this->assertEquals("PAUSED", $projects[3][1]);
        $this->assertEquals("DEPLOYMENT_NOT_FOUND", $projects[4][1]);
        $this->assertStringContainsString("-red", $projects[4][0]);
        $this->assertEquals("HTTP 500", $projects[5][1]);
        $this->assertStringContainsString("-red", $projects[5][0]);

    }//end testTrustsTheProbeOverTheDeploymentState()


    /**
     * A pause seen only by the probe is PAUSED too, and a request that failed
     * is UNREACHABLE.
     *
     * @return void
     */
    public function testReportsPausesAndFailuresSeenByTheProbe(): void
    {
        $answers = $this->getHealthyAnswers();

        $answers["live-project"]    = $this->getAnswer(503, "DEPLOYMENT_PAUSED");
        $answers["preview-project"] = $this->getAnswer(-1, null);

        $this->getProbeAnswering($answers);

        $projects = $this->vercel->getProjects();

        $this->assertEquals("PAUSED", $projects[1][1]);
        $this->assertStringContainsString("-red", $projects[1][0]);
        $this->assertEquals("UNREACHABLE", $projects[4][1]);
        $this->assertStringContainsString("-red", $projects[4][0]);

    }//end testReportsPausesAndFailuresSeenByTheProbe()


    /**
     * Without an answer for a URL, the API alone decides.
     *
     * @return void
     */
    public function testFallsBackToTheApiWithoutAnAnswer(): void
    {
        $this->getProbeAnswering([]);

        $projects = $this->vercel->getProjects();

        $this->assertEquals("READY", $projects[1][1]);
        $this->assertEquals("PAUSED", $projects[3][1]);
        $this->assertEquals("ERROR", $projects[5][1]);

    }//end testFallsBackToTheApiWithoutAnAnswer()


    /**
     * A tile on one project whose production deployment has a custom domain
     * listed before its *.vercel.app alias.
     *
     * @param VercelProbe $probe The probe to plug in.
     *
     * @return Vercel
     */
    private function getCustomDomainTile(VercelProbe $probe): Vercel
    {
        $builder = $this->getMockBuilder(Vercel::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods(['fetchProjects', 'getProbe']);

        $deployment = "{\"readyState\": \"READY\", \"alias\": [\"progress-bar.xyz\", \"progressbar.vercel.app\"], \"url\": \"progressbar-abc123.vercel.app\", \"createdAt\": 1762343999000}";
        $response   = json_decode("{\"projects\": [{\"name\": \"progressbar\", \"targets\": {\"production\": {$deployment}}, \"latestDeployments\": [{$deployment}]}]}");

        $vercel = $builder->getMock();
        $vercel->method('fetchProjects')->willReturn($response);
        $vercel->method('getProbe')->willReturn($probe);

        return $vercel;

    }//end getCustomDomainTile()


    /**
     * The state comes from the *.vercel.app URL, never from the custom
     * domain, whose DNS may send visitors elsewhere: both are probed.
     *
     * @return void
     */
    public function testProbesTheVercelUrlAndTheCustomDomainApart(): void
    {
        $links = [];

        $links["vercel:progressbar"] = "https://progressbar.vercel.app";
        $links["domain:progressbar"] = "https://progress-bar.xyz";

        $answers = [];

        $answers["vercel:progressbar"] = $this->getAnswer(402, "DEPLOYMENT_DISABLED");
        $answers["domain:progressbar"] = $this->getAnswer(200, null, false);

        $probe = $this->createMock(VercelProbe::class);
        $probe->expects($this->once())->method('probe')->with($links)->willReturn($answers);

        $projects = $this->getCustomDomainTile($probe)->getProjects();

        // Disabled on Vercel although the domain, now on another host, answers 200.
        $this->assertEquals("DEPLOYMENT_DISABLED", $projects[1][1]);
        $this->assertStringContainsString("-red", $projects[1][0]);

        // The badge leads to what Vercel serves, not to the other host.
        $this->assertStringContainsString("href='https://progressbar.vercel.app'", $projects[1][0]);

    }//end testProbesTheVercelUrlAndTheCustomDomainApart()


    /**
     * A healthy project whose custom domain answers from another host is
     * DOMAIN_ELSEWHERE, linked to its *.vercel.app URL; served by Vercel it
     * stays READY, linked to the domain.
     *
     * @return void
     */
    public function testFlagsACustomDomainServedElsewhere(): void
    {
        $elsewhere = [];

        $elsewhere["vercel:progressbar"] = $this->getAnswer(200, null);
        $elsewhere["domain:progressbar"] = $this->getAnswer(200, null, false);

        $probe = $this->createMock(VercelProbe::class);
        $probe->method('probe')->willReturn($elsewhere);

        $projects = $this->getCustomDomainTile($probe)->getProjects();

        $this->assertEquals("DOMAIN_ELSEWHERE", $projects[1][1]);
        $this->assertStringContainsString("-orange", $projects[1][0]);
        $this->assertStringContainsString("href='https://progressbar.vercel.app'", $projects[1][0]);

        $onVercel = [];

        $onVercel["vercel:progressbar"] = $this->getAnswer(200, null);
        $onVercel["domain:progressbar"] = $this->getAnswer(200, null);

        $probe = $this->createMock(VercelProbe::class);
        $probe->method('probe')->willReturn($onVercel);

        $projects = $this->getCustomDomainTile($probe)->getProjects();

        $this->assertEquals("READY", $projects[1][1]);
        $this->assertStringContainsString("href='https://progress-bar.xyz'", $projects[1][0]);

        // A failed request, or an answer cached before the header was recorded, says nothing.
        $unknown = [];

        $unknown["vercel:progressbar"] = $this->getAnswer(200, null);
        $legacy = [];

        $legacy["checkedAt"]   = time();
        $legacy["statusCode"]  = 200;
        $legacy["vercelError"] = null;

        $unknown["domain:progressbar"] = $legacy;

        $probe = $this->createMock(VercelProbe::class);
        $probe->method('probe')->willReturn($unknown);

        $this->assertEquals("READY", $this->getCustomDomainTile($probe)->getProjects()[1][1]);

    }//end testFlagsACustomDomainServedElsewhere()


    /**
     * Deployment states keep their icons and colours; UNKNOWN is grey; the
     * platform's codes and anything else the probe reports are red.
     *
     * @return void
     */
    public function testMapsStatesToIconsAndColours(): void
    {
        $reflection = new ReflectionClass(Vercel::class);
        $status     = $reflection->getMethod('mapStatus');
        $color      = $reflection->getMethod('mapColor');

        $this->assertEquals("✅", $status->invoke($this->vercel, "READY"));
        $this->assertEquals("❌", $status->invoke($this->vercel, "ERROR"));
        $this->assertEquals("🚫", $status->invoke($this->vercel, "BLOCKED"));
        $this->assertEquals("❓", $status->invoke($this->vercel, "UNKNOWN"));
        $this->assertEquals("⛔", $status->invoke($this->vercel, "PAUSED"));
        $this->assertEquals("⛔", $status->invoke($this->vercel, "DEPLOYMENT_DISABLED"));
        $this->assertEquals("🔥", $status->invoke($this->vercel, "DEPLOYMENT_NOT_FOUND"));
        $this->assertEquals("↪️", $status->invoke($this->vercel, "DOMAIN_ELSEWHERE"));

        $this->assertEquals("green", $color->invoke($this->vercel, "READY"));
        $this->assertEquals("blue", $color->invoke($this->vercel, "QUEUED"));
        $this->assertEquals("gray", $color->invoke($this->vercel, "CANCELED"));
        $this->assertEquals("lightgray", $color->invoke($this->vercel, "UNKNOWN"));
        $this->assertEquals("red", $color->invoke($this->vercel, "ERROR"));
        $this->assertEquals("red", $color->invoke($this->vercel, "BLOCKED"));
        $this->assertEquals("red", $color->invoke($this->vercel, "PAUSED"));
        $this->assertEquals("red", $color->invoke($this->vercel, "HTTP 500"));
        $this->assertEquals("orange", $color->invoke($this->vercel, "DOMAIN_ELSEWHERE"));

    }//end testMapsStatesToIconsAndColours()


}//end class

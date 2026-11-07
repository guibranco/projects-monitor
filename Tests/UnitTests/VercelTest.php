<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Vercel;

class VercelTest extends TestCase
{
    private $vercel;

    private $cachePath;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . "/vercel_probe_" . uniqid("", true) . ".json";

        $this->vercel = $this->getMockBuilder(Vercel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fetchProjects', 'executeProbe', 'probeCachePath'])
            ->getMock();
        $this->vercel->method('probeCachePath')->willReturn($this->cachePath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }
    }

    private function deployment(string $readyState, string $hostname, ?string $alias = null): string
    {
        $aliases = $alias === null ? "[]" : "[\"{$alias}\"]";

        return "{
            \"readyState\": \"{$readyState}\",
            \"alias\": {$aliases},
            \"url\": \"{$hostname}\",
            \"createdAt\": 1762343999000
        }";
    }

    /**
     * Shape of GET https://api.vercel.com/v10/projects, trimmed to the fields the class reads.
     * A project paused by Vercel (usage limits, spend management or by hand) keeps its
     * deployment in the READY state and only gets "paused": true.
     */
    private function projectsResponse(): object
    {
        $live = $this->deployment("READY", "live-project-abc123.vercel.app", "live-project.vercel.app");
        $paused = $this->deployment("READY", "paused-project-abc123.vercel.app", "paused-project.vercel.app");
        $resumed = $this->deployment("ERROR", "resumed-project-abc123.vercel.app");
        $production = $this->deployment("READY", "preview-project-prod123.vercel.app", "preview-project.vercel.app");
        $preview = $this->deployment("ERROR", "preview-project-git-feature.vercel.app");

        return json_decode("{
            \"projects\": [
                {\"name\": \"paused-project\", \"paused\": true, \"latestDeployments\": [{$paused}]},
                {\"name\": \"live-project\", \"latestDeployments\": [{$live}]},
                {\"name\": \"resumed-project\", \"paused\": false, \"latestDeployments\": [{$resumed}]},
                {\"name\": \"never-deployed\", \"latestDeployments\": []},
                {\"name\": \"preview-project\", \"targets\": {\"production\": {$production}}, \"latestDeployments\": [{$preview}]}
            ],
            \"pagination\": {\"count\": 5, \"next\": null, \"prev\": null}
        }");
    }

    private function probe(int $statusCode, ?string $vercelError = null, ?int $checkedAt = null): array
    {
        return ["checkedAt" => $checkedAt ?? time(), "statusCode" => $statusCode, "vercelError" => $vercelError];
    }

    private function healthyProbes(): array
    {
        return [
            "https://live-project.vercel.app" => $this->probe(200),
            "https://paused-project.vercel.app" => $this->probe(503, "DEPLOYMENT_PAUSED"),
            "https://resumed-project-abc123.vercel.app" => $this->probe(200),
            "https://preview-project.vercel.app" => $this->probe(200),
        ];
    }

    public function testGetProjectsReportsPausedProjectsAsPaused()
    {
        $this->vercel->method('fetchProjects')->willReturn($this->projectsResponse());
        $this->vercel->method('executeProbe')->willReturn($this->healthyProbes());

        $projects = $this->vercel->getProjects();

        // Header row plus one row per project, sorted by name.
        $this->assertCount(6, $projects);
        $this->assertEquals(["Project", "Status", "Last Deployment"], $projects[0]);

        $this->assertStringContainsString("live--project", $projects[1][0]);
        $this->assertEquals("READY", $projects[1][1]);

        $this->assertStringContainsString("never--deployed", $projects[2][0]);
        $this->assertEquals("UNKNOWN", $projects[2][1]);
        $this->assertEquals("-", $projects[2][2]);

        // The paused project is READY for the deployments API but down for its visitors.
        $this->assertStringContainsString("paused--project", $projects[3][0]);
        $this->assertEquals("PAUSED", $projects[3][1]);
        $this->assertStringContainsString("title='PAUSED'", $projects[3][0]);
        $this->assertStringContainsString("-red", $projects[3][0]);
        $this->assertStringContainsString("href='https://paused-project.vercel.app'", $projects[3][0]);

        // "paused": false does not hide a failed deployment.
        $this->assertStringContainsString("resumed--project", $projects[5][0]);
        $this->assertEquals("ERROR", $projects[5][1]);
        $this->assertStringContainsString("href='https://resumed-project-abc123.vercel.app'", $projects[5][0]);
    }

    public function testGetProjectsUsesTheProductionDeploymentOverTheLatestPreview()
    {
        $this->vercel->method('fetchProjects')->willReturn($this->projectsResponse());
        $this->vercel->method('executeProbe')->willReturn($this->healthyProbes());

        $projects = $this->vercel->getProjects();

        // The latest deployment is a failed preview; production is what visitors get.
        $this->assertStringContainsString("preview--project", $projects[4][0]);
        $this->assertEquals("READY", $projects[4][1]);
        $this->assertStringContainsString("href='https://preview-project.vercel.app'", $projects[4][0]);
        $this->assertStringNotContainsString("git-feature", $projects[4][0]);
    }

    public function testGetProjectsProbesTheProductionLinks()
    {
        $this->vercel->method('fetchProjects')->willReturn($this->projectsResponse());
        $this->vercel->expects($this->once())
            ->method('executeProbe')
            ->with([
                "https://live-project.vercel.app",
                "https://paused-project.vercel.app",
                "https://preview-project.vercel.app",
                "https://resumed-project-abc123.vercel.app",
            ])
            ->willReturn($this->healthyProbes());

        $this->vercel->getProjects();
    }

    public function testGetProjectsTrustsTheProbeOverTheDeploymentState()
    {
        $this->vercel->method('fetchProjects')->willReturn($this->projectsResponse());
        $this->vercel->method('executeProbe')->willReturn([
            // Disabled by Vercel for exceeding the plan's usage limits: READY for the
            // API, 402 DEPLOYMENT_DISABLED for every visitor.
            "https://live-project.vercel.app" => $this->probe(402, "DEPLOYMENT_DISABLED"),
            "https://paused-project.vercel.app" => $this->probe(503, "DEPLOYMENT_PAUSED"),
            // The project answered a 5xx itself.
            "https://resumed-project-abc123.vercel.app" => $this->probe(500),
            // The platform answered for a deployment that no longer exists.
            "https://preview-project.vercel.app" => $this->probe(404, "DEPLOYMENT_NOT_FOUND"),
        ]);

        $projects = $this->vercel->getProjects();

        $this->assertEquals("DEPLOYMENT_DISABLED", $projects[1][1]);
        $this->assertStringContainsString("-red", $projects[1][0]);
        $this->assertStringContainsString("title='DEPLOYMENT_DISABLED'", $projects[1][0]);
        $this->assertEquals("PAUSED", $projects[3][1]);
        $this->assertEquals("DEPLOYMENT_NOT_FOUND", $projects[4][1]);
        $this->assertStringContainsString("-red", $projects[4][0]);
        $this->assertEquals("HTTP 500", $projects[5][1]);
        $this->assertStringContainsString("-red", $projects[5][0]);
    }

    public function testGetProjectsReportsAPauseSeenOnlyByTheProbe()
    {
        $this->vercel->method('fetchProjects')->willReturn($this->projectsResponse());
        $probes = $this->healthyProbes();
        $probes["https://live-project.vercel.app"] = $this->probe(503, "DEPLOYMENT_PAUSED");
        $this->vercel->method('executeProbe')->willReturn($probes);

        $projects = $this->vercel->getProjects();

        $this->assertEquals("PAUSED", $projects[1][1]);
        $this->assertStringContainsString("-red", $projects[1][0]);
    }

    public function testGetProjectsReportsConnectionFailuresAsUnreachable()
    {
        $this->vercel->method('fetchProjects')->willReturn($this->projectsResponse());
        $probes = $this->healthyProbes();
        $probes["https://live-project.vercel.app"] = $this->probe(-1);
        $this->vercel->method('executeProbe')->willReturn($probes);

        $projects = $this->vercel->getProjects();

        $this->assertEquals("UNREACHABLE", $projects[1][1]);
        $this->assertStringContainsString("-red", $projects[1][0]);
    }

    public function testGetProjectsFallsBackToTheApiWhenTheProbeFails()
    {
        $this->vercel->method('fetchProjects')->willReturn($this->projectsResponse());
        $this->vercel->method('executeProbe')->willReturn([]);

        $projects = $this->vercel->getProjects();

        $this->assertEquals("READY", $projects[1][1]);
        $this->assertEquals("PAUSED", $projects[3][1]);
        $this->assertEquals("ERROR", $projects[5][1]);
    }

    public function testProbeResultsAreCachedAndRefreshedOnlyWhenExpired()
    {
        $this->vercel->method('fetchProjects')->willReturn($this->projectsResponse());

        $stale = $this->probe(200, null, time() - 601);
        $fresh = $this->probe(503, "DEPLOYMENT_PAUSED", time() - 10);
        file_put_contents($this->cachePath, json_encode([
            "https://live-project.vercel.app" => $stale,
            "https://paused-project.vercel.app" => $fresh,
        ]));

        // Only the expired and the missing links are probed; the fresh one is served from the cache.
        $this->vercel->expects($this->once())
            ->method('executeProbe')
            ->with([
                "https://live-project.vercel.app",
                "https://preview-project.vercel.app",
                "https://resumed-project-abc123.vercel.app",
            ])
            ->willReturn([
                "https://live-project.vercel.app" => $this->probe(200),
                "https://preview-project.vercel.app" => $this->probe(200),
                "https://resumed-project-abc123.vercel.app" => $this->probe(200),
            ]);

        $projects = $this->vercel->getProjects();

        $this->assertEquals("READY", $projects[1][1]);
        $this->assertEquals("PAUSED", $projects[3][1]);

        $cached = json_decode(file_get_contents($this->cachePath), true);
        $this->assertCount(4, $cached);
        $this->assertEquals(200, $cached["https://live-project.vercel.app"]["statusCode"]);
        $this->assertEquals("DEPLOYMENT_PAUSED", $cached["https://paused-project.vercel.app"]["vercelError"]);
        $this->assertArrayHasKey("https://preview-project.vercel.app", $cached);
    }

    public function testMapStatus()
    {
        $reflection = new ReflectionClass(Vercel::class);
        $method = $reflection->getMethod('mapStatus');
        $method->setAccessible(true);

        $this->assertEquals("✅", $method->invoke($this->vercel, "READY"));
        $this->assertEquals("❌", $method->invoke($this->vercel, "ERROR"));
        $this->assertEquals("🚫", $method->invoke($this->vercel, "BLOCKED"));
        $this->assertEquals("⛔", $method->invoke($this->vercel, "PAUSED"));
        $this->assertEquals("❓", $method->invoke($this->vercel, "UNKNOWN"));
    }

    public function testMapColor()
    {
        $reflection = new ReflectionClass(Vercel::class);
        $method = $reflection->getMethod('mapColor');
        $method->setAccessible(true);

        $this->assertEquals("green", $method->invoke($this->vercel, "READY"));
        $this->assertEquals("red", $method->invoke($this->vercel, "ERROR"));
        $this->assertEquals("red", $method->invoke($this->vercel, "BLOCKED"));
        $this->assertEquals("red", $method->invoke($this->vercel, "PAUSED"));
        $this->assertEquals("lightgray", $method->invoke($this->vercel, "UNKNOWN"));
    }
}

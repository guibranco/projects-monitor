<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Vercel;

class VercelTest extends TestCase
{
    private $vercel;

    protected function setUp(): void
    {
        $this->vercel = $this->getMockBuilder(Vercel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fetchProjects'])
            ->getMock();
    }

    private function projectsResponse(): object
    {
        // Shape of GET https://api.vercel.com/v10/projects, trimmed to the fields the class reads.
        // A project paused by Vercel (usage limits, spend management or by hand) keeps its
        // latest deployment in the READY state and only gets "paused": true.
        return json_decode('{
            "projects": [
                {
                    "name": "paused-project",
                    "paused": true,
                    "latestDeployments": [{
                        "readyState": "READY",
                        "alias": ["paused-project.vercel.app"],
                        "url": "paused-project-abc123.vercel.app",
                        "createdAt": 1762343999000
                    }]
                },
                {
                    "name": "live-project",
                    "latestDeployments": [{
                        "readyState": "READY",
                        "alias": ["live-project.vercel.app"],
                        "url": "live-project-abc123.vercel.app",
                        "createdAt": 1762343999000
                    }]
                },
                {
                    "name": "resumed-project",
                    "paused": false,
                    "latestDeployments": [{
                        "readyState": "ERROR",
                        "url": "resumed-project-abc123.vercel.app",
                        "createdAt": 1762343999000
                    }]
                },
                {
                    "name": "never-deployed",
                    "latestDeployments": []
                }
            ],
            "pagination": {"count": 4, "next": null, "prev": null}
        }');
    }

    public function testGetProjectsReportsPausedProjectsAsPaused()
    {
        $this->vercel->method('fetchProjects')->willReturn($this->projectsResponse());

        $projects = $this->vercel->getProjects();

        // Header row plus one row per project, sorted by name.
        $this->assertCount(5, $projects);
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
        $this->assertStringContainsString("resumed--project", $projects[4][0]);
        $this->assertEquals("ERROR", $projects[4][1]);
        $this->assertStringContainsString("href='https://resumed-project-abc123.vercel.app'", $projects[4][0]);
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

<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\AppVeyor;
use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\ShieldsIo;

class AppVeyorTest extends TestCase
{
    private $appVeyor;

    protected function setUp(): void
    {
        $this->appVeyor = $this->getMockBuilder(AppVeyor::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getProjects'])
            ->getMock();
    }

    public function testGetBuilds()
    {
        $mockProjects = json_decode('[{
            "accountName": "testAccount",
            "slug": "testSlug",
            "name": "Test Project",
            "builds": [{
                "status": "success",
                "branch": "main",
                "version": "1.0.0",
                "buildId": "12345",
                "updated": "2023-10-01T12:00:00Z"
            }]
        }]');

        $this->appVeyor->method('getProjects')->willReturn($mockProjects);

        $builds = $this->appVeyor->getBuilds();

        $this->assertCount(2, $builds);
        // The project name/version live in the badge image's URL and title attribute,
        // not as HTML text content, so strip_tags() would always yield an empty string —
        // ShieldsIo encodes a space as "_" in the badge URL path segment.
        $this->assertStringContainsString("Test_Project", $builds[1][0]);
        $this->assertStringContainsString("1.0.0", $builds[1][1]);
        $this->assertEquals("2023-10-01 12:00:00", $builds[1][2]);
    }

    public function testMapStatus()
    {
        $reflection = new ReflectionClass(AppVeyor::class);
        $method = $reflection->getMethod('mapStatus');
        $method->setAccessible(true);

        $this->assertEquals("✅", $method->invoke($this->appVeyor, "success"));
        $this->assertEquals("❌", $method->invoke($this->appVeyor, "failed"));
        $this->assertEquals("⏳", $method->invoke($this->appVeyor, "queued"));
    }

    public function testMapColor()
    {
        $reflection = new ReflectionClass(AppVeyor::class);
        $method = $reflection->getMethod('mapColor');
        $method->setAccessible(true);

        $this->assertEquals("green", $method->invoke($this->appVeyor, "success"));
        $this->assertEquals("red", $method->invoke($this->appVeyor, "failed"));
        $this->assertEquals("blue", $method->invoke($this->appVeyor, "queued"));
    }
}

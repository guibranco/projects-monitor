<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\TimeZone;

class ConfigurationTest extends TestCase
{
    private $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->configuration->init();
    }

    public function testInit()
    {
        $this->assertEquals("UTF-8", ini_get("default_charset"));
        $this->assertEquals(E_ALL, ini_get('error_reporting'));
        $this->assertEquals("UTF-8", mb_internal_encoding());
    }

    public function testSetUserAgent()
    {
        $this->assertTrue(defined("USER_AGENT_VENDOR"));
        $this->assertTrue(defined("USER_AGENT"));
        $this->assertStringContainsString("projects-monitor", constant("USER_AGENT_VENDOR"));
    }

    /**
     * Checks that the version comes from version.txt, trimmed, and falls back
     * to 1.0.0 when the file is missing.
     *
     * @return void
     */
    public function testGetVersionReadsVersionFile()
    {
        $versionFile = sprintf("%s/../../Src/version.txt", __DIR__);
        $existing = null;
        if (file_exists($versionFile) === true) {
            $existing = file_get_contents($versionFile);
        }

        try {
            file_put_contents($versionFile, "1.28.1\n");
            $this->assertSame("1.28.1", $this->configuration->getVersion());

            unlink($versionFile);
            $this->assertSame("1.0.0", $this->configuration->getVersion());
        } finally {
            if ($existing !== null) {
                file_put_contents($versionFile, $existing);
            }
        }
    }

    public function testGetTimeZone()
    {
        $this->assertInstanceOf(TimeZone::class, $this->configuration->getTimeZone());
    }

    public function testGetRequestHeaders()
    {
        $headers = $this->configuration->getRequestHeaders();
        $this->assertArrayHasKey("REMOTE_ADDR", $headers);
        $this->assertArrayHasKey("HTTP_HOST", $headers);
        $this->assertArrayHasKey("REQUEST_URI", $headers);
    }

    public function testGetRequestData()
    {
        $data = $this->configuration->getRequestData();
        $this->assertIsArray($data);
    }
}

<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\WireGuardClientsConfig;
use GuiBranco\ProjectsMonitor\Library\WireGuardClientsConfigException;

/** Tests WireGuardClientsConfig's loading, schema validation and graceful handling of a missing file. */
class WireGuardClientsConfigTest extends TestCase
{
    private array $writtenFiles = [];

    /** Deletes any temp fixture files written during the test. */
    protected function tearDown(): void
    {
        foreach ($this->writtenFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->writtenFiles = [];
    }

    /** Writes raw contents to a temp fixture file, tracked for cleanup. */
    private function writeRaw(string $contents): string
    {
        $path = sys_get_temp_dir() . "/wireguard-clients-" . uniqid("", true) . ".json";
        file_put_contents($path, $contents);
        $this->writtenFiles[] = $path;
        return $path;
    }

    /** Writes $data as a temp JSON fixture file. */
    private function writeFixture(array $data): string
    {
        return $this->writeRaw(json_encode($data));
    }

    private function baseConfig(): array
    {
        return [
            "version" => 1,
            "clients" => [
                ["allowedIps" => "10.0.0.2/32", "name" => "Client A"],
                ["allowedIps" => "10.0.0.3/32", "name" => "Client B"],
            ],
        ];
    }

    /** The committed wireguard-clients.json must load cleanly against its schema. */
    public function testShippedConfigIsValid(): void
    {
        $config = new WireGuardClientsConfig();

        $this->assertTrue($config->isLoaded());
        $this->assertNotEmpty($config->getClients());
        $this->assertSame("GH Actions - Projects Monitor", $config->getClientName("10.141.230.2/32"));
    }

    public function testLoadsClientsFromJson(): void
    {
        $config = new WireGuardClientsConfig($this->writeFixture($this->baseConfig()));

        $this->assertTrue($config->isLoaded());
        $this->assertSame(["10.0.0.2/32" => "Client A", "10.0.0.3/32" => "Client B"], $config->getClients());
        $this->assertSame("Client B", $config->getClientName("10.0.0.3/32"));
    }

    public function testUnmappedPeerFallsBackToAllowedIps(): void
    {
        $config = new WireGuardClientsConfig($this->writeFixture($this->baseConfig()));

        $this->assertSame("10.0.0.99/32", $config->getClientName("10.0.0.99/32"));
    }

    public function testMissingFileIsHandledGracefully(): void
    {
        $config = new WireGuardClientsConfig(sys_get_temp_dir() . "/does-not-exist-" . uniqid() . ".json");

        $this->assertFalse($config->isLoaded());
        $this->assertSame([], $config->getClients());
        $this->assertSame("10.0.0.2/32", $config->getClientName("10.0.0.2/32"));
    }

    public function testEmptyClientListIsValid(): void
    {
        $data = $this->baseConfig();
        $data["clients"] = [];

        $config = new WireGuardClientsConfig($this->writeFixture($data));

        $this->assertTrue($config->isLoaded());
        $this->assertSame([], $config->getClients());
    }

    public function testMalformedJsonThrows(): void
    {
        $this->expectException(WireGuardClientsConfigException::class);
        $this->expectExceptionMessage("Invalid JSON");

        new WireGuardClientsConfig($this->writeRaw('{"version": 1, "clients": ['));
    }

    public function testNonObjectRootThrows(): void
    {
        $this->expectException(WireGuardClientsConfigException::class);
        $this->expectExceptionMessage("root must be a JSON object");

        new WireGuardClientsConfig($this->writeRaw('[]'));
    }

    public function testMissingClientsPropertyThrows(): void
    {
        $data = $this->baseConfig();
        unset($data["clients"]);

        $this->expectException(WireGuardClientsConfigException::class);
        $this->expectExceptionMessage("missing required property 'clients'");

        new WireGuardClientsConfig($this->writeFixture($data));
    }

    public function testClientMissingNameThrows(): void
    {
        $data = $this->baseConfig();
        unset($data["clients"][1]["name"]);

        $this->expectException(WireGuardClientsConfigException::class);
        $this->expectExceptionMessage("\$.clients[1]: missing required property 'name'");

        new WireGuardClientsConfig($this->writeFixture($data));
    }

    public function testUnexpectedPropertyThrows(): void
    {
        $data = $this->baseConfig();
        $data["clients"][0]["ip"] = "10.0.0.2";

        $this->expectException(WireGuardClientsConfigException::class);
        $this->expectExceptionMessage("unexpected property 'ip'");

        new WireGuardClientsConfig($this->writeFixture($data));
    }

    public function testWrongTypeThrows(): void
    {
        $data = $this->baseConfig();
        $data["clients"][0]["name"] = 42;

        $this->expectException(WireGuardClientsConfigException::class);
        $this->expectExceptionMessage("\$.clients[0].name: expected type string, got integer");

        new WireGuardClientsConfig($this->writeFixture($data));
    }

    public function testBlankNameThrows(): void
    {
        $data = $this->baseConfig();
        $data["clients"][0]["name"] = "   ";

        $this->expectException(WireGuardClientsConfigException::class);
        $this->expectExceptionMessage("non-empty");

        new WireGuardClientsConfig($this->writeFixture($data));
    }

    public function testDuplicateAllowedIpsThrows(): void
    {
        $data = $this->baseConfig();
        $data["clients"][1]["allowedIps"] = "10.0.0.2/32";

        $this->expectException(WireGuardClientsConfigException::class);
        $this->expectExceptionMessage("repeats allowedIps '10.0.0.2/32' (already mapped to 'Client A')");

        new WireGuardClientsConfig($this->writeFixture($data));
    }
}

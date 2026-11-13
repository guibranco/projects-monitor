<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\WireGuardClientsConfig;
use GuiBranco\ProjectsMonitor\Library\WireGuardClientsConfigException;

/**
 * Tests WireGuardClientsConfig's loading, validation and graceful handling of a missing file.
 */
class WireGuardClientsConfigTest extends TestCase
{
    private const VALID_CONFIG = '{"version": 1, "clients": [{"allowedIps": "10.0.0.2/32", "name": "Client A"}, {"allowedIps": "10.0.0.3/32", "name": "Client B"}]}';

    /**
     * Temp fixture files written during the test.
     *
     * @var array
     */
    private array $writtenFiles = [];


    /**
     * Deletes the temp fixture files written during the test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->writtenFiles as $path) {
            if (file_exists($path) === true) {
                unlink($path);
            }
        }

        $this->writtenFiles = [];

    }//end tearDown()


    /**
     * Writes the contents to a temp fixture file, tracked for cleanup.
     *
     * @param string $contents Contents of the fixture file.
     *
     * @return string Path of the fixture file.
     */
    private function getFixturePath(string $contents): string
    {
        $path = sprintf('%s/wireguard-clients-%s.json', sys_get_temp_dir(), uniqid('', true));
        file_put_contents($path, $contents);
        $this->writtenFiles[] = $path;

        return $path;

    }//end getFixturePath()


    /**
     * The committed wireguard-clients.json must load cleanly.
     *
     * @return void
     */
    public function testShippedConfigIsValid(): void
    {
        $config = new WireGuardClientsConfig(sprintf('%s/../../Src/Library/wireguard-clients.json', __DIR__));

        $this->assertTrue($config->isLoaded());
        $this->assertNotEmpty($config->getClients());
        $this->assertSame('GH Actions - Projects Monitor', $config->getClients()['10.141.230.2/32']);

    }//end testShippedConfigIsValid()


    /**
     * A valid file is loaded into an allowed IPs to name mapping.
     *
     * @return void
     */
    public function testLoadsClientsFromJson(): void
    {
        $config = new WireGuardClientsConfig($this->getFixturePath(self::VALID_CONFIG));

        $this->assertTrue($config->isLoaded());
        $this->assertSame(['10.0.0.2/32' => 'Client A', '10.0.0.3/32' => 'Client B'], $config->getClients());

    }//end testLoadsClientsFromJson()


    /**
     * A missing file gives an empty mapping instead of an error.
     *
     * @return void
     */
    public function testMissingFileIsHandledGracefully(): void
    {
        $config = new WireGuardClientsConfig(sprintf('%s/does-not-exist-%s.json', sys_get_temp_dir(), uniqid()));

        $this->assertFalse($config->isLoaded());
        $this->assertSame([], $config->getClients());

    }//end testMissingFileIsHandledGracefully()


    /**
     * An empty clients list is valid.
     *
     * @return void
     */
    public function testEmptyClientListIsValid(): void
    {
        $config = new WireGuardClientsConfig($this->getFixturePath('{"version": 1, "clients": []}'));

        $this->assertTrue($config->isLoaded());
        $this->assertSame([], $config->getClients());

    }//end testEmptyClientListIsValid()


    /**
     * An invalid file throws an exception whose message names the problem.
     *
     * @param string $contents        Contents of the configuration file.
     * @param string $expectedMessage Part of the expected exception message.
     *
     * @return void
     */
    #[DataProvider('getInvalidConfigs')]
    public function testInvalidConfigThrows(string $contents, string $expectedMessage): void
    {
        $path = $this->getFixturePath($contents);

        $this->expectException(WireGuardClientsConfigException::class);
        $this->expectExceptionMessage($expectedMessage);

        new WireGuardClientsConfig($path);

    }//end testInvalidConfigThrows()


    /**
     * Invalid configuration files and the error each one must produce.
     *
     * @return array Test cases keyed by description, each with the file contents and the expected message.
     */
    public static function getInvalidConfigs(): array
    {
        $cases = [];
        $cases['malformed JSON'] = self::getCase('{"version": 1, "clients": [', 'Invalid JSON');
        $cases['empty file'] = self::getCase('', 'Invalid JSON');
        $cases['non-object root'] = self::getCase('[]', '$ must be a JSON object');
        $cases['missing version'] = self::getCase('{"clients": []}', '$.version must be an integer >= 1');
        $cases['zero version'] = self::getCase('{"version": 0, "clients": []}', '$.version must be an integer >= 1');
        $cases['missing clients'] = self::getCase('{"version": 1}', '$.clients must be an array');
        $cases['unexpected root property'] = self::getCase('{"version": 1, "clients": [], "peers": []}', "$: unexpected property 'peers'");
        $cases['client not an object'] = self::getCase('{"version": 1, "clients": ["10.0.0.2/32"]}', '$.clients[0] must be an object');
        $cases['client missing name'] = self::getCase('{"version": 1, "clients": [{"allowedIps": "10.0.0.2/32"}]}', '$.clients[0].name must be a non-empty string');
        $cases['client name not a string'] = self::getCase('{"version": 1, "clients": [{"allowedIps": "10.0.0.2/32", "name": 42}]}', '$.clients[0].name must be a non-empty string');
        $cases['client blank name'] = self::getCase('{"version": 1, "clients": [{"allowedIps": "10.0.0.2/32", "name": "  "}]}', '$.clients[0].name must be a non-empty string');
        $cases['client blank allowedIps'] = self::getCase('{"version": 1, "clients": [{"allowedIps": "", "name": "A"}]}', '$.clients[0].allowedIps must be a non-empty string');
        $cases['unexpected client property'] = self::getCase('{"version": 1, "clients": [{"allowedIps": "10.0.0.2/32", "name": "A", "ip": "10.0.0.2"}]}', "$.clients[0]: unexpected property 'ip'");
        $cases['duplicate allowedIps'] = self::getCase(str_replace('10.0.0.3/32', '10.0.0.2/32', self::VALID_CONFIG), "$.clients[1] repeats allowedIps '10.0.0.2/32' (already mapped to 'Client A')");

        return $cases;

    }//end getInvalidConfigs()


    /**
     * Builds one data provider case.
     *
     * @param string $contents        Contents of the configuration file.
     * @param string $expectedMessage Part of the expected exception message.
     *
     * @return array The case's arguments, in order.
     */
    private static function getCase(string $contents, string $expectedMessage): array
    {
        $case = [];
        $case[] = $contents;
        $case[] = $expectedMessage;

        return $case;

    }//end getCase()


}//end class

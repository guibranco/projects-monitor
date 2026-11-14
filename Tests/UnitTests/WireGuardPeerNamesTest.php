<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\LogStream;
use GuiBranco\ProjectsMonitor\Library\WireGuardPeerNames;

/**
 * Tests WireGuardPeerNames' lookup, its fallback to allowed IPs and the logging of a missing or invalid file.
 */
class WireGuardPeerNamesTest extends TestCase
{
    private const VALID_CONFIG = '{"version": 1, "clients": [{"allowedIps": "10.0.0.2/32", "name": "Client A"}]}';

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
        $path = sprintf('%s/wireguard-peer-names-%s.json', sys_get_temp_dir(), uniqid('', true));
        file_put_contents($path, $contents);
        $this->writtenFiles[] = $path;

        return $path;

    }//end getFixturePath()


    /**
     * A mapped peer gets its configured name and nothing is logged.
     *
     * @return void
     */
    public function testReturnsConfiguredName(): void
    {
        $logger = $this->createMock(LogStream::class);
        $logger->expects($this->never())->method('warning');
        $logger->expects($this->never())->method('error');

        $names = new WireGuardPeerNames($this->getFixturePath(self::VALID_CONFIG), $logger);

        $this->assertSame('Client A', $names->getName('10.0.0.2/32'));
        $this->assertSame('10.0.0.9/32', $names->getName('10.0.0.9/32'));

    }//end testReturnsConfiguredName()


    /**
     * A missing file logs one warning and every peer is shown by its allowed IPs.
     *
     * @return void
     */
    public function testMissingFileLogsWarningOnce(): void
    {
        $path = sprintf('%s/does-not-exist-%s.json', sys_get_temp_dir(), uniqid());
        $logger = $this->createMock(LogStream::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('not found'), ['path' => $path], 'wireguard');
        $logger->expects($this->never())->method('error');

        $names = new WireGuardPeerNames($path, $logger);

        $this->assertSame('10.0.0.2/32', $names->getName('10.0.0.2/32'));
        $this->assertSame('10.0.0.3/32', $names->getName('10.0.0.3/32'));

    }//end testMissingFileLogsWarningOnce()


    /**
     * An invalid file logs one error naming the problem and every peer is shown by its allowed IPs.
     *
     * @return void
     */
    public function testInvalidFileLogsErrorOnce(): void
    {
        $logger = $this->createMock(LogStream::class);
        $logger->expects($this->never())->method('warning');
        $logger->expects($this->once())->method('error')->with($this->stringContains('Invalid WireGuard clients configuration'), $this->callback(static fn (array $context): bool => str_contains($context['reason'], '$.clients must be an array')), 'wireguard');

        $names = new WireGuardPeerNames($this->getFixturePath('{"version": 1}'), $logger);

        $this->assertSame('10.0.0.2/32', $names->getName('10.0.0.2/32'));
        $this->assertSame('10.0.0.3/32', $names->getName('10.0.0.3/32'));

    }//end testInvalidFileLogsErrorOnce()


}//end class

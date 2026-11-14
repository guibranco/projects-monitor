<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\DnsProviderDetector;

class DnsProviderDetectorTest extends TestCase
{
    public function testDetectsKnownProviders()
    {
        $this->assertEquals('Cloudflare', DnsProviderDetector::detect(['amy.ns.cloudflare.com', 'rob.ns.cloudflare.com.']));
        $this->assertEquals('NameCheap', DnsProviderDetector::detect(['dns1.registrar-servers.com', 'dns2.registrar-servers.com']));
        $this->assertEquals('Registro.br', DnsProviderDetector::detect(['a.auto.dns.br', 'b.auto.dns.br']));
        $this->assertEquals('Vercel', DnsProviderDetector::detect(['ns1.vercel-dns.com']));
        $this->assertEquals('AWS Route 53', DnsProviderDetector::detect(['ns-123.awsdns-15.com']));
        $this->assertEquals('Azure DNS', DnsProviderDetector::detect(['ns1-01.azure-dns.com']));
    }

    public function testIsCaseInsensitive()
    {
        $this->assertEquals('Cloudflare', DnsProviderDetector::detect(['AMY.NS.CLOUDFLARE.COM']));
    }

    public function testUnknownNameserverUsesFallback()
    {
        $this->assertEquals('Nuvem Hospedagem', DnsProviderDetector::detect(['ns1.example-host.com.br'], 'Nuvem Hospedagem'));
    }

    public function testUnknownNameserverWithoutFallbackUsesNameserverDomain()
    {
        $this->assertEquals('example-host.com.br', DnsProviderDetector::detect(['ns1.example-host.com.br']));
    }

    public function testEmptyNameserversReturnFallback()
    {
        $this->assertEquals('NameCheap', DnsProviderDetector::detect([], 'NameCheap'));
        $this->assertNull(DnsProviderDetector::detect([]));
    }

    public function testDoesNotMatchPartialSuffix()
    {
        $this->assertEquals('notcloudflare.com', DnsProviderDetector::detect(['ns1.notcloudflare.com']));
    }
}

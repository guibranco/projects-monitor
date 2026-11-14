<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\DnsProviderDetector;

/**
 * Tests for DnsProviderDetector.
 */
class DnsProviderDetectorTest extends TestCase
{
    private const CLOUDFLARE = "Cloudflare";


    /**
     * Creates a detector whose DNS lookups return the given NS records.
     *
     * @param array|false $records The records the fake DNS lookup returns.
     *
     * @return DnsProviderDetector
     */
    private function createDetector($records): DnsProviderDetector
    {
        $detector = $this->getMockBuilder(DnsProviderDetector::class)->onlyMethods(["queryDns"])->getMock();
        $detector->method("queryDns")->willReturn($records);
        return $detector;

    }//end createDetector()


    /**
     * Known nameservers map to their provider.
     *
     * @return void
     */
    public function testDetectsKnownProviders(): void
    {
        $detector = new DnsProviderDetector();
        $this->assertEquals(self::CLOUDFLARE, $detector->detect(["amy.ns.cloudflare.com", "rob.ns.cloudflare.com."], ""));
        $this->assertEquals("NameCheap", $detector->detect(["dns1.registrar-servers.com"], ""));
        $this->assertEquals("Registro.br", $detector->detect(["a.auto.dns.br"], ""));
        $this->assertEquals("Vercel", $detector->detect(["ns1.vercel-dns.com"], ""));
        $this->assertEquals("AWS Route 53", $detector->detect(["ns-123.awsdns-15.com"], ""));
        $this->assertEquals("Azure DNS", $detector->detect(["ns1-01.azure-dns.com"], ""));
        $this->assertEquals(self::CLOUDFLARE, $detector->detect(["AMY.NS.CLOUDFLARE.COM"], ""));

    }//end testDetectsKnownProviders()


    /**
     * Unknown nameservers use the fallback, then the nameserver's domain.
     *
     * @return void
     */
    public function testUnknownNameservers(): void
    {
        $detector = new DnsProviderDetector();
        $this->assertEquals("Nuvem Hospedagem", $detector->detect(["ns1.example-host.com.br"], "Nuvem Hospedagem"));
        $this->assertEquals("example-host.com.br", $detector->detect(["ns1.example-host.com.br"], ""));
        $this->assertEquals("notcloudflare.com", $detector->detect(["ns1.notcloudflare.com"], ""));
        $this->assertEquals(self::CLOUDFLARE, $detector->detect(["ns1.awsdns-backup.example.org"], self::CLOUDFLARE));
        $this->assertEquals("NameCheap", $detector->detect([], "NameCheap"));
        $this->assertEquals("", $detector->detect([], ""));

    }//end testUnknownNameservers()


    /**
     * A mismatch with domains.json is flagged, a match or an unknown value is not.
     *
     * @return void
     */
    public function testFlagMismatch(): void
    {
        $detector = new DnsProviderDetector();
        $this->assertEquals("NameCheap, Inc.", $detector->flagMismatch("NameCheap, Inc.", "NameCheap"));
        $this->assertEquals("NameCheap", $detector->flagMismatch("", "NameCheap"));
        $this->assertEquals(self::CLOUDFLARE, $detector->flagMismatch(self::CLOUDFLARE, ""));
        $this->assertStringContainsString("domains.json says NameCheap", $detector->flagMismatch(self::CLOUDFLARE, "NameCheap"));
        $this->assertEquals("A &amp; B", $detector->flagMismatch("A & B", "A"));

    }//end testFlagMismatch()


    /**
     * Live NS records are normalized, sorted and cached.
     *
     * @return void
     */
    public function testResolveNameservers(): void
    {
        $records = [];
        $records[] = ["target" => "Rob.NS.Cloudflare.com."];
        $records[] = ["target" => "amy.ns.cloudflare.com"];
        $records[] = ["type" => "NS"];
        $detector = $this->createDetector($records);
        $this->assertEquals(["amy.ns.cloudflare.com", "rob.ns.cloudflare.com"], $detector->resolveNameservers("progress-bar.xyz", ["dns1.registrar-servers.com"]));
        $this->assertEquals("amy.ns.cloudflare.com rob.ns.cloudflare.com", $detector->formatNameservers("progress-bar.xyz", []));

    }//end testResolveNameservers()


    /**
     * The live NS records win over the WHOIS ones and flag a stale domains.json.
     *
     * @return void
     */
    public function testDescribeDomainPrefersLiveRecords(): void
    {
        $records = [];
        $records[] = ["target" => "amy.ns.cloudflare.com"];
        $detector = $this->createDetector($records);
        $result = $detector->describeDomain("progress-bar.xyz", ["dns1.registrar-servers.com"], "NameCheap");
        $this->assertStringStartsWith(self::CLOUDFLARE, $result);
        $this->assertStringContainsString("domains.json says NameCheap", $result);

    }//end testDescribeDomainPrefersLiveRecords()


    /**
     * When the DNS lookup fails, the WHOIS nameservers are used.
     *
     * @return void
     */
    public function testDescribeDomainFallsBackToWhois(): void
    {
        $detector = $this->createDetector(false);
        $this->assertEquals("NameCheap", $detector->describeDomain("progress-bar.xyz", ["dns1.registrar-servers.com"], "NameCheap"));
        $this->assertEquals("Registro.br", $detector->describeDomain("example.com.br", [], "Registro.br"));
        $this->assertEquals("dns1.registrar-servers.com", $detector->formatNameservers("progress-bar.xyz", ["dns1.registrar-servers.com"]));
        $this->assertEquals("-", $detector->formatNameservers("example.com.br", []));

    }//end testDescribeDomainFallsBackToWhois()


    /**
     * The real DNS lookup returns an empty list for a domain that cannot exist.
     *
     * @return void
     */
    public function testLookupOfInvalidDomain(): void
    {
        $detector = new DnsProviderDetector();
        $this->assertEquals([], $detector->resolveNameservers("domain.invalid", []));

    }//end testLookupOfInvalidDomain()


}//end class

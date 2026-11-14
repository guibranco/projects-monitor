<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Infers the DNS provider (who hosts the authoritative zone) from a domain's nameservers.
 */
class DnsProviderDetector
{
    /**
     * Nameserver hostname suffix => provider display name.
     * Checked with "ends with", so keep the most specific suffixes first.
     */
    private const PROVIDERS = [
        'ns.cloudflare.com' => 'Cloudflare',
        'registrar-servers.com' => 'NameCheap',
        'namecheaphosting.com' => 'NameCheap',
        'dns.br' => 'Registro.br',
        'vercel-dns.com' => 'Vercel',
        'azure-dns.com' => 'Azure DNS',
        'azure-dns.net' => 'Azure DNS',
        'azure-dns.org' => 'Azure DNS',
        'azure-dns.info' => 'Azure DNS',
        'googledomains.com' => 'Google Cloud DNS',
        'domaincontrol.com' => 'GoDaddy',
        'digitalocean.com' => 'DigitalOcean',
        'nsone.net' => 'NS1',
        'render.com' => 'Render',
    ];

    /**
     * Returns the live NS records for a domain (lowercase, no trailing dot), or [] when the lookup fails.
     */
    public static function lookupNameservers(string $domain): array
    {
        $records = @dns_get_record($domain, DNS_NS);
        if ($records === false) {
            return [];
        }

        $nameservers = array_map(fn ($record) => self::normalize($record['target'] ?? ''), $records);
        $nameservers = array_values(array_filter($nameservers));
        sort($nameservers);
        return $nameservers;
    }

    /**
     * Maps a list of nameservers to a provider name.
     * Unknown nameservers fall back to $fallback (the value from domains.json), then to the NS host's domain.
     */
    public static function detect(array $nameservers, ?string $fallback = null): ?string
    {
        foreach ($nameservers as $nameserver) {
            $nameserver = self::normalize($nameserver);

            if (str_contains($nameserver, '.awsdns-')) {
                return 'AWS Route 53';
            }

            if (preg_match('/^ns-cloud-[a-z]\d\.googledomains\.com$/', $nameserver)) {
                return 'Google Cloud DNS';
            }

            foreach (self::PROVIDERS as $suffix => $provider) {
                if ($nameserver === $suffix || str_ends_with($nameserver, '.' . $suffix)) {
                    return $provider;
                }
            }
        }

        if (!empty($fallback)) {
            return $fallback;
        }

        if (empty($nameservers)) {
            return null;
        }

        // e.g. "ns1.somehost.com.br" -> "somehost.com.br"
        $parts = explode('.', self::normalize($nameservers[0]));
        return implode('.', array_slice($parts, 1)) ?: null;
    }

    private static function normalize(string $nameserver): string
    {
        return rtrim(strtolower(trim($nameserver)), '.');
    }
}

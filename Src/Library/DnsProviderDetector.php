<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Infers the DNS provider (the host of the authoritative zone) from a domain's nameservers.
 */
class DnsProviderDetector
{
    private const PROVIDERS_FILE = "dns-providers.json";

    /**
     * Known providers, each with a nameserver regular expression and a display name.
     *
     * @var array
     */
    private $providers;

    /**
     * Live nameservers already looked up, by domain.
     *
     * @var array
     */
    private $cache = [];


    /**
     * Loads the known providers from dns-providers.json.
     */
    public function __construct()
    {
        $file = sprintf("%s/%s", __DIR__, self::PROVIDERS_FILE);
        $this->providers = json_decode(file_get_contents($file), true);

    }//end __construct()


    /**
     * Describes who manages a domain's DNS, preferring the live NS records over the WHOIS ones.
     *
     * @param string $domain           The domain.
     * @param array  $whoisNameservers The nameservers reported by WHOIS, used when the DNS lookup fails.
     * @param string $configured       The DNS manager configured in domains.json.
     *
     * @return string
     */
    public function describeDomain(string $domain, array $whoisNameservers, string $configured): string
    {
        $nameservers = $this->resolveNameservers($domain, $whoisNameservers);
        return $this->flagMismatch($this->detect($nameservers, $configured), $configured);

    }//end describeDomain()


    /**
     * Lists a domain's nameservers for display, preferring the live NS records over the WHOIS ones.
     *
     * @param string $domain           The domain.
     * @param array  $whoisNameservers The nameservers reported by WHOIS, used when the DNS lookup fails.
     *
     * @return string
     */
    public function formatNameservers(string $domain, array $whoisNameservers): string
    {
        $nameservers = $this->resolveNameservers($domain, $whoisNameservers);
        if (count($nameservers) === 0) {
            return "-";
        }

        return htmlspecialchars(implode(" ", $nameservers), ENT_QUOTES);

    }//end formatNameservers()


    /**
     * Returns the detected value, with a warning icon when it differs from the configured one.
     *
     * @param string $detected   The detected value, empty when unknown.
     * @param string $configured The value configured in domains.json.
     *
     * @return string
     */
    public function flagMismatch(string $detected, string $configured): string
    {
        if ($detected === "") {
            return htmlspecialchars($configured, ENT_QUOTES);
        }

        if ($configured === "" || stripos($detected, $configured) !== false) {
            return htmlspecialchars($detected, ENT_QUOTES);
        }

        return sprintf(
            "%s <span title='domains.json says %s'>&#9888;&#65039;</span>",
            htmlspecialchars($detected, ENT_QUOTES),
            htmlspecialchars($configured, ENT_QUOTES)
        );

    }//end flagMismatch()


    /**
     * Maps nameservers to a provider name.
     *
     * @param array  $nameservers The nameservers of the domain.
     * @param string $fallback    The value to use when no known provider matches, empty for the nameserver's domain.
     *
     * @return string
     */
    public function detect(array $nameservers, string $fallback): string
    {
        foreach ($nameservers as $nameserver) {
            $provider = $this->matchProvider($this->normalize($nameserver));
            if ($provider !== "") {
                return $provider;
            }
        }

        if ($fallback !== "" || count($nameservers) === 0) {
            return $fallback;
        }

        return $this->nameserverDomain($nameservers[0]);

    }//end detect()


    /**
     * Returns the live NS records of a domain (lowercase, sorted, without the trailing dot), or the WHOIS ones when there are none.
     *
     * @param string $domain           The domain to look up.
     * @param array  $whoisNameservers The nameservers reported by WHOIS.
     *
     * @return array
     */
    public function resolveNameservers(string $domain, array $whoisNameservers): array
    {
        if (isset($this->cache[$domain]) === false) {
            $nameservers = [];
            $records = $this->queryDns($domain);
            if (is_array($records) === true) {
                foreach ($records as $record) {
                    $nameservers[] = $this->normalize((string) ($record["target"] ?? ""));
                }
            }

            $nameservers = array_values(array_filter($nameservers));
            sort($nameservers);
            $this->cache[$domain] = $nameservers;
        }

        if (count($this->cache[$domain]) === 0) {
            return $whoisNameservers;
        }

        return $this->cache[$domain];

    }//end resolveNameservers()


    /**
     * Queries the NS records of a domain.
     *
     * @param string $domain The domain to look up.
     *
     * @return array|false
     */
    protected function queryDns(string $domain)
    {
        return @dns_get_record($domain, DNS_NS);

    }//end queryDns()


    /**
     * Returns the name of the first provider whose pattern matches the nameserver.
     *
     * @param string $nameserver The normalized nameserver.
     *
     * @return string
     */
    private function matchProvider(string $nameserver): string
    {
        foreach ($this->providers as $provider) {
            if (preg_match($provider["pattern"], $nameserver) === 1) {
                return $provider["name"];
            }
        }

        return "";

    }//end matchProvider()


    /**
     * Returns the domain of a nameserver, such as host.com.br for ns1.host.com.br.
     *
     * @param string $nameserver The nameserver.
     *
     * @return string
     */
    private function nameserverDomain(string $nameserver): string
    {
        $parts = explode(".", $this->normalize($nameserver));
        return implode(".", array_slice($parts, 1));

    }//end nameserverDomain()


    /**
     * Lowercases a nameserver and removes surrounding spaces and the trailing dot.
     *
     * @param string $nameserver The nameserver.
     *
     * @return string
     */
    private function normalize(string $nameserver): string
    {
        return rtrim(strtolower(trim($nameserver)), ".");

    }//end normalize()


}//end class

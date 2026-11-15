<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\secrets\Ip2WhoIsSecrets;

class Ip2WhoIs
{
    private $request;

    private $headers;

    /**
     * Detects the DNS provider of each domain.
     *
     * @var DnsProviderDetector
     */
    private $dnsDetector;

    public function __construct()
    {
        $config = new Configuration();
        $config->init();

        $this->request = new Request();
        $this->headers = [
            "Accept: application/json",
            constant("USER_AGENT")
        ];
        $this->dnsDetector = new DnsProviderDetector();
    }

    private function getWhoIs($domain)
    {
        $apiKey = Ip2WhoIsSecrets::$ApiKey;
        $url = "https://api.ip2whois.com/v2?key={$apiKey}&domain={$domain}";

        $response = $this->request->get($url, $this->headers);

        if ($response->getStatusCode() != 200) {
            $error = $response->getStatusCode() == -1 ? $response->getMessage() : $response->getBody();
            throw new RequestException("Domain: {$domain} - Code: {$response->getStatusCode()} - Error: {$error}");
        }

        return json_decode($response->getBody());
    }

    /**
     * The WHOIS record of a domain, from the day-old cache when there is one.
     * The cache path is relative to the working directory (Src/api/v1 for
     * the API endpoints), so every endpoint shares it.
     *
     * @param string $domain The domain.
     *
     * @return mixed
     *
     * @throws SecretsFileNotFoundException When the record must be fetched and there is no API key.
     */
    private function loadWhoIs(string $domain): mixed
    {
        $cache = sprintf("cache/domain_%s.json", str_replace(".", "_", $domain));
        if (file_exists($cache) === true && filemtime($cache) > strtotime("-1 day")) {
            return json_decode(file_get_contents($cache));
        }

        if (class_exists(Ip2WhoIsSecrets::class) === false) {
            throw new SecretsFileNotFoundException("File not found: Ip2WhoIsSecrets.php");
        }

        $response = $this->getWhoIs($domain);
        file_put_contents($cache, json_encode($response));
        return $response;

    }//end loadWhoIs()


    /**
     * The expiry date of every domain checked through IP2WHOIS, as rows of
     * domain and expiresAt (Unix time, null when WHOIS gives none).
     *
     * @return array
     */
    public function getExpirations(): array
    {
        $expirations = [];
        foreach ($this->getDomains() as $domainConfig) {
            if (empty($domainConfig["ip2whois"]) === true) {
                continue;
            }

            $response   = $this->loadWhoIs($domainConfig["domain"]);
            $expireTime = strtotime((string) ($response->expire_date ?? ""));
            $expiration = [];

            $expiration["domain"]    = $domainConfig["domain"];
            $expiration["expiresAt"] = null;
            if ($expireTime !== false) {
                $expiration["expiresAt"] = $expireTime;
            }

            $expirations[] = $expiration;
        }

        return $expirations;

    }//end getExpirations()


    private function getDomains()
    {
        $file = __DIR__ . "/domains.json";
        return json_decode(file_get_contents($file), true);
    }

    public function getDomainValidity()
    {
        $domains = $this->getDomains();
        $pattern = '/https?:\/\/[^\s]+/';

        $data = array();
        foreach ($domains as $domainConfig) {
            $domain = $domainConfig["domain"];
            $link = "<a href='https://whois.domaintools.com/$domain' target='_blank' rel='noopener noreferrer'>$domain</a>";
            $registrar = $domainConfig["registrar"];
            $dnsManager = $this->dnsDetector->describeDomain($domain, [], $domainConfig["dnsManager"]);

            if (empty($domainConfig["ip2whois"])) {
                $nameservers = $this->dnsDetector->formatNameservers($domain, []);
                $data[] = [
                    $link,
                    $registrar,
                    $dnsManager,
                    "-",
                    "-",
                    "-",
                    $nameservers
                ];
                continue;
            }

            $response = $this->loadWhoIs($domain);

            $createdTime = strtotime($response->create_date);
            $createdDate = date("d/m/Y", $createdTime);
            $createdDays = round((time() - $createdTime) / (60 * 60 * 24));
            $createdColor = "green";
            if ($createdDays < 30) {
                $createdColor = "red";
            } elseif ($createdDays < 365) {
                $createdColor = "orange";
            } elseif ($createdDays < 720) {
                $createdColor = "yellow";
            }

            $expireTime = strtotime($response->expire_date);
            $expireDate = date("d/m/Y", $expireTime);
            $expireDays = round(($expireTime - time()) / (60 * 60 * 24));
            $expireDaysString = ($expireDays >= 0 ? "$expireDays" : "-$expireDays");
            $expireColor = "green";
            if ($expireDays < 10) {
                $expireColor = "red";
            } elseif ($expireDays < 30) {
                $expireColor = "orange";
            } elseif ($expireDays < 90) {
                $expireColor = "yellow";
            }

            $createdImg = "<img alt='Created date' src='https://img.shields.io/badge/" . str_replace("/", "%2F", $createdDate) . "-" . $createdDays . "_days-" . $createdColor . "?style=for-the-badge&labelColor=black' />";
            $expireImg = "<img alt='Expire date' src='https://img.shields.io/badge/" . str_replace("/", "%2F", $expireDate) . "-In_" . $expireDaysString . "_days-" . $expireColor . "?style=for-the-badge&labelColor=black' />";
            $dnsManager = $this->dnsDetector->describeDomain($domain, (array) $response->nameservers, $domainConfig["dnsManager"]);
            $registrar = $this->dnsDetector->flagMismatch((string) ($response->registrar->name ?? ""), $domainConfig["registrar"]);
            $status = preg_replace($pattern, '', $response->status);
            $nameservers = $this->dnsDetector->formatNameservers($domain, (array) $response->nameservers);
            $data[] = [
                $link,
                $registrar,
                $dnsManager,
                $createdImg,
                $expireImg,
                $status,
                $nameservers
            ];
        }

        $columns = [
            "Domain",
            "Registrar",
            "DNS Manager",
            "Created Date",
            "Expire Date",
            "Status",
            "Nameservers"
        ];
        sort($data);
        array_unshift($data, $columns);
        return $data;
    }
}

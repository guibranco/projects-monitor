<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\secrets\Ip2WhoIsSecrets;

class Ip2WhoIs
{
    private $request;

    private $headers;

    public function __construct()
    {
        $this->request = new Request();
        $this->headers = [
            "Accept: application/json",
            "User-Agent: ProjectsMonitor/1.0 (+https://github.com/guibranco/projects-monitor)"
        ];
    }

    private function getWhoIs($domain)
    {
        $apiKey = Ip2WhoIsSecrets::$ApiKey;
        $url = "https://api.ip2whois.com/v2?key=$apiKey&domain=$domain";

        $response = $this->request->get($url, $this->headers);

        if ($response->statusCode != 200) {
            throw new RequestException("Code: {$response->statusCode} - Error: {$response->body}");
        }

        return json_decode($response->body);
    }

    public function getDomainValidity()
    {
        $domains = [
            "guilhermebranco.com.br",
            "zerocool.com.br",
            "apibr.com",
            "gstraccini.bot",
            "gstraccini.dev",
            "straccini.com",
            "straccini.com.br",
            "stracini.com",
            "stracini.com.br"
        ];
        $pattern = '/https?:\/\/[^\s]+/';

        $data = array();
        foreach ($domains as $domain) {
            $response = $this->getWhoIs($domain);
            $createdTime = strtotime($response->create_date);
            $expireTime = strtotime($response->expire_date);
            $createdDate = date("d/m/Y", $createdTime);
            $expireDate = date("d/m/Y", $expireTime);
            $expireDays = round(($expireTime - time()) / (60 * 60 * 24));
            $color = "green";
            if ($expireDays < 10) {
                $color = "red";
            } elseif ($expireDays < 30) {
                $color = "orange";
            } elseif ($expireDays < 90) {
                $color = "yellow";
            }
            $expireImg = "<img alt='Expire date' src='https://img.shields.io/badge/" . ($expireDays >= 0 ? $expireDays : "-$expireDays") . "_days-" . str_replace("/", "%2F", $expireDate) . "-" . $color . "?style=for-the-badge&labelColor=black' />";
            $status = preg_replace($pattern, '', $response->status);
            $nameservers = implode(" ", $response->nameservers);
            $data[] = array("<a href='https://$domain' target='_blank'>$domain</a>", $createdDate, $expireImg, $response->domain_age, $status, $nameservers);
        }

        sort($data);
        array_unshift($data, array("Domain", "Created Date", "Expire Date", "Domain Age", "Status", "Nameservers"));
        return $data;
    }
}

<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\secrets\Ip2WhoIsSecrets;

class Ip2WhoIs
{
    private $request;

    private $headers;

    public function __construct()
    {
        $config = new Configuration();
        $config->init();
        
        $this->request = new Request();
        $this->headers = [
            "Accept: application/json",
            constant("USER_AGENT")
        ];
    }

    private function getWhoIs($domain)
    {
        $apiKey = Ip2WhoIsSecrets::$ApiKey;
        $url = "https://api.ip2whois.com/v2?key=$apiKey&domain=$domain";

        $response = $this->request->get($url, $this->headers);

        if ($response->statusCode != 200) {
            $error = $response->statusCode == -1 ? $response->error : $response->body;
            throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
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
            "progress-bar.xyz",
            "straccini.com",
            "straccini.com.br",
            "stracini.com",
            "stracini.com.br"
        ];
        $pattern = '/https?:\/\/[^\s]+/';

        $data = array();
        foreach ($domains as $domain) {

            $cache = "cache/domain_" . str_replace(".", "_", $domain) . ".json";
            if (file_exists($cache) && filemtime($cache) > strtotime("-1 day")) {
                $response = json_decode(file_get_contents($cache));
            } else {
                $response = $this->getWhoIs($domain);
                file_put_contents($cache, json_encode($response));
            }

            $link = "<a href='https://whois.domaintools.com/$domain' target='_blank'>$domain</a>";

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
            $status = preg_replace($pattern, '', $response->status);
            $nameservers = implode(" ", $response->nameservers);
            $data[] = [
                $link,
                $createdImg,
                $expireImg,
                $status,
                $nameservers
            ];
        }

        $columns = [
            "Domain",
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

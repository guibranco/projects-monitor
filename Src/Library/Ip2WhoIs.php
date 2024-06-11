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
            $createDate = date("d/M/Y", strtotime($response->create_date));
            $expireDate = date("d/M/Y", strtotime($response->expire_date));
            $status = preg_replace($pattern, '', $response->status);
            $nameservers = implode(",", $response->nameservers);
            $data[] = array($domain, $createDate, $expireDate, $response->domain_age, $status, $nameservers);
        }

        sort($data);
        array_unshift($data, array("Domain", "Create Date", "Expire Date", "Domain Age", "Status", "Nameservers"));
        return $data;
    }
}
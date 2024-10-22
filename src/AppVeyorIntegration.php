<?php

class AppVeyorIntegration
{
    private $apiToken;
    private $baseUrl = 'https://ci.appveyor.com/api';

    public function __construct($apiToken)
    {
        $this->apiToken = $apiToken;
    }

    private function makeApiRequest($endpoint)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json'
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    public function getBuildInfo($projectSlug)
    {
        return $this->makeApiRequest('/projects/' . $projectSlug . '/builds');
    }
}

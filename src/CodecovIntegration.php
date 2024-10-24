<?php

class CodecovIntegration {
    private $apiToken;
    private $baseUrl = 'https://api.codecov.io';

    public function __construct($apiToken) {
        $this->apiToken = $apiToken;
    }

    public function fetchCoverageData($repository) {
        $url = $this->baseUrl . '/v2/' . $repository . '/coverage';
        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}

?>

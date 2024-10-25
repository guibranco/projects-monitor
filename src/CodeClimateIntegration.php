<?php

class CodeClimateIntegration {
    private $apiKey;
    private $baseUrl = 'https://api.codeclimate.com/v1';

    public function __construct() {
        $this->apiKey = getenv('CODECLIMATE_API_KEY');
    }

    private function getHeaders() {
        return [
            'Authorization: Token ' . $this->apiKey,
            'Content-Type: application/json',
        ];
    }

    public function fetchRepositoryData($repositoryName) {
        $url = $this->baseUrl . '/repos/' . urlencode($repositoryName);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Error fetching data from CodeClimate API');
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error decoding JSON response from CodeClimate API');
        }

        return $data;
    }
}

?>

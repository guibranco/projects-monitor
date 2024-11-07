<?php

namespace Src;

use GuiBranco\Pancake\Request;

class GitGuardianIntegration
{
    private $apiKey;
    private $apiUrl = 'https://api.gitguardian.com';

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function scanRepository($repository)
    {
        $endpoint = $this->apiUrl . '/v1/repositories/' . $repository . '/secrets';
        $request = new Request('GET', $endpoint);
        $request->setHeader('Authorization', 'Bearer ' . $this->apiKey);

        try {
            $response = $request->send();
            return $response->getBody();
        } catch (Exception $e) {
            // Handle error
            return null;
        }
    }
}

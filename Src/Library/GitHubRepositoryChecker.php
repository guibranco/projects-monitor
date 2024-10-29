<?php

class GitHubRepositoryChecker {

    private $apiClient;

    public function __construct($apiClient) {
        $this->apiClient = $apiClient;
    }

    public function checkDependabotFile($repository) {
        $path = '.github/dependabot.yml';
        $response = $this->apiClient->get("/repos/{$repository}/contents/{$path}");
        return $response && $response->getStatusCode() === 200;
    }

}

?>

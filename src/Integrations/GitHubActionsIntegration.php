<?php

namespace Integrations;

class GitHubActionsIntegration {
    private $token;
    private $apiUrl = 'https://api.github.com';

    public function __construct($token) {
        $this->token = $token;
    }

    private function request($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $this->token,
            'User-Agent: GitHubActionsIntegration'
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    public function getWorkflows($owner, $repo) {
        $url = "$this->apiUrl/repos/$owner/$repo/actions/workflows";
        return $this->request($url);
    }

    public function getWorkflowRuns($owner, $repo, $workflowId) {
        $url = "$this->apiUrl/repos/$owner/$repo/actions/workflows/$workflowId/runs";
        return $this->request($url);
    }
}

?>

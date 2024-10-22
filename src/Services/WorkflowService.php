<?php

namespace Services;

use Integrations\GitHubActionsIntegration;

class WorkflowService {
    private $db;
    private $githubIntegration;

    public function __construct($db, $githubToken) {
        $this->db = $db;
        $this->githubIntegration = new GitHubActionsIntegration($githubToken);
    }

    public function fetchWorkflowsAndStore() {
        $repositories = $this->getRepositories();
        foreach ($repositories as $repository) {
            $owner = $repository['owner'];
            $repo = $repository['name'];
            $workflows = $this->githubIntegration->getWorkflows($owner, $repo);
            foreach ($workflows['workflows'] as $workflow) {
                $workflowId = $workflow['id'];
                $workflowName = $workflow['name'];
                $runs = $this->githubIntegration->getWorkflowRuns($owner, $repo, $workflowId);
                if (!empty($runs['workflow_runs'])) {
                    $lastRun = $runs['workflow_runs'][0];
                    $lastRunStatus = $lastRun['conclusion'];
                    $lastRunTimestamp = $lastRun['updated_at'];
                    $this->storeWorkflowData($repository['id'], $workflowName, $lastRunStatus, $lastRunTimestamp);
                }
            }
        }
    }

    private function getRepositories() {
        $stmt = $this->db->prepare('SELECT id, owner, name FROM repositories');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function storeWorkflowData($repositoryId, $workflowName, $lastRunStatus, $lastRunTimestamp) {
        $stmt = $this->db->prepare('
            INSERT INTO github_workflows (repository_id, workflow_name, last_run_status, last_run_timestamp)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            last_run_status = VALUES(last_run_status),
            last_run_timestamp = VALUES(last_run_timestamp)
        ');
        $stmt->execute([$repositoryId, $workflowName, $lastRunStatus, $lastRunTimestamp]);
    }
}

?>

<?php

require_once 'CodeClimateIntegration.php';

class RepositoryMetricsUpdater {
    private $db;
    private $codeClimate;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
        $this->codeClimate = new CodeClimateIntegration();
    }

    public function updateMetrics() {
        $repositories = $this->getRepositories();
        foreach ($repositories as $repository) {
            $metrics = $this->codeClimate->fetchRepositoryData($repository['name']);
            $this->storeMetrics($repository['id'], $metrics);
        }
    }

    private function getRepositories() {
        $query = "SELECT id, name FROM repositories WHERE active = 1";
        $result = $this->db->query($query);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }

    private function storeMetrics($repositoryId, $metrics) {
        $query = "INSERT INTO codeclimate_metrics (repository_id, gpa, issues_count, maintainability_index, last_updated) 
                  VALUES (:repository_id, :gpa, :issues_count, :maintainability_index, NOW()) 
                  ON DUPLICATE KEY UPDATE gpa = :gpa, issues_count = :issues_count, maintainability_index = :maintainability_index, last_updated = NOW()";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':repository_id', $repositoryId);
        $stmt->bindParam(':gpa', $metrics['gpa']);
        $stmt->bindParam(':issues_count', $metrics['issues_count']);
        $stmt->bindParam(':maintainability_index', $metrics['maintainability_index']);

        try {
            $stmt->execute();
        } catch (Exception $e) {
            error_log('Failed to store metrics for repository ID ' . $repositoryId . ': ' . $e->getMessage());
        }
    }
}

?>

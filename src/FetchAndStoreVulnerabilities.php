<?php

require_once 'SnykIntegration.php';

class FetchAndStoreVulnerabilities
{
    private $db;
    private $snykIntegration;

    public function __construct($dbConnection, $snykApiToken)
    {
        $this->db = $dbConnection;
        $this->snykIntegration = new SnykIntegration($snykApiToken);
    }

    public function execute()
    {
        $repositories = $this->getRepositories();
        foreach ($repositories as $repository) {
            $vulnerabilities = $this->snykIntegration->fetchVulnerabilities($repository['id']);
            $this->storeVulnerabilities($repository['id'], $vulnerabilities);
        }
    }

    private function getRepositories()
    {
        $query = "SELECT id FROM repositories WHERE active = 1";
        $result = $this->db->query($query);
        return $result->fetchAll();
    }

    private function storeVulnerabilities($repositoryId, $vulnerabilities)
    {
        $query = "INSERT INTO snyk_vulnerabilities (repository_id, vulnerability_count, critical_issues, high_issues, created_at, updated_at) 
                  VALUES (:repository_id, :vulnerability_count, :critical_issues, :high_issues, NOW(), NOW())
                  ON CONFLICT (repository_id) DO UPDATE SET 
                  vulnerability_count = EXCLUDED.vulnerability_count, 
                  critical_issues = EXCLUDED.critical_issues, 
                  high_issues = EXCLUDED.high_issues, 
                  updated_at = NOW();";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':repository_id' => $repositoryId,
            ':vulnerability_count' => $vulnerabilities['count'],
            ':critical_issues' => $vulnerabilities['critical'],
            ':high_issues' => $vulnerabilities['high'],
        ]);
    }
}

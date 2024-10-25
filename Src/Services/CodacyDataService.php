<?php

namespace Src\Services;

class CodacyDataService
{
    private $repositoryService;
    private $codacyIntegration;
    private $database;
    private $logger;

    public function __construct($repositoryService, $codacyIntegration, $database, $logger)
    {
        $this->repositoryService = $repositoryService;
        $this->codacyIntegration = $codacyIntegration;
        $this->database = $database;
        $this->logger = $logger;
    }

    public function updateCodacyData()
    {
        $repositories = $this->repositoryService->getAllRepositories();

        foreach ($repositories as $repository) {
            $codacyData = $this->codacyIntegration->fetchRepositoryData($repository->name);

            if ($codacyData) {
                $qualityScore = $codacyData['quality_score'];
                $issuesCount = $codacyData['issues_count'];

                $this->database->query(
                    'REPLACE INTO codacy_info (repository_id, quality_score, issues_count, last_updated) VALUES (?, ?, ?, NOW())',
                    [$repository->id, $qualityScore, $issuesCount]
                );
            } else {
                $this->logger->warning("Codacy data not available for repository: " . $repository->name);
            }
        }
    }
}

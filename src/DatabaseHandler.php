<?php

class DatabaseHandler
{
    private $pdo;

    public function __construct($dsn, $username, $password)
    {
        $this->pdo = new PDO($dsn, $username, $password);
    }

    public function getRepositories()
    {
        $stmt = $this->pdo->query('SELECT id, name FROM repositories');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCodecovInfoTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS codecov_info (
            id INT AUTO_INCREMENT PRIMARY KEY,
            repository_id INT NOT NULL,
            coverage_percentage FLOAT NOT NULL,
            lines_covered INT NOT NULL,
            total_lines INT NOT NULL,
            FOREIGN KEY (repository_id) REFERENCES repositories(id)
        )";
        $this->pdo->exec($sql);
    }

    public function insertOrUpdateCoverageData($repositoryId, $coveragePercentage, $linesCovered, $totalLines)
    {
        $sql = "INSERT INTO codecov_info (repository_id, coverage_percentage, lines_covered, total_lines) 
                VALUES (:repository_id, :coverage_percentage, :lines_covered, :total_lines) 
                ON DUPLICATE KEY UPDATE 
                coverage_percentage = VALUES(coverage_percentage), 
                lines_covered = VALUES(lines_covered), 
                total_lines = VALUES(total_lines)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':repository_id' => $repositoryId,
            ':coverage_percentage' => $coveragePercentage,
            ':lines_covered' => $linesCovered,
            ':total_lines' => $totalLines
        ]);
    }
}

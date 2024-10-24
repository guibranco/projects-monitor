<?php

require_once 'CodecovIntegration.php';
require_once 'DatabaseHandler.php';

$apiToken = 'your_codecov_api_token';
$dsn = 'mysql:host=localhost;dbname=your_database';
$username = 'your_db_username';
$password = 'your_db_password';

$codecov = new CodecovIntegration($apiToken);
$dbHandler = new DatabaseHandler($dsn, $username, $password);

$dbHandler->createCodecovInfoTable();
$repositories = $dbHandler->getRepositories();

foreach ($repositories as $repository) {
    $coverageData = $codecov->fetchCoverageData($repository['name']);
    if ($coverageData) {
        $coveragePercentage = $coverageData['coverage_percentage'];
        $linesCovered = $coverageData['lines_covered'];
        $totalLines = $coverageData['total_lines'];

        $dbHandler->insertOrUpdateCoverageData(
            $repository['id'], 
            $coveragePercentage, 
            $linesCovered, 
            $totalLines
        );
    }
}

?>

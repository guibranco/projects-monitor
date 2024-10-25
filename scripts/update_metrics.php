<?php

require_once '../src/RepositoryMetricsUpdater.php';

$dbConnection = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
$dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$metricsUpdater = new RepositoryMetricsUpdater($dbConnection);

try {
    $metricsUpdater->updateMetrics();
    echo "Metrics updated successfully.\n";
} catch (Exception $e) {
    echo "An error occurred while updating metrics: " . $e->getMessage() . "\n";
}

?>

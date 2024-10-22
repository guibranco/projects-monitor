<?php

require_once 'src/AppVeyorIntegration.php';

// Database connection (assuming PDO)
$pdo = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');

// Fetch all repositories
$stmt = $pdo->query('SELECT id, name FROM repositories');
$repositories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$appVeyor = new AppVeyorIntegration('your_appveyor_api_token');

foreach ($repositories as $repo) {
    // Check for appveyor.yml using GitHub API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/{$repo['name']}/contents/appveyor.yml");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: YourAppName']);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response && json_decode($response, true)) {
        // Fetch build info from AppVeyor
        $buildInfo = $appVeyor->getBuildInfo($repo['name']);

        if ($buildInfo) {
            // Extract necessary data
            $buildStatus = $buildInfo['builds'][0]['status'];
            $lastRunTime = $buildInfo['builds'][0]['finished'];
            $buildVersion = $buildInfo['builds'][0]['version'];

            // Insert or update the appveyor_builds table
            $stmt = $pdo->prepare('INSERT INTO appveyor_builds (repository_id, build_status, last_run_time, build_version, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE build_status = VALUES(build_status), last_run_time = VALUES(last_run_time), build_version = VALUES(build_version), updated_at = NOW()');
            $stmt->execute([$repo['id'], $buildStatus, $lastRunTime, $buildVersion]);
        }
    }
}

echo "AppVeyor build information updated successfully.";

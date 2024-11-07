<?php

// Daemon script to monitor and process error_log files from CPanel API

$cpanelApiUrl = 'https://your-cpanel-server:2083/execute/Fileman/get_file_list';
$apiToken = 'your-cpanel-api-token';

$options = [
    'http' => [
        'header' => "Authorization: cpanel your-cpanel-username:$apiToken\r\n",
        'method' => 'GET',
    ],
];
$context = stream_context_create($options);
$response = file_get_contents($cpanelApiUrl, false, $context);
$files = json_decode($response, true);

$db = new mysqli('hostname', 'username', 'password', 'database');

foreach ($files as $file) {
    $filePath = $file['path'];
    $newFileName = $filePath . '_processed';
    rename($filePath, $newFileName);
    
    $contents = file_get_contents($newFileName);
    // Parse the contents
    $errors = parseErrors($contents);

    foreach ($errors as $error) {
        $stmt = $db->prepare('INSERT INTO errors (error_message) VALUES (?)');
        $stmt->bind_param('s', $error);
        $stmt->execute();
    }

    unlink($newFileName); // Delete the processed file
}

function parseErrors($contents) {
    // Implement error parsing logic
    return explode("\n", $contents); // Example logic
}

?>

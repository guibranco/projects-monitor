<?php

require_once 'session_validator.php';

$version = '0.0.0-dev';
$versionFile = __DIR__ . '/../../version.txt';
if (file_exists($versionFile)) {
    $v = trim(file_get_contents($versionFile));
    if ($v !== '') {
        $version = $v;
    }
}

echo json_encode(["version" => $version]);

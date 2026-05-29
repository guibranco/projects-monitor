<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$version = '0.0.0-dev';
$versionFile = __DIR__ . '/../../version.txt';
if (file_exists($versionFile)) {
    $v = trim(file_get_contents($versionFile));
    if ($v !== '') {
        $version = $v;
    }
}

$spec = json_decode(file_get_contents(__DIR__ . '/openapi.json'), true);
$spec['info']['version'] = $version;

echo json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

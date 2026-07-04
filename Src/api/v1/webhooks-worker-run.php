<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Webhooks;
use GuiBranco\ProjectsMonitor\Library\LogStream;

$requestBody = file_get_contents("php://input");
$input = json_decode($requestBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        "error" => "Invalid JSON provided",
        "details" => json_last_error_msg()
    ]);
    exit;
}

if (!isset($input['name'])) {
    http_response_code(400);
    echo json_encode(["error" => "Field 'name' is required"]);
    exit;
}

$allowedWorkers = ['service', 'cleanup', 'database-service', 'maintenance'];
$name = filter_var($input['name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!in_array($name, $allowedWorkers, true)) {
    http_response_code(422);
    echo json_encode(["error" => "Unknown worker name: {$name}"]);
    exit;
}

LogStream::info("API request received", ["endpoint" => "POST /api/v1/webhooks-workers/run", "worker" => $name], "api");
$webhooks = new Webhooks();
$data = $webhooks->runWorker($name);
echo json_encode($data);

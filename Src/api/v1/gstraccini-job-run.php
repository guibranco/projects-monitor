<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\GStracciniBot;
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

if (!isset($input['job'])) {
    http_response_code(400);
    echo json_encode(["error" => "Field 'job' is required"]);
    exit;
}

$allowedJobs = ['branches', 'comments', 'issues', 'pullRequests', 'pushes', 'repositories', 'signature'];
$job = filter_var($input['job'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!in_array($job, $allowedJobs, true)) {
    http_response_code(422);
    echo json_encode(["error" => "Unknown job name: {$job}"]);
    exit;
}

LogStream::info("API request received", ["endpoint" => "POST /api/v1/gstraccini-jobs/run", "job" => $job], "api");
$bot = new GStracciniBot();
$data = $bot->runJob($job);
echo json_encode($data);

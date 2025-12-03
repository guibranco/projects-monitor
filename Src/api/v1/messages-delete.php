<?php

require_once 'validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Logger;

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

if (isset($input['application']) === false) {
    http_response_code(400);
    echo json_encode(["error" => "Application name is required"]);
    exit;
}

$applicationName = filter_var(urldecode($input['application']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$log = new Logger();
$result = $log->deleteMessagesByApplication($applicationName);

if ($result === false) {
    http_response_code(500);
    echo json_encode(["error" => "An error occurred while deleting the messages"]);
    exit;
}

$total = $log->getTotal();
$byApplications = $log->getTotalByApplications();
$grouped = $log->getGroupedMessages();

$data = [
    "total" => $total,
    "byApplications" => $byApplications,
    "grouped" => $grouped
];

echo json_encode($data);

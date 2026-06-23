<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Logger;

$requestBody = file_get_contents("php://input");
$input = json_decode($requestBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON provided", "details" => json_last_error_msg()]);
    exit;
}

$sampleId = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$sampleId || $sampleId < 1) {
    http_response_code(400);
    echo json_encode(["error" => "A valid message ID is required"]);
    exit;
}

$log    = new Logger();
$result = $log->deleteMessagesByGroupSampleId((int)$sampleId);

if ($result === false) {
    http_response_code(500);
    echo json_encode(["error" => "An error occurred while deleting the message group"]);
    exit;
}

$total          = $log->getTotal();
$byApplications = $log->getTotalByApplications();
$grouped        = $log->getGroupedMessages();

echo json_encode([
    "total"          => $total,
    "byApplications" => $byApplications,
    "grouped"        => $grouped,
]);

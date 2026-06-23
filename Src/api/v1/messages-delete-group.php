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

if (!isset($input['application']) || !isset($input['message'])) {
    http_response_code(400);
    echo json_encode(["error" => "Application and message are required"]);
    exit;
}

$application = filter_var(urldecode($input['application']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$message     = $input['message'];
$userAgent   = $input['user_agent'] ?? '';

$log    = new Logger();
$result = $log->deleteMessagesByGroup($application, $message, $userAgent);

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

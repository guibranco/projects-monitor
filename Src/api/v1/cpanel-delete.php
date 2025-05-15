<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';
header('Content-Type: application/json');

use GuiBranco\ProjectsMonitor\Library\CPanel;

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

if (isset($input['directory']) === false) {
    http_response_code(400);
    echo json_encode(["error" => "Directory is required"]);
    exit;
}

$directory = filter_var(urldecode($input['directory']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$cPanel = new CPanel();
$result = $cPanel->deleteErrorLogFile($directory);

if ($result === false) {
    http_response_code(500);
    echo json_encode(["error" => "An error occurred while deleting the error_log file at {$directory}"]);
    exit;
}

$logMessages = $cPanel->getErrorLogMessages();
$data["error_log_files"] = $cPanel->getErrorLogFiles();
$data["error_log_messages"] = $logMessages;
$data["total_error_messages"] = count($logMessages) > 1 ? count($logMessages) - 1 : 0;
$data["cronjobs"] = $cPanel->getCrons();
$data["emails"] = $cPanel->getInboxMessagesCount();
try {
    $data["usage"] = $cPanel->getUsageData();
} catch (Exception $e) {
    $data["usage"] = null;
    $data["errors"][] = "Failed to retrieve usage data: " . $e->getMessage();
}
echo json_encode($data);

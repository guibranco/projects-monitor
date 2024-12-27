<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Logger;

$requestBody = file_get_contents("php://input");
$input = json_decode($requestBody, true);

if (isset($input['application']) === false) {
    http_response_code(400);
    echo json_encode(["error" => "Application name is required"]);
    exit;
}

$applicationName = filter_var($input['application'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$log = new Logger();
$log->deleteMessagesByApplication($applicationName);

$total = $log->getTotal();
$byApplications = $log->getTotalByApplications();
$grouped = $log->getGroupedMessages();

$data = [
    "total" => $total,
    "byApplications" => $byApplications,
    "grouped" => $grouped
];

echo json_encode($data);

<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Logger;

if (isset($_POST["application"]) === false) {
    http_response_code(400);
    echo json_encode(["error" => "Application name is required"]);
    exit;
}

$log = new Logger();
$applicationName = filter_input(INPUT_POST, 'application', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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

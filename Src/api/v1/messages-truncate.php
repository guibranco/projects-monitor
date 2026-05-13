<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Logger;

$log = new Logger();
$result = $log->truncateMessages();

if ($result === false) {
    http_response_code(500);
    echo json_encode(["error" => "An error occurred while truncating the messages table"]);
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

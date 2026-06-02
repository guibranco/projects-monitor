<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Logger;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/messages"], "api");
$log = new Logger();
$total = $log->getTotal();
$byApplications = $log->getTotalByApplications();
$grouped = $log->getGroupedMessages();

$data = [
    "total" => $total,
    "byApplications" => $byApplications,
    "grouped" => $grouped
];
echo json_encode($data);

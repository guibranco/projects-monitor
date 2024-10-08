<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Logger;

$log = new Logger();
$total = $log->getTotal();
$byApplications = $log->getTotalByApplications();
$grouped = $log->getGroupedMessages();

$data = [
    "total" => $total,
    "byApplications" => $byApplications,
    "grouped" => $grouped
];

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

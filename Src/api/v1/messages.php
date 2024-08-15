<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Logger;

$quantity = isset($_GET["quantity"]) ? intval($_GET["quantity"]) : 100;

$log = new Logger();
$total = $log->getTotal();
$byApplications = $log->getTotalByApplications();
$grouped = $log->getGroupedMessages();
$messages = $log->showLastMessages($quantity);

$result = [
    "total" => $total,
    "byApplications" => $byApplications,
    "grouped" => $grouped,
    "messages" => $messages
];
header("Content-Type: application/json; charset=UTF-8");
echo json_encode($result);

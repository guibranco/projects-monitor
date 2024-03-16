<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Logger;

$quantity = isset($_GET["quantity"]) ? intval($_GET["quantity"]) : 100;

$log = new Logger();
$total = $log->getTotal();
$byApplications = $log->getTotalByApplications();
$messages = $log->showLastMessages($quantity);

$result = [
    "total" => $total,
    "byApplications" => $byApplications,
    "messages" => $messages
];

echo json_encode($result);

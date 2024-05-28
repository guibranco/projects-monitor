<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Webhooks;

$webhooks = new Webhooks();
$data = $webhooks->getDashboard();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

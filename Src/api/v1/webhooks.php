<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Webhooks;

$feedOptionsFilter = isset($_GET["feedOptionsFilter"]) ? $_GET["feedOptionsFilter"] : "all";

$webhooks = new Webhooks();
$data = $webhooks->getDashboard($feedOptionsFilter);
echo json_encode($data);

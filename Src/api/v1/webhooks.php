<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Webhooks;

$allowedFilters = ['all', 'active', 'completed']; // Define allowed values
$feedOptionsFilter = isset($_GET["feedOptionsFilter"]) && in_array($_GET["feedOptionsFilter"], $allowedFilters) 
    ? $_GET["feedOptionsFilter"] 
    : "all";

$webhooks = new Webhooks();
$data = $webhooks->getDashboard($feedOptionsFilter);
echo json_encode($data);

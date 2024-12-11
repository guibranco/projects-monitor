<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Webhooks;

$allowedFilters = ['all', 'mine'];
$feedOptionsFilter = isset($_GET["feedOptionsFilter"]) && in_array($_GET["feedOptionsFilter"], $allowedFilters)
    ? $_GET["feedOptionsFilter"]
    : "all";

$workflowsLimiterEnabled = isset($_GET["workflowsLimiterEnabled"]) ? ($_GET["workflowsLimiterEnabled"] === "true") : false;
$workflowsLimiterQuantity = $workflowsLimiterEnabled && isset($_GET["workflowsLimiterQuantity"]) ? intval($_GET["workflowsLimiterQuantity"]) : 0;

$webhooks = new Webhooks();
$data = $webhooks->getDashboard($feedOptionsFilter, $workflowsLimiterQuantity);
echo json_encode($data);

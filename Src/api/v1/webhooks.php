<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Webhooks;

$allowedFilters = ['all', 'mine'];
$feedOptionsFilter = isset($_GET["feedOptionsFilter"]) && in_array($_GET["feedOptionsFilter"], $allowedFilters)
    ? $_GET["feedOptionsFilter"]
    : "all";

$workflowsLimiterEnabled = filter_var(
    $_GET["workflowsLimiterEnabled"] ?? false,
    FILTER_VALIDATE_BOOLEAN
);
$maxLimit = 10000;
$workflowsLimiterQuantity = $workflowsLimiterEnabled
    ? filter_var(
        $_GET["workflowsLimiterQuantity"] ?? 0,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1, "max_range" => $maxLimit]]
    ) ?: 0
    : 0;

$webhooks = new Webhooks();
$data = $webhooks->getDashboard($feedOptionsFilter, $workflowsLimiterQuantity);
echo json_encode($data);

<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Webhooks;
use GuiBranco\ProjectsMonitor\Library\LogStream;

$allowedFilters = ['all', 'mine'];
$feedOptionsFilter = isset($_GET["feedOptionsFilter"]) && in_array($_GET["feedOptionsFilter"], $allowedFilters)
    ? $_GET["feedOptionsFilter"]
    : "all";

LogStream::info("API request received", ["endpoint" => "GET /api/v1/webhooks", "filter" => $feedOptionsFilter], "api");
$webhooks = new Webhooks();
$data = $webhooks->getDashboard($feedOptionsFilter);
echo json_encode($data);

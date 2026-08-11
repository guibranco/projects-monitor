<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\SSH;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/system-report"], "api");

$reports = array();

foreach (SSH::getHosts() as $hostConfig) {
    $ssh = new SSH($hostConfig);
    $reports[] = array(
        "name"    => $ssh->getName(),
        "rows"    => $ssh->getSystemReport(),
        "metrics" => $ssh->getResourceUsage()["metrics"],
    );
}

$data = array();
$data["system_reports"] = $reports;
echo json_encode($data);

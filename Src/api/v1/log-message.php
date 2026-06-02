<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Application;
use GuiBranco\ProjectsMonitor\Library\Logger;
use GuiBranco\ProjectsMonitor\Library\LogStream;

$application = new Application();

if (!$application->validate()) {
    die();
}

LogStream::info("API request received", ["endpoint" => "POST /api/v1/log-message", "application_id" => $application->getApplicationId()], "api");
$log = new Logger();
$result = $log->saveMessage($application->getApplicationId());
if ($result) {
    http_response_code(202);
    LogStream::debug("Log message saved", ["application_id" => $application->getApplicationId()], "api");
} else {
    http_response_code(500);
    LogStream::error("Failed to save log message", ["application_id" => $application->getApplicationId()], "api");
}

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($result);

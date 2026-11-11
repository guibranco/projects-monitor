<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Application;
use GuiBranco\ProjectsMonitor\Library\Logger;
use GuiBranco\ProjectsMonitor\Library\LogStream;

$application = new Application();

if (!$application->validate()) {
    die();
}

$logStream = new LogStream();
$logStream->info("API request {request.method} {request.path} received from application {application_id}", ["application_id" => $application->getApplicationId()], "api");
$log = new Logger();
$result = $log->saveMessage($application->getApplicationId());
if ($result) {
    http_response_code(202);
    $logStream->debug("Log message from application {application_id} saved", ["application_id" => $application->getApplicationId()], "api");
} else {
    http_response_code(500);
    $logStream->error("Failed to save log message from application {application_id}", ["application_id" => $application->getApplicationId()], "api");
}

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($result);

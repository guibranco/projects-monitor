<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\UpTimeRobot;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/uptimerobot"], "api");
$upTimeRobot = new UpTimeRobot();
$data["monitors"] = $upTimeRobot->getMonitors();
echo json_encode($data);

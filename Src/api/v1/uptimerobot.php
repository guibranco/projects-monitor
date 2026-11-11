<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\UpTimeRobot;

$upTimeRobot = new UpTimeRobot();
$data["monitors"] = $upTimeRobot->getMonitors();
echo json_encode($data);

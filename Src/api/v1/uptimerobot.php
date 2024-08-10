<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\UpTimeRobot;

$upTimeRobot = new UpTimeRobot();
$data["monitors"] = $upTimeRobot->getMonitors();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

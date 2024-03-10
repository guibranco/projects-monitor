<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Application;
use GuiBranco\ProjectsMonitor\Library\Logger;

$application = new Application();

if(!$application->validate()) {
    die();
}

$log = new Logger();
$result = $log->saveLog($application->getApplicationId());
echo json_encode($result);

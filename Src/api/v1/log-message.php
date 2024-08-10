<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Application;
use GuiBranco\ProjectsMonitor\Library\Logger;

$application = new Application();

if (!$application->validate()) {
    die();
}

$log = new Logger();
$result = $log->saveMessage($application->getApplicationId());
if ($result) {
    http_response_code(202);
} else {
    http_response_code(500);
}
echo json_encode($result);

<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Application;
use GuiBranco\ProjectsMonitor\Library\Logger;

$application = new Application();

if (!$application->validate()) {
    die();
}

$messageId = isset($_GET["id"]) ? intval($_GET["id"]) : 0;
$log = new Logger();
$message = $log->getMessage($messageId);

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($message);

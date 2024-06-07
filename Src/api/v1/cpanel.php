<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\CPanel;

$cPanel = new CPanel();
$data["errorLogFiles"] = $cPanel->getErrorLogFiles();
$logMessages = $cPanel->getErrorLogMessages();
$data["errorLogMessages"] = $logMessages;
$data{"totalLogMessages"] = count($logMessages) > 1 ? count($logMessages) - 1 : 0;
$data["cronjobs"] = $cPanel->getCrons();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

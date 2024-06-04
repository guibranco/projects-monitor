<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\CPanel;

$cPanel = new CPanel();
$data["errorLogFiles"] = $cPanel->getErrorLogFiles();
$data["errorLogMessages"] = $cPanel->getErrorLogMessages();
$data["cronjobs"] = $cPanel->getCrons();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

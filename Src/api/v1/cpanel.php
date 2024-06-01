<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\CPanel;

$cPanel = new CPanel();
$data["errors"] = $cPanel->getAllErrors();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

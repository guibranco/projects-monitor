<?php

require_once '../../vendor/autoload.php';

date_default_timezone_set('Europe/Dublin');

use GuiBranco\ProjectsMonitor\Library\HealthChecksIo;

$healthChecksIo = new HealthChecksIo();
$data["checks"] = $healthChecksIo->getChecks();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

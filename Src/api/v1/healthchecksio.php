<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\HealthChecksIo;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/healthchecksio"], "api");
$healthChecksIo = new HealthChecksIo();
$data["checks"] = $healthChecksIo->getChecks();
echo json_encode($data);

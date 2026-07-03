<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Webhooks;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/webhooks-pushes-processing"], "api");
$webhooks = new Webhooks();
$data = $webhooks->getPushesProcessing();
echo json_encode($data);

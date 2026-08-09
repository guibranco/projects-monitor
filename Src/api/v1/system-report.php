<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\SSH;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/system-report"], "api");
$ssh = new SSH();
$data = array();
$data["system_report"] = $ssh->getSystemReport();
echo json_encode($data);

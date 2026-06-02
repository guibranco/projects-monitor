<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Ip2WhoIs;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/domains"], "api");
$ip2WhoIs = new Ip2WhoIs();
$data = array();
$data["domains"] = $ip2WhoIs->getDomainValidity();
echo json_encode($data);

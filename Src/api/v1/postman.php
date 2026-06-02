<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Postman;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/postman"], "api");
$postman = new Postman();
$data = array();
$data["usage"] = $postman->getUsage();
echo json_encode($data);

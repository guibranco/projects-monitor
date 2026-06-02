<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

header('Content-Type: application/json');

use GuiBranco\ProjectsMonitor\Library\ErrorLog;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/errors"], "api");
$errorLog = new ErrorLog();
echo json_encode([
    'errors' => $errorLog->getErrors(),
    'total'  => $errorLog->getTotal(),
]);

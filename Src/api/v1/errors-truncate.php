<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

header('Content-Type: application/json');

use GuiBranco\ProjectsMonitor\Library\ErrorLog;

$errorLog = new ErrorLog();

if ($errorLog->truncate() === false) {
    http_response_code(500);
    echo json_encode(["error" => "An error occurred while truncating the errors table"]);
    exit;
}

echo json_encode([
    'errors' => $errorLog->getErrors(),
    'total'  => $errorLog->getTotal(),
]);

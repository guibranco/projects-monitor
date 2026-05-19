<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

header('Content-Type: application/json');

use GuiBranco\ProjectsMonitor\Library\ErrorLog;

$errorLog = new ErrorLog();
echo json_encode([
    'errors' => $errorLog->getErrors(),
    'total'  => $errorLog->getTotal(),
]);

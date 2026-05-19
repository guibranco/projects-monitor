<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

header('Content-Type: application/json');

use GuiBranco\ProjectsMonitor\Library\ErrorLog;

$requestBody = file_get_contents("php://input");
$input = json_decode($requestBody, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($input['path']) || !is_string($input['path']) || trim($input['path']) === '') {
    http_response_code(400);
    echo json_encode(["error" => "A valid path is required"]);
    exit;
}

$errorLog = new ErrorLog();
$deleted = $errorLog->deleteByPath($input['path']);

echo json_encode([
    'deleted' => $deleted,
    'errors'  => $errorLog->getErrors(),
    'total'   => $errorLog->getTotal(),
]);

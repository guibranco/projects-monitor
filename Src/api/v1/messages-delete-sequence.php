<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Logger;

$requestBody = file_get_contents("php://input");
$input = json_decode($requestBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON provided", "details" => json_last_error_msg()]);
    exit;
}

if (!isset($input['id']) || !is_numeric($input['id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Message ID is required"]);
    exit;
}

$id  = (int) $input['id'];
$log = new Logger();

$result = $log->deleteMessageById($id);

if ($result === false) {
    http_response_code(500);
    echo json_encode(["error" => "An error occurred while deleting the message"]);
    exit;
}

echo json_encode(["success" => true]);

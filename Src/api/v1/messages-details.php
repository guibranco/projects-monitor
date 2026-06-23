<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Logger;

$application = filter_var($_GET['application'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$message     = $_GET['message'] ?? '';
$userAgent   = $_GET['user_agent'] ?? '';

if (empty($application) || empty($message)) {
    http_response_code(400);
    echo json_encode(["error" => "Application and message are required"]);
    exit;
}

$log      = new Logger();
$messages = $log->getMessagesByGroup($application, $message, $userAgent);

echo json_encode(["messages" => $messages]);

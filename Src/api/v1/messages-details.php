<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Logger;

$sampleId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$sampleId || $sampleId < 1) {
    http_response_code(400);
    echo json_encode(["error" => "A valid message ID is required"]);
    exit;
}

$log      = new Logger();
$messages = $log->getMessagesByGroupSampleId((int)$sampleId);

echo json_encode(["messages" => $messages]);

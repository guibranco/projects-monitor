<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

header('Content-Type: application/json');

use GuiBranco\ProjectsMonitor\Library\Webhooks;
use GuiBranco\ProjectsMonitor\Library\LogStream;

$requestBody = file_get_contents("php://input");
$input = json_decode($requestBody, true);

$workflowRunId = isset($input['workflowRunId'])
    ? filter_var($input['workflowRunId'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])
    : false;

if (json_last_error() !== JSON_ERROR_NONE || $workflowRunId === false) {
    http_response_code(400);
    echo json_encode(["error" => "A valid 'workflowRunId' is required"]);
    exit;
}

LogStream::info(
    "API request received",
    ["endpoint" => "POST /api/v1/workflow-runs/retry", "workflow_run_id" => $workflowRunId],
    "api"
);

$webhooks = new Webhooks();
$result = $webhooks->retryWorkflowRun($workflowRunId);

http_response_code($result['statusCode']);
echo json_encode($result['body']);

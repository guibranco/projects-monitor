<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

header('Content-Type: application/json');

use GuiBranco\ProjectsMonitor\Library\Webhooks;
use GuiBranco\ProjectsMonitor\Library\LogStream;

$requestBody = file_get_contents("php://input");
$input = json_decode($requestBody, true);

if (
    json_last_error() !== JSON_ERROR_NONE
    || !isset($input['workflowRunId'])
    || !is_numeric($input['workflowRunId'])
) {
    http_response_code(400);
    echo json_encode(["error" => "A valid 'workflowRunId' is required"]);
    exit;
}

$workflowRunId = (int) $input['workflowRunId'];

LogStream::info(
    "API request received",
    ["endpoint" => "POST /api/v1/workflow-runs/delete", "workflow_run_id" => $workflowRunId],
    "api"
);

$webhooks = new Webhooks();
$result = $webhooks->deleteWorkflowRun($workflowRunId);

http_response_code($result['statusCode']);
echo json_encode($result['body']);

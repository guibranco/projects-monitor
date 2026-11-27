<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\ApplicationsManager;

// POST {"id": 1, "name": "..."}: renames an application (its key is kept).

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    sendErrorResponse("Method not allowed", 405);
}

$input = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    sendErrorResponse("Invalid JSON provided", 400);
}

$id = filter_var($input["id"] ?? 0, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    sendErrorResponse("A valid application ID is required", 400);
}

try {
    $application = (new ApplicationsManager())->rename((int) $id, (string) ($input["name"] ?? ""));
} catch (\InvalidArgumentException $e) {
    sendErrorResponse($e->getMessage(), 400);
}

if ($application === null) {
    sendErrorResponse("Application not found", 404);
}

echo json_encode(["application" => $application]);

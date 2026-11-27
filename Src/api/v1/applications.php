<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\ApplicationsManager;

// GET: every application (tokens masked).
// POST {"name": "..."}: creates an application with a pmk-{acronym}-key key
// and a new token; the response is the only time the full token is returned.

try {
    $manager = new ApplicationsManager();

    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        echo json_encode(["applications" => $manager->list()]);
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        header("Allow: GET, POST");
        sendErrorResponse("Method not allowed", 405);
    }

    $input = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
        sendErrorResponse("Invalid JSON provided", 400);
    }

    $application = $manager->create((string) ($input["name"] ?? ""));
    http_response_code(201);
    echo json_encode(["application" => $application]);
} catch (\InvalidArgumentException $e) {
    sendErrorResponse($e->getMessage(), 400);
}

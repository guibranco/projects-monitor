<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\ApplicationsManager;

// POST {"id": 1, "target": "token"}: gives the application a new token; the
// response is the only time the full token is returned.
// POST {"id": 1, "target": "key"}: sets the key to the pmk-{acronym}-key built
// from the application's current name.
// Either way the old value stops working at once.

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

$target = $input["target"] ?? "";
if ($target !== "key" && $target !== "token") {
    sendErrorResponse("The target must be \"key\" or \"token\"", 400);
}

$manager = new ApplicationsManager();
$application = $target === "key" ? $manager->rotateKey((int) $id) : $manager->rotateToken((int) $id);

if ($application === null) {
    sendErrorResponse("Application not found", 404);
}

echo json_encode(["application" => $application]);

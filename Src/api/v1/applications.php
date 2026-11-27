<?php
/*
 * GET: every application (tokens masked).
 * POST {"name": "..."}: creates an application with a pmk-{acronym}-key key
 * and a new token; the response is the only time the full token is returned.
 */

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\ApplicationKey;
use GuiBranco\ProjectsMonitor\Library\ApplicationsApi;
use GuiBranco\ProjectsMonitor\Library\ApplicationsManager;
use GuiBranco\ProjectsMonitor\Library\Database;

$method = ($_SERVER["REQUEST_METHOD"] ?? "GET");
if ($method !== "GET" && $method !== "POST") {
    header("Allow: GET, POST");
    sendErrorResponse("Method not allowed", 405);
}

$api = new ApplicationsApi(new ApplicationsManager((new Database())->getConnection(), new ApplicationKey()));
$response = $api->list();
if ($method === "POST") {
    $response = $api->create((string) file_get_contents("php://input"));
}

http_response_code($response[0]);
echo json_encode($response[1]);

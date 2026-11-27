<?php
/*
 * POST {"id": 1, "name": "..."}: renames an application (its key is kept).
 */

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\ApplicationKey;
use GuiBranco\ProjectsMonitor\Library\ApplicationsApi;
use GuiBranco\ProjectsMonitor\Library\ApplicationsManager;
use GuiBranco\ProjectsMonitor\Library\Database;

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    header("Allow: POST");
    sendErrorResponse("Method not allowed", 405);
}

$api = new ApplicationsApi(new ApplicationsManager((new Database())->getConnection(), new ApplicationKey()));
$response = $api->update((string) file_get_contents("php://input"));

http_response_code($response[0]);
echo json_encode($response[1]);

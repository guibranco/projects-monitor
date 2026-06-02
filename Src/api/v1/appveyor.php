<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\AppVeyor;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/appveyor"], "api");
$appVeyor = new AppVeyor();
$projects = $appVeyor->getBuilds();

$data["projects"] = $projects;
echo json_encode($data);

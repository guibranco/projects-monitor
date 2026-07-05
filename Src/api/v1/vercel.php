<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Vercel;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/vercel"], "api");
$vercel = new Vercel();
$data["projects"] = $vercel->getProjects();
echo json_encode($data);

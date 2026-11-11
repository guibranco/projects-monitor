<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Vercel;

$vercel = new Vercel();
$data["projects"] = $vercel->getProjects();
echo json_encode($data);

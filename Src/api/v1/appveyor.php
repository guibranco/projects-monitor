<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\AppVeyor;

$appVeyor = new AppVeyor();
$projects = $appVeyor->getBuilds();

$data["projects"] = $projects;
echo json_encode($data);

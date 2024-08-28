<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\AppVeyor;

$appVeyor = new AppVeyor();
$projects = $appVeyor->getBuilds();

$data["projects"] = $projects;

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

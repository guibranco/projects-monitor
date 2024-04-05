<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\GitHub;

$github = new GitHub();
$data["issues"] = $github->getIssues();
$data["pull_requests"] = $github->getPullRequests();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

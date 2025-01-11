<?php

require_once 'validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\GitHub;

$github = new GitHub();
$data["api_usage"] =  $github->getApiUsage();
$data["accounts_usage"] = $github->getAccountUsage();
$data["issues"] = $github->getIssues();
$data["pull_requests"] = $github->getPullRequests();
$data["latest_release"] = $github->getLatestReleaseOfBancosBrasileiros();
echo json_encode($data);

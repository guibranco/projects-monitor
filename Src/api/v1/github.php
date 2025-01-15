<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\GitHub;

$github = new GitHub();
$apiUsage = $github->getApiUsage();;
$data["api_usage"] = $apiUsage["data"];
$data["api_usage_core"] = $apiUsage["core"];
$data["accounts_usage"] = $github->getAccountUsage();
$data["issues"] = $github->getIssues();
$data["pull_requests"] = $github->getPullRequests();
$data["latest_release"] = $github->getLatestReleaseOfBancosBrasileiros();
echo json_encode($data);

<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\GitHub;

$github = new GitHub();
$data["api_usage"] =  $github->getApiUsage();
$usage = $github->getAccountUsage();
$data["accounts_usage"] = $usage["data"];
$data["account_usage_core"] = $usage["core"];
$data["issues"] = $github->getIssues();
$data["pull_requests"] = $github->getPullRequests();
$data["latest_release"] = $github->getLatestReleaseOfBancosBrasileiros();
echo json_encode($data);

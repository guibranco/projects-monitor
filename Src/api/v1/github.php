<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\GitHub;
use GuiBranco\ProjectsMonitor\Library\GitHubActionsUsage;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/github"], "api");
$github = new GitHub();
$apiUsage = $github->getApiUsage();
;
$data["api_usage"] = $apiUsage["data"];
$data["api_usage_core"] = $apiUsage["core"];

try {
    $data["accounts_usage"] = (new GitHubActionsUsage())->getAccountsUsageTable();
} catch (\Throwable $e) {
    LogStream::error("GitHub Actions billing config failed to load", ["reason" => $e->getMessage()], "github-billing");
    $data["accounts_usage"] = array();
}

$data["issues"] = $github->getIssues();
$data["pull_requests"] = $github->getPullRequests();
$data["latest_release"] = $github->getLatestReleaseOfBancosBrasileiros();
echo json_encode($data);

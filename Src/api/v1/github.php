<?php


require_once '../../Library/GitHub.php';
$github = new GitHub();

$owner = $_GET['owner'];
$repo = $_GET['repo'];
$templatesExist = $github->checkIssueTemplates($owner, $repo);

echo json_encode(['templatesExist' => $templatesExist]);

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\GitHub;

$github = new GitHub();
$data["issues"] = $github->getIssues();
$data["pull_requests"] = $github->getPullRequests();
$data["latest_release"] = $github->getLatestReleaseOfBancosBrasileiros();
$data["accounts_usage"] = $github->getAccountUsage();
$data["api_usage"] =  $github->getApiUsage();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

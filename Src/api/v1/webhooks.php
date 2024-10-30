<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Webhooks;
use GuiBranco\ProjectsMonitor\Library\GitHub;

$webhooks = new Webhooks();
$github = new GitHub();
$repoName = 'example-repo'; // This should be dynamically set
$language = 'JavaScript'; // This should be dynamically set
$data["linter_files"] = $github->checkLinterFiles($repoName, $language);
$data = $webhooks->getDashboard();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

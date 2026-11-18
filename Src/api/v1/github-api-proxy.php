<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\GitHubApiProxy;

$proxy = new GitHubApiProxy();
$data  = $proxy->getDashboard();
echo json_encode($data);

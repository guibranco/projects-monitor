<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\IdlePullRequestsFeed;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/webhooks-pull-requests-idle"], "api");
$feed = new IdlePullRequestsFeed();
$data = $feed->fetch();
echo json_encode($data);

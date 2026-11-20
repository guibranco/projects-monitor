<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\IdlePullRequestsFeed;

$feed = new IdlePullRequestsFeed();
$data = $feed->fetch(IdlePullRequestsFeed::normalizeFilter($_GET["filter"] ?? null));
echo json_encode($data);

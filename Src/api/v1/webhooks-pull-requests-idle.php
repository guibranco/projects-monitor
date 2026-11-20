<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\IdlePullRequestsFeed;

$filter = isset($_GET["filter"]) && in_array($_GET["filter"], IdlePullRequestsFeed::FILTERS, true)
    ? $_GET["filter"]
    : IdlePullRequestsFeed::FILTER_MINE;

$feed = new IdlePullRequestsFeed();
$data = $feed->fetch($filter);
echo json_encode($data);

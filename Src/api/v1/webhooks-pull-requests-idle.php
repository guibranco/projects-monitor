<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\IdlePullRequestsFeed;

$filter = IdlePullRequestsFeed::FILTER_MINE;
if (isset($_GET["filter"]) === true && in_array($_GET["filter"], IdlePullRequestsFeed::FILTERS, true) === true) {
    $filter = $_GET["filter"];
}

$feed = new IdlePullRequestsFeed();
$data = $feed->fetch($filter);
echo json_encode($data);

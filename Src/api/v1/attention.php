<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Attention\AttentionFeed;

$refresh = isset($_GET["refresh"]) && $_GET["refresh"] === "1";

$feed = new AttentionFeed();
echo json_encode($feed->getResponse($refresh));

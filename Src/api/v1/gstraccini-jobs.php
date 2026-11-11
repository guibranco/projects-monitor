<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\GStracciniBot;

$bot = new GStracciniBot();
$data = $bot->getJobs();
echo json_encode($data);

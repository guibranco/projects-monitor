<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\GStracciniBot;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/gstraccini-jobs"], "api");
$bot = new GStracciniBot();
$data = $bot->getJobs();
echo json_encode($data);

<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\WebhooksHandler;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/throttled-repositories"], "api");
$handler = new WebhooksHandler();
$data = $handler->getThrottledRepositories();
echo json_encode($data);

<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\WebhooksHandler;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/broker-usage"], "api");
$handler = new WebhooksHandler();
$data = $handler->getBrokerUsage();
echo json_encode($data);

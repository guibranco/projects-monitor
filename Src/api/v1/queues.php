<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\RabbitMq;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/queues"], "api");
$rabbitMq = new RabbitMq();
$data = $rabbitMq->getQueueLength();
echo json_encode($data);

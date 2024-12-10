<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\RabbitMq;

$rabbitMq = new RabbitMq();
$data = $rabbitMq->getQueueLength();
echo json_encode($data);

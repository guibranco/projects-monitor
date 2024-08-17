<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\RabbitMq;

$rabbitMq = new RabbitMq();
$data = $rabbitMq->getQueueLength();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

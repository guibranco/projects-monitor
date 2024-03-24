<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Queue;

$queue = new Queue();
$data = $queue->getQueueLength();
header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

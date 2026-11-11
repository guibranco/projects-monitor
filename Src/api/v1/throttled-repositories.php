<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\WebhooksHandler;

$handler = new WebhooksHandler();
$data = $handler->getThrottledRepositories();
echo json_encode($data);

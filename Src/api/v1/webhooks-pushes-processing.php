<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Webhooks;

$webhooks = new Webhooks();
$data = $webhooks->getPushesProcessing();
echo json_encode($data);

<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\CloudflareWorkers;
use GuiBranco\ProjectsMonitor\Library\ErrorReporter;

$cloudflareWorkers = new CloudflareWorkers();
$data["workers"] = $cloudflareWorkers->getRowsOrError(new ErrorReporter());
echo json_encode($data);

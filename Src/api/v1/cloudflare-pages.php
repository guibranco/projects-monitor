<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\CloudflarePages;
use GuiBranco\ProjectsMonitor\Library\ErrorReporter;

$cloudflarePages = new CloudflarePages();
$data["projects"] = $cloudflarePages->getProjectsOrError(new ErrorReporter());
echo json_encode($data);

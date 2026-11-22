<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\CloudflarePages;

$cloudflarePages = new CloudflarePages();
$data["projects"] = $cloudflarePages->getProjects();
echo json_encode($data);

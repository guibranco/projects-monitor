<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Cloudflare;

$cloudflare = new Cloudflare();
$data["zones"] = $cloudflare->getZones();
echo json_encode($data);

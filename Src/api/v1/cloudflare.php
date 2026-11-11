<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Cloudflare;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/cloudflare"], "api");
$cloudflare = new Cloudflare();
$data["zones"] = $cloudflare->getZones();
echo json_encode($data);

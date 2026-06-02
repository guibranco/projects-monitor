<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\SSH;
use GuiBranco\ProjectsMonitor\Library\LogStream;

LogStream::info("API request received", ["endpoint" => "GET /api/v1/wireguard"], "api");
$ssh = new SSH();
$data = array();
$data["wireguard"] = $ssh->getWireGuardConnections();
echo json_encode($data);

<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\SSH;

$ssh = new SSH();
$data = array();
$data["wireguard"] = $ssh->getWireGuardConnections();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

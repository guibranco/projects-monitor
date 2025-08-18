<?php

require_once 'validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\SSH;

$ssh = new SSH();
$data = array();
$data["wireguard"] = $ssh->getWireGuardConnections();
echo json_encode($data);

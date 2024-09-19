<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\SSH;

$ssh = new SSH();
$data = $ssh->getWireGuardConnections();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

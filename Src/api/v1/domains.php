<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Ip2WhoIs;

$ip2WhoIs = new Ip2WhoIs();
$data = array();
$data["domains"] = $ip2WhoIs->getDomainValidity();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

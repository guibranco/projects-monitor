<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Ip2WhoIs;

$ip2WhoIs = new Ip2WhoIs();
$data = array();
$data["domains"] = $ip2WhoIs->getDomainValidity();
echo json_encode($data);

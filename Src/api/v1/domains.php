<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Ip2WhoIs;

$ip2WhoIs = new Ip2WhoIs();
$data = array();
$data["domains"] = $ip2WhoIs->getDomainValidity();
$sec = 60 * 60 * 24;
header("Content-Type: application/json; charset=UTF-8");
header('Cache-Control: must-revalidate, max-age=' . (int) $sec);
header('Pragma: cache');
header('Expires: ' . str_replace('+0000', 'GMT', gmdate('r', time() + $sec)));
echo json_encode($data);

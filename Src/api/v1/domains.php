<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Ip2WhoIs;

$file = "domains.json";

if(!file_exists($file)){
    $ip2WhoIs = new Ip2WhoIs();
    $data = array();
    $data["domains"] = $ip2WhoIs->getDomainValidity();
    file_put_contents($file, json_encode($data));
}

header("Content-Type: application/json; charset=UTF-8");
echo file_get_contents($file);

if(filemtime("domains.json") < strtotime("-1 day")){
    unlink($file);
}


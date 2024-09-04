<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Postman;

$postman = new Postman();
$data = array();
$data["usage"] = $postman->getUsage();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

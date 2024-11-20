<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Postman;

$postman = new Postman();
$data = array();
$data["usage"] = $postman->getUsage();
echo json_encode($data);

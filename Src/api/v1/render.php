<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Render;

$render = new Render();
$data["services"] = $render->getServices();
echo json_encode($data);

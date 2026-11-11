<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\CPanel;

$cPanel = new CPanel();
$data = array();
try {
    $data["records"] = $cPanel->getAllDnsRecords();
} catch (Exception $e) {
    $data["records"] = [];
    $data["error"] = "Failed to retrieve DNS records: " . $e->getMessage();
}
echo json_encode($data);

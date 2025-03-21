<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\CPanel;

$cPanel = new CPanel();
$logMessages = $cPanel->getErrorLogMessages();
$data["error_log_files"] = $cPanel->getErrorLogFiles();
$data["error_log_messages"] = $logMessages;
$data["total_error_messages"] = count($logMessages) > 1 ? count($logMessages) - 1 : 0;
$data["cronjobs"] = $cPanel->getCrons();
$data["emails"] = $cPanel->getInboxMessagesCount();
try {
    $data["usage"] = $cPanel->getUsageData();
} catch (Exception $e) {
    $data["usage"] = null;
    $data["errors"][] = "Failed to retrieve usage data: " . $e->getMessage();
}
echo json_encode($data);

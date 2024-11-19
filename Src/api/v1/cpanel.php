<?php

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\CPanel;

if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    exit;
}

$cPanel = new CPanel();
$logMessages = $cPanel->getErrorLogMessages();
$data["error_log_files"] = $cPanel->getErrorLogFiles();
$data["error_log_messages"] = $logMessages;
$data["total_error_messages"] = count($logMessages) > 1 ? count($logMessages) - 1 : 0;
$data["cronjobs"] = $cPanel->getCrons();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

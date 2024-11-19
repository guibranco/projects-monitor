<?php

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Postman;

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

$postman = new Postman();
$data = array();
$data["usage"] = $postman->getUsage();

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($data);

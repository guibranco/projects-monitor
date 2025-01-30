<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Logger;

if (headers_sent($file, $line)) {
    error_log("Headers already sent in $file:$line");
    exit(1);
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header("Content-Type: application/json; charset=UTF-8");

function sendErrorResponse($message, $code = 401)
{
    http_response_code($code);
    echo json_encode(["error" => $message]);
    exit();
}

define('SESSION_TIMEOUT', 1800);
define('MAX_REQUESTS_PER_MINUTE', 60);

function validateIP()
{
    if (!isset($_SESSION['ip'])) {
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
    }
    if ($_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
        $logger = new Logger();
        $logger->logMessage("Potential session hijacking attempt: ".$_SERVER['REMOTE_ADDR']." in ".$_SERVER["SCRIPT_NAME"]);
        session_destroy();
        sendErrorResponse("Session invalid", 401);
    }
}

validateIP();

if (isset($_SERVER['HTTP_HOST']) && $_SERVER["HTTP_HOST"] === "localhost:8000") {
    $_SESSION['user_id'] = 1;
    $_SESSION['last_activity'] = time();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    sendErrorResponse("Unauthorized access: User is not logged in.");
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    $logger = new Logger();
    $logger->logMessage("Session timeout for user: " . $_SESSION['user_id']." in ".$_SERVER["SCRIPT_NAME"]);
    session_unset();
    session_destroy();
    sendErrorResponse("Session expired. Please log in again.", 401);
}

if (!isset($_SESSION['request_count'])) {
    $_SESSION['request_count'] = 1;
    $_SESSION['request_time'] = time();
} else {
    if (time() - $_SESSION['request_time'] <= 60) {
        if ($_SESSION['request_count'] > MAX_REQUESTS_PER_MINUTE) {
            $logger = new Logger();
            $logger->logMessage("Rate limit exceeded for user: " . $_SESSION['user_id']);
            sendErrorResponse("Too many requests", 429);
        }
        $_SESSION['request_count']++;
    } else {
        $_SESSION['request_count'] = 1;
        $_SESSION['request_time'] = time();
    }
}

$_SESSION['last_activity'] = time();
$userId = $_SESSION['user_id'];

session_write_close();

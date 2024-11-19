<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

if (headers_sent($file, $line)) {
    error_log("Headers already sent in $file:$line");
    exit(1);
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header("Content-Type: application/json");

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
        error_log("Potential session hijacking attempt: " . $_SERVER['REMOTE_ADDR']);
        session_destroy();
        sendErrorResponse("Session invalid", 401);
    }
}

validateIP();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    error_log("Unauthorized access attempt: " . $_SERVER['REMOTE_ADDR']);
    sendErrorResponse("Unauthorized access: User is not logged in.");
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    error_log("Session timeout for user: " . $_SESSION['user_id']);
    session_unset();
    session_destroy();
    sendErrorResponse("Session expired. Please log in again.", 401);
}

// Rate limiting
if (!isset($_SESSION['request_count'])) {
    $_SESSION['request_count'] = 1;
    $_SESSION['request_time'] = time();
} else {
    if (time() - $_SESSION['request_time'] <= 60) {
        if ($_SESSION['request_count'] > MAX_REQUESTS_PER_MINUTE) {
            error_log("Rate limit exceeded for user: " . $_SESSION['user_id']);
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

if (isset($_SESSION['user_data'])) {
    $userData = $_SESSION['user_data'];
} else {
    $userData = null;
}

<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

header("Content-Type: application/json");

function sendErrorResponse($message, $code = 401)
{
    http_response_code($code);
    echo json_encode(["error" => $message]);
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    sendErrorResponse("Unauthorized access: User is not logged in.");
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    sendErrorResponse("Session expired. Please log in again.", 401);
}

$_SESSION['last_activity'] = time();

$userId = $_SESSION['user_id'];

if (isset($_SESSION['user_data'])) {
    $userData = $_SESSION['user_data'];
} else {
    $userData = null;
}

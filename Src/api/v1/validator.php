<?php

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header("Content-Type: application/json; charset=UTF-8");

require 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function sendErrorResponse($message, $code = 401)
{
    http_response_code($code);
    echo json_encode(["error" => $message]);
    exit();
}

const JWT_SECRET = 'your-256-bit-secret';
const JWT_ALGO = 'HS256';

function validateJWT($token)
{
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGO));
        if ($decoded->exp < time()) {
            throw new Exception("Token has expired");
        }
        return $decoded;
    } catch (Exception $e) {
        error_log("JWT validation error: " . $e->getMessage());
        sendErrorResponse("Invalid or expired token", 401);
    }
}

$authToken = $_COOKIE['auth_token'] ?? null;
if (!$authToken) {
    sendErrorResponse("Missing authentication token", 401);
}

$userData = validateJWT($authToken);
$userId = $userData->data->id ?? null;
if (!$userId) {
    sendErrorResponse("Unauthorized access: User ID not found in token", 401);
}

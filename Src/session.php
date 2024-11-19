<?php

if (headers_sent($file, $line)) {
    error_log("Headers already sent in $file:$line");
    exit(1);
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    $expires = 60;
    $cookie_lifetime = 604800;
    session_set_cookie_params([
        'lifetime' => $cookie_lifetime,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

<?php

session_start();
$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();
if (!headers_sent()) {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    header('Location: /projects-monitor/login.php?token=' . urlencode($token));
    exit;
} else {
    echo '<script>window.location.href = "/projects-monitor/login.php";</script>';
    echo 'If you are not redirected, please <a href="/projects-monitor/login.php">click here</a>.';
    exit;
}

<?php

<?php

session_start();
// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();
// Ensure no output has been sent
if (!headers_sent()) {
    // Use absolute path and add CSRF token
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    header('Location: /login.php?token=' . urlencode($token));
    exit;
} else {
    // Fallback for when headers are already sent
    echo '<script>window.location.href = "/login.php";</script>';
    echo 'If you are not redirected, please <a href="/login.php">click here</a>.';
    exit;
}

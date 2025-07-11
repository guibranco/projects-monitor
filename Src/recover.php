<?php
require_once 'session.php';
require_once 'vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\Database;
use GuiBranco\ProjectsMonitor\Library\Logger;

header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

$configuration = new Configuration();
$configuration->init();
$database = new Database();
$conn = $database->getConnection();

$message = '';
$ip_address = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
    : $_SERVER['REMOTE_ADDR'];

if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
    http_response_code(400);
    $message = 'Invalid IP address';
}

$attempt_count = 0;
try {
    $stmt = $conn->prepare('SELECT COUNT(1) FROM password_recovery_attempts WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    $stmt->bind_param('s', $ip_address);
    if (!$stmt->execute()) {
        throw new Exception("Query failed: " . $stmt->error);
    }
    $stmt->bind_result($attempt_count);
    $stmt->fetch();
    $stmt->close();
} catch (Exception $e) {
    $logger = new Logger();
    $logger->logMessage("Rate limiting check failed: " . $e->getMessage());
    http_response_code(500);
    $message = 'Internal server error';
}
$max_attempts = 3;
if ($attempt_count >= $max_attempts) {
    http_response_code(429);
    header('Content-Type: text/plain; charset=utf-8');
    $conn->close();
    $message = 'Too many password recovery attempts. Please try again later.';
}

if (empty($message) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        $conn->close();
        die('Invalid request');
    }
    $identifier = filter_input(INPUT_POST, 'identifier', FILTER_SANITIZE_EMAIL);
    if (!$identifier) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        $conn->close();
        die('Invalid input provided');
    }
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $reset_token = bin2hex(random_bytes(16));
        $reset_token_expiration = gmdate('Y-m-d H:i:s', strtotime('+1 hour UTC'));

        $conn->begin_transaction();
        try {
            $cleanup_stmt = $conn->prepare("UPDATE users SET reset_token = NULL, reset_token_expiration = NULL WHERE reset_token_expiration < NOW()");
            $cleanup_stmt->execute();

            $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiration = ? WHERE id = ?");
            $update_stmt->bind_param('ssi', $reset_token, $reset_token_expiration, $user['id']);
            $update_stmt->execute();
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

        $reset_link = sprintf('%s://%s/projects-monitor/reset.php?token=%s', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http', $_SERVER['HTTP_HOST'], urlencode($reset_token));
        $to = $user['email'];
        $subject = '=?UTF-8?B?' . base64_encode('Password Reset Request') . '?=';
        $message = wordwrap("Click the link below to reset your password:\n\n{$reset_link}\n\nThis link is valid for 1 hour.\n\nIf you didn't request this reset, please ignore this email.", 70);
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8; format=flowed',
            'Content-Transfer-Encoding: 8bit',
            'From: ' . sprintf('=?UTF-8?B?%s?= <noreply@%s>', base64_encode("Projects Monitor"), $_SERVER['HTTP_HOST'])
        ];
        if (mail($to, $subject, $message, implode("\r\n", $headers))) {
            $message = 'A password reset link has been sent to your email.';
        } else {
            $message = 'Failed to send the email. Try again later.';
        }
    } else {
        $errorMessage = sprintf(
            'Failed password recovery attempt for identifier: %s, IP: %s',
            preg_replace('/[^\w\-\.\@]/', '', $identifier),
            filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)
        );
        $logger = new Logger();
        $logger->logMessage($errorMessage);
        $message = 'No account found with that username or email.';
        try {
            $conn->begin_transaction();

            $cleanup = $conn->prepare('DELETE FROM password_recovery_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
            $cleanup->execute();
            $cleanup->close();

            $stmt = $conn->prepare('INSERT INTO password_recovery_attempts (ip_address, created_at) VALUES (?, NOW())');
            $stmt->bind_param('s', $ip_address);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $logger = new Logger();
            $logger->logMessage("Failed to log recovery attempt: " . $e->getMessage());
            $message = 'An error occurred. Please try again later.';
        }
    }
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Projects Monitor | Password Recovery</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="static/styles-public.css?<?php echo filemtime("static/styles-public.css"); ?>">
</head>

<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title text-center">Recover Password</h3>
                        <?php if ($message): ?>
                            <div class="alert alert-info"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="mb-3">
                                <label for="identifier" class="form-label">Username or Email</label>
                                <input type="text" class="form-control" id="identifier" name="identifier"
                                    pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$|^[a-zA-Z0-9_]{3,}$"
                                    required maxlength="255" autocomplete="username">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Send Recovery Email</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="login.php" class="btn btn-secondary w-100">Return to Login Page</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>

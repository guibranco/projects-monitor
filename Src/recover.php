<?php
require_once 'session.php';
require_once 'vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\Database;

header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

$configuration = new Configuration();
$configuration->init();
$database = new Database();
$conn = $database->getConnection();

$message = '';
$ip_address = $_SERVER['REMOTE_ADDR'];
$stmt = $conn->prepare('SELECT COUNT(*) FROM password_recovery_attempts WHERE ip_address = ? AND timestamp > (NOW() - INTERVAL 1 HOUR)');
$stmt->bind_param('s', $ip_address);
$stmt->execute();
$stmt->bind_result($attempt_count);
$stmt->fetch();
$stmt->close();
$max_attempts = 3;
if ($attempt_count >= $max_attempts) {
    die('Too many password recovery attempts. Please try again later.');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
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
        $subject = '=?UTF-8?B?'.base64_encode('Password Reset Request').'?=';
        $message = wordwrap("Click the link below to reset your password:\n\n{$reset_link}\n\nThis link is valid for 1 hour.\n\nIf you didn't request this reset, please ignore this email.", 70);
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8; format=flowed',
            'Content-Transfer-Encoding: 8bit',
            'From: '.sprintf('=?UTF-8?B?%s?= <noreply@%s>', base64_encode("Projects Monitor"), $_SERVER['HTTP_HOST'])
        ];
        if (mail($to, $subject, $message, implode("\r\n", $headers))) {
            $message = 'A password reset link has been sent to your email.';
        } else {
            $message = 'Failed to send the email. Try again later.';
        }
    } else {
        error_log(sprintf(
            'Failed password recovery attempt for identifier: %s, IP: %s',
            preg_replace('/[^\w\-\.\@]/', '', $identifier),
            filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)
        ));
        $message = 'No account found with that username or email.';
    $stmt = $conn->prepare('INSERT INTO password_recovery_attempts (ip_address, timestamp) VALUES (?, NOW())');
    $stmt->bind_param('s', $ip_address);
    $stmt->execute();
    $stmt->close();
    }
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Recovery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title text-center">Recover Password</h3>
                        <?php if ($message): ?>
                            <div class="alert alert-info"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="mb-3">
                                <label for="identifier" class="form-label">Username or Email</label>
                                <input type="text" class="form-control" id="identifier" name="identifier" pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$|^[a-zA-Z0-9_]{3,}$" required maxlength="255" autocomplete="username">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Send Recovery Email</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

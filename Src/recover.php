<?php
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\Database;

$configuration = new Configuration();
$configuration->init();
$database = new Database();
$conn = $database->getConnection();

$message = '';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid request');
    }
    $identifier = filter_input(INPUT_POST, 'identifier', FILTER_SANITIZE_EMAIL);
    if (!$identifier) {
        $message = 'Invalid input provided';
        exit;
    }
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $reset_token = bin2hex(random_bytes(16));
        $reset_token_expiration = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Update reset token and expiration in the database
        $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiration = ? WHERE id = ?");
        $update_stmt->bind_param('ssi', $reset_token, $reset_token_expiration, $user['id']);
        $update_stmt->execute();

        // Send reset email
        $reset_link = "http://yourdomain.com/reset.php?token=$reset_token";
        $to = $user['email'];
        $subject = "Password Reset Request";
        $message = "Click the link below to reset your password:\n\n$reset_link\n\nThis link is valid for 1 hour.";
        $headers = "From: noreply@yourdomain.com";

        if (mail($to, $subject, $message, $headers)) {
            $message = 'A password reset link has been sent to your email.';
        } else {
            $message = 'Failed to send the email. Try again later.';
        }
    } else {
        $message = 'No account found with that username or email.';
    }
}
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
                            <div class="alert alert-info"><?php echo $message; ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="identifier" class="form-label">Username or Email</label>
                                <input type="text" class="form-control" id="identifier" name="identifier" required>
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

<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once 'vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\Database;

$configuration = new Configuration();
$configuration->init();
$database = new Database();
$conn = $database->getConnection();

$error = '';

function login() {
    global $error;
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid request';
        return;
    }

    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);

    if (empty($username) || empty($password)) {
        $error = 'All fields are required';
        return;
    }

    $attempts = isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] : 0;
    $lastAttempt = isset($_SESSION['last_attempt']) ? $_SESSION['last_attempt'] : 0;

    if ($attempts >= 3 && time() - $lastAttempt < 900) {
        $error = 'Too many failed attempts. Please try again later.';
        return;
    }

    $stmt = $conn->prepare('SELECT id, password FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $user = $result->fetch_assoc();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['last_activity'] = time();
        unset($_SESSION['login_attempts']);
        unset($_SESSION['last_attempt']);
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
        $_SESSION['login_attempts'] = $attempts + 1;
        $_SESSION['last_attempt'] = time();
    }

    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    login();
}
$_SESSION['csrf_token'] = uniqid();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Projects Monitor</title>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title text-center">Login</h3>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required + aria-required="true" autocomplete="username">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required + aria-required="true" autocomplete="current-password">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                        <div class="mt-3 text-center">
                            <a href="recover.php" class="text-decoration-none">Forgot your password?</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

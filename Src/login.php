<?php
require_once 'session.php';
require_once 'vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\Database;
use GuiBranco\ProjectsMonitor\Library\Logger;

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Location: index.php');
    exit;
}

$configuration = new Configuration();
$configuration->init();
$database = new Database();
$conn = $database->getConnection();

/**
 * @param string $value
 * @param array $flags
 * @return string
 */
function sanitizeFilterString(string $value, array $flags): string
{
    $noQuotes = in_array(FILTER_FLAG_NO_ENCODE_QUOTES, $flags);
    $options = ($noQuotes ? ENT_NOQUOTES : ENT_QUOTES) | ENT_SUBSTITUTE;
    $optionsDecode = ($noQuotes ? ENT_QUOTES : ENT_NOQUOTES) | ENT_SUBSTITUTE;
    $value = strip_tags($value);
    $value = htmlspecialchars($value, $options);
    $value = str_replace(["&quot;", "&#039;"], ["&#34;", "&#39;"], $value);
    return html_entity_decode($value, $optionsDecode);
}

function login(mysqli $conn): string
{
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        return 'Invalid request';
    }

    $username = sanitizeFilterString($_POST['username'], []);
    $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);

    if (empty($username) || empty($password)) {
        return 'All fields are required';
    }

    $attempts    = $_SESSION['login_attempts'] ?? 0;
    $lastAttempt = $_SESSION['last_attempt']   ?? 0;

    if ($attempts >= 3 && time() - $lastAttempt < 900) {
        return 'Too many failed attempts. Please try again later.';
    }

    $stmt = $conn->prepare('SELECT id, username, email, password FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['username']      = $user['username'];
        $_SESSION['email']         = $user['email'];
        $_SESSION['last_activity'] = time();
        unset($_SESSION['login_attempts'], $_SESSION['last_attempt']);
        header('Location: index.php');
        exit;
    }

    $_SESSION['login_attempts'] = $attempts + 1;
    $_SESSION['last_attempt']   = time();
    return 'Invalid username or password.';
}

function recover(mysqli $conn): string
{
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        return 'Invalid request';
    }

    $ip_address = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
        : $_SERVER['REMOTE_ADDR'];

    if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
        return 'Invalid IP address';
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
        return 'Internal server error';
    }

    $max_attempts = 3;
    if ($attempt_count >= $max_attempts) {
        return 'Too many password recovery attempts. Please try again later.';
    }

    $identifier = filter_input(INPUT_POST, 'identifier', FILTER_SANITIZE_EMAIL);
    if (!$identifier) {
        return 'Invalid input provided';
    }

    $stmt = $conn->prepare("SELECT id, email FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    // Always return this same message whether or not the identifier matches an
    // account, so the response can't be used to enumerate valid usernames/emails.
    $genericMessage = 'If an account matches that username or email, a password reset link has been sent.';

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
        $body = wordwrap("Click the link below to reset your password:\n\n{$reset_link}\n\nThis link is valid for 1 hour.\n\nIf you didn't request this reset, please ignore this email.", 70);
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8; format=flowed',
            'Content-Transfer-Encoding: 8bit',
            'From: ' . sprintf('=?UTF-8?B?%s?= <noreply@%s>', base64_encode("Projects Monitor"), $_SERVER['HTTP_HOST'])
        ];
        if (!mail($to, $subject, $body, implode("\r\n", $headers))) {
            $logger = new Logger();
            $logger->logMessage("Failed to send password reset email to user id {$user['id']}");
        }
        return $genericMessage;
    }

    $errorMessage = sprintf(
        'Failed password recovery attempt for identifier: %s, IP: %s',
        preg_replace('/[^\w\-\.\@]/', '', $identifier),
        filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)
    );
    $logger = new Logger();
    $logger->logMessage($errorMessage);

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
        return 'An error occurred. Please try again later.';
    }

    return $genericMessage;
}

$error = '';
$recoverMessage = '';
$openRecoverModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['form_action'] ?? 'login') === 'recover') {
        $recoverMessage = recover($conn);
        $openRecoverModal = true;
    } else {
        $error = login($conn);
    }
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <title>Projects Monitor | Login</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha384-5e2ESR8Ycmos6g3gAKr1Jvwye8sW4U1u/cAKulfVJnkakCcMqhOudbtPnvJ+nbv7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="static/styles-public.css?<?php echo filemtime("static/styles-public.css"); ?>">
</head>

<body>

    <!-- Sticky top navbar with sign-in trigger -->
    <nav class="navbar navbar-dark sticky-top">
        <div class="container-fluid">
            <span class="navbar-brand d-flex align-items-center gap-2">
                <i class="bi bi-activity fs-5"></i>
                <span class="fw-bold">Projects Monitor</span>
            </span>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sign-in btn-sm" data-bs-toggle="modal" data-bs-target="#loginModal">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                </button>
            </div>
        </div>
    </nav>

    <!-- Login modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header py-2 px-3 border-bottom-0">
                    <h6 class="modal-title mb-0" id="loginModalLabel">
                        <i class="bi bi-box-arrow-in-right me-2" style="color:#ff6b35"></i>Sign In
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-1 px-3 pb-3">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-sm py-2 px-3 mb-3 small">
                            <i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <input type="hidden" name="form_action" value="login">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="mb-2">
                            <label for="username" class="form-label small mb-1 text-muted">
                                <i class="fas fa-user me-1"></i>Username
                            </label>
                            <input type="text" class="form-control form-control-sm" id="username" name="username"
                                   required aria-required="true" autocomplete="username">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label small mb-1 text-muted">
                                <i class="fas fa-lock me-1"></i>Password
                            </label>
                            <input type="password" class="form-control form-control-sm" id="password" name="password"
                                   required aria-required="true" autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-sign-in-alt me-1"></i>Sign In
                        </button>
                    </form>
                    <div class="text-center mt-2">
                        <a href="#" id="showRecoverModal" class="text-decoration-none small text-muted">
                            <i class="fas fa-key me-1"></i>Forgot your password?
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Password recovery modal -->
    <div class="modal fade" id="recoverModal" tabindex="-1" aria-labelledby="recoverModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header py-2 px-3 border-bottom-0">
                    <h6 class="modal-title mb-0" id="recoverModalLabel">
                        <i class="bi bi-key me-2" style="color:#ff6b35"></i>Recover Password
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-1 px-3 pb-3">
                    <?php if ($recoverMessage): ?>
                        <div class="alert alert-info alert-sm py-2 px-3 mb-3 small">
                            <i class="fas fa-info-circle me-1"></i><?php echo htmlspecialchars($recoverMessage, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <input type="hidden" name="form_action" value="recover">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="mb-3">
                            <label for="identifier" class="form-label small mb-1 text-muted">
                                <i class="fas fa-user me-1"></i>Username or Email
                            </label>
                            <input type="text" class="form-control form-control-sm" id="identifier" name="identifier"
                                   pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$|^[a-zA-Z0-9_]{3,}$"
                                   required aria-required="true" maxlength="255" autocomplete="username">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-paper-plane me-1"></i>Send Recovery Email
                        </button>
                    </form>
                    <div class="text-center mt-2">
                        <a href="#" id="showLoginModal" class="text-decoration-none small text-muted">
                            <i class="fas fa-arrow-left me-1"></i>Back to Sign In
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Full-width dashboard -->
    <div class="container-fluid py-4">

        <!-- Stats -->
        <div class="row g-3 mb-4" id="stats-row">
            <div class="col-6 col-md">
                <div class="card stats-card fade-in">
                    <div class="card-body text-center">
                        <div class="stats-icon bg-primary-light mx-auto">
                            <i class="fas fa-satellite-dish"></i>
                        </div>
                        <h4 class="fw-bold mb-1" id="stat-total"><span class="placeholder-glow"><span class="placeholder col-4"></span></span></h4>
                        <p class="text-muted mb-0">Monitors</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="card stats-card fade-in">
                    <div class="card-body text-center">
                        <div class="stats-icon bg-success-light mx-auto">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4 class="fw-bold mb-1" id="stat-healthy"><span class="placeholder-glow"><span class="placeholder col-4"></span></span></h4>
                        <p class="text-muted mb-0">Healthy</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="card stats-card fade-in">
                    <div class="card-body text-center">
                        <div class="stats-icon bg-warning-light mx-auto">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h4 class="fw-bold mb-1" id="stat-warning"><span class="placeholder-glow"><span class="placeholder col-4"></span></span></h4>
                        <p class="text-muted mb-0">Warnings</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="card stats-card fade-in">
                    <div class="card-body text-center">
                        <div class="stats-icon bg-danger-light mx-auto">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h4 class="fw-bold mb-1" id="stat-critical"><span class="placeholder-glow"><span class="placeholder col-4"></span></span></h4>
                        <p class="text-muted mb-0">Critical</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="card stats-card fade-in">
                    <div class="card-body text-center">
                        <div class="stats-icon bg-info-light mx-auto">
                            <i class="fas fa-code-branch"></i>
                        </div>
                        <h4 class="fw-bold mb-1" id="stat-branches"><span class="visually-hidden">Loading</span><span class="placeholder-glow" aria-hidden="true"><span class="placeholder col-4"></span></span></h4>
                        <p class="text-muted mb-0">Branches</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="card stats-card fade-in">
                    <div class="card-body text-center">
                        <div class="stats-icon bg-purple-light mx-auto">
                            <i class="fas fa-code-pull-request"></i>
                        </div>
                        <h4 class="fw-bold mb-1" id="stat-pull-requests"><span class="visually-hidden">Loading</span><span class="placeholder-glow" aria-hidden="true"><span class="placeholder col-4"></span></span></h4>
                        <p class="text-muted mb-0">Pull Requests</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="card stats-card fade-in">
                    <div class="card-body text-center">
                        <div class="stats-icon bg-orange-light mx-auto">
                            <i class="fas fa-robot"></i>
                        </div>
                        <h4 class="fw-bold mb-1" id="stat-bot-queue"><span class="visually-hidden">Loading</span><span class="placeholder-glow" aria-hidden="true"><span class="placeholder col-4"></span></span></h4>
                        <p class="text-muted mb-0">Bot Queue</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity + System Status -->
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="card data-card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-clock me-2 text-primary"></i>Recent Monitor Activity
                        </h6>
                        <small class="text-muted" id="last-updated"></small>
                    </div>
                    <div class="card-body p-0" id="activity-list">
                        <div class="activity-empty">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Loading monitor data…
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card data-card h-100">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="fas fa-server me-2 text-primary"></i>System Status
                        </h6>
                    </div>
                    <div class="card-body" id="system-status">
                        <div class="text-center text-muted py-3">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Loading…
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resource Usage + Queue Lengths -->
        <div class="row g-3 mb-4">
            <div class="col-md-5">
                <div class="card data-card h-100">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-line me-2 text-primary"></i>Resource Usage
                        </h6>
                    </div>
                    <div class="card-body" id="performance-metrics">
                        <div class="text-center text-muted py-3">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Loading…
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card data-card h-100">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="fas fa-layer-group me-2 text-primary"></i>Queue Lengths
                        </h6>
                    </div>
                    <div class="card-body p-0" id="queue-lengths">
                        <div class="text-center text-muted py-3">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Loading…
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bot Processing State -->
        <div class="row g-3">
            <div class="col-12">
                <div class="card data-card">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="fas fa-robot me-2 text-primary"></i>Bot Processing State
                        </h6>
                    </div>
                    <div class="card-body p-0" id="bot-processing-state">
                        <div class="text-center text-muted py-3">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Loading…
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php include_once __DIR__ . '/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha256-CDOy6cOibCWEdsRiZuaHf8dSGGJRYuBGC+mjoJimHGw=" crossorigin="anonymous"></script>
    <script>
        const STATUS_CONFIG = {
            healthy:     { badge: 'bg-success',   text: 'Healthy',     bar: 'bg-success' },
            warning:     { badge: 'bg-warning',   text: 'Warning',     bar: 'bg-warning' },
            critical:    { badge: 'bg-danger',    text: 'Critical',    bar: 'bg-danger'  },
            operational: { badge: 'bg-success',   text: 'Operational', bar: 'bg-success' },
            unknown:     { badge: 'bg-secondary', text: 'Unknown',     bar: 'bg-secondary'},
            paused:      { badge: 'bg-secondary', text: 'Paused',      bar: 'bg-secondary'},
        };

        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function formatRelativeTime(isoString) {
            if (!isoString) return '—';
            const diff = Math.floor((Date.now() - new Date(isoString).getTime()) / 1000);
            if (diff < 60)   return `${diff}s ago`;
            if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
            if (diff < 86400)return `${Math.floor(diff / 3600)}h ago`;
            return `${Math.floor(diff / 86400)}d ago`;
        }

        function statusBadge(status) {
            const cfg = STATUS_CONFIG[status] ?? STATUS_CONFIG.unknown;
            return `<span class="badge ${cfg.badge} status-badge">${cfg.text}</span>`;
        }

        function updateStats(monitors) {
            document.getElementById('stat-total').textContent   = monitors.total;
            document.getElementById('stat-healthy').textContent = monitors.healthy;
            document.getElementById('stat-warning').textContent = monitors.warning;
            document.getElementById('stat-critical').textContent= monitors.critical;
        }

        function updateWebhookSummaryStats(counts) {
            document.getElementById('stat-branches').textContent      = counts?.branches     ?? '—';
            document.getElementById('stat-pull-requests').textContent = counts?.pullRequests ?? '—';
        }

        function updateActivity(items) {
            const el = document.getElementById('activity-list');
            if (!items || items.length === 0) {
                el.innerHTML = '<div class="list-group list-group-flush"><div class="activity-empty">No monitor data available</div></div>';
                return;
            }
            const rows = items.map(item => `
                <div class="activity-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="activity-name">${escHtml(item.name)}</span>
                            <span class="activity-time">Last change: ${formatRelativeTime(item.lastChange)}</span>
                        </div>
                        ${statusBadge(item.status)}
                    </div>
                </div>`).join('');
            el.innerHTML = `<div class="list-group list-group-flush">${rows}</div>`;
        }

        function updateSystemStatus(sys) {
            const el = document.getElementById('system-status');
            if (!sys) {
                el.innerHTML = '<p class="text-muted mb-0">Data unavailable</p>';
                return;
            }
            const rows = Object.values(sys).map(item => {
                const cfg = STATUS_CONFIG[item.status] ?? STATUS_CONFIG.unknown;
                const pct = item.percent !== null ? `${item.percent}%` : cfg.text;
                return `
                <div class="status-row">
                    <span class="status-row-label">${escHtml(item.label)}</span>
                    <span class="badge ${cfg.badge} status-badge">${pct}</span>
                </div>`;
            }).join('');
            el.innerHTML = rows;
        }

        function updatePerformance(perf) {
            const el = document.getElementById('performance-metrics');
            if (!perf) {
                el.innerHTML = '<p class="text-muted mb-0">Data unavailable</p>';
                return;
            }

            const labels = { cpu: 'CPU Usage', memory: 'Memory Usage', processes: 'Processes' };
            const units  = { cpu: '%', memory: ' MB', processes: '' };

            const rows = Object.entries(perf).map(([key, item]) => {
                if (item.percent === null && item.value === null) return '';
                const pct   = item.percent ?? 0;
                const cfg   = pct >= 90 ? STATUS_CONFIG.critical : pct >= 75 ? STATUS_CONFIG.warning : STATUS_CONFIG.healthy;
                const value = item.value !== null
                    ? `${item.value}${units[key] ?? ''} / ${item.max}${units[key] ?? ''}`
                    : '—';
                return `
                <div class="perf-row">
                    <div class="perf-row-header">
                        <span class="perf-label">${labels[key] ?? key}</span>
                        <span class="perf-value">${value}</span>
                    </div>
                    <div class="perf-bar-track">
                        <div class="perf-bar-fill ${cfg.bar}" style="width:${Math.min(pct, 100)}%"></div>
                    </div>
                </div>`;
            }).join('');
            el.innerHTML = rows || '<p class="text-muted mb-0">No resource data available</p>';
        }

        function updateLastUpdated(isoString) {
            const el = document.getElementById('last-updated');
            if (el && isoString) {
                el.textContent = `Updated ${formatRelativeTime(isoString)}`;
            }
        }

        function updateQueues(queues) {
            const el = document.getElementById('queue-lengths');
            if (!queues || queues.length === 0) {
                el.innerHTML = '<p class="text-muted mb-0 p-3">No active queues</p>';
                return;
            }
            const rows = queues.map(q => {
                const cls = q.messages > 0 ? 'queue-count has-messages' : 'queue-count';
                return `<tr>
                    <td>${escHtml(q.name)}</td>
                    <td class="text-end"><span class="${cls}">${q.messages}</span></td>
                </tr>`;
            }).join('');
            el.innerHTML = `
                <table class="table public-table">
                    <thead>
                        <tr><th>Queue</th><th class="text-end">Messages</th></tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>`;
        }

        function updateBotProcessingState(stats) {
            const el = document.getElementById('bot-processing-state');
            if (!stats) {
                el.innerHTML = '<p class="text-muted mb-0 p-3">Data unavailable</p>';
                document.getElementById('stat-bot-queue').textContent = '—';
                return;
            }
            const states = ['NEW', 'RE_REQUESTED', 'UPDATED', 'PROCESSING'];
            const labels = ['New', 'Re-Requested', 'Updated', 'Processing'];
            const tables = [
                'github_branches', 'github_comments', 'github_installations',
                'github_issues', 'github_pull_requests', 'github_pushes',
                'github_repositories', 'github_signature', 'github_users'
            ];

            const botQueueTotal = states.reduce((sum, state) => {
                return sum + tables.reduce((s, table) => s + (stats[state]?.[table] ?? 0), 0);
            }, 0);
            document.getElementById('stat-bot-queue').textContent = botQueueTotal;
            const headerCols = labels.map(l => `<th class="text-end">${l}</th>`).join('');
            const rows = tables.map(table => {
                const displayName = table.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                const cells = states.map(s => {
                    const n = stats[s]?.[table] ?? 0;
                    const cls = n > 0 ? 'state-count non-zero' : 'state-count';
                    return `<td class="text-end"><span class="${cls}">${n}</span></td>`;
                }).join('');
                return `<tr><td>${displayName}</td>${cells}</tr>`;
            }).join('');
            el.innerHTML = `
                <table class="table public-table">
                    <thead>
                        <tr><th>Table</th>${headerCols}</tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>`;
        }

        function showError() {
            ['stat-total','stat-healthy','stat-warning','stat-critical','stat-branches','stat-pull-requests','stat-bot-queue'].forEach(id => {
                document.getElementById(id).textContent = '—';
            });
            document.getElementById('activity-list').innerHTML =
                '<div class="list-group list-group-flush"><div class="list-group-item text-center text-danger py-4"><i class="fas fa-exclamation-circle me-2"></i>Could not load monitor data</div></div>';
            document.getElementById('system-status').innerHTML  = '<p class="text-muted mb-0">Data unavailable</p>';
            document.getElementById('performance-metrics').innerHTML = '<p class="text-muted mb-0">Data unavailable</p>';
            document.getElementById('queue-lengths').innerHTML = '<p class="text-muted mb-0 p-3">Data unavailable</p>';
            document.getElementById('bot-processing-state').innerHTML = '<p class="text-muted mb-0 p-3">Data unavailable</p>';
        }

        function loadDashboard() {
            fetch('api/v1/public-stats')
                .then(r => {
                    if (!r.ok) throw new Error(`HTTP ${r.status}`);
                    return r.json();
                })
                .then(data => {
                    updateStats(data.monitors);
                    updateWebhookSummaryStats(data.webhookCounts);
                    updateActivity(data.recentActivity);
                    updateSystemStatus(data.systemStatus);
                    updatePerformance(data.performance);
                    updateLastUpdated(data.generatedAt);
                    updateQueues(data.queues);
                    updateBotProcessingState(data.webhookStats);
                })
                .catch(err => {
                    console.error('Failed to load dashboard data:', err);
                    showError();
                });
        }

        loadDashboard();
        setInterval(loadDashboard, 60000);

        // Switch between the Sign In and Recover Password modals without
        // stacking them (Bootstrap doesn't support two shown modals cleanly).
        function switchModal(fromId, toId) {
            const fromEl = document.getElementById(fromId);
            const toEl = document.getElementById(toId);
            fromEl.addEventListener('hidden.bs.modal', function handler() {
                fromEl.removeEventListener('hidden.bs.modal', handler);
                bootstrap.Modal.getOrCreateInstance(toEl).show();
            });
            bootstrap.Modal.getOrCreateInstance(fromEl).hide();
        }

        document.getElementById('showRecoverModal')?.addEventListener('click', (e) => {
            e.preventDefault();
            switchModal('loginModal', 'recoverModal');
        });

        document.getElementById('showLoginModal')?.addEventListener('click', (e) => {
            e.preventDefault();
            switchModal('recoverModal', 'loginModal');
        });

        <?php if ($error): ?>
        bootstrap.Modal.getOrCreateInstance(document.getElementById('loginModal')).show();
        <?php endif; ?>
        <?php if ($openRecoverModal): ?>
        bootstrap.Modal.getOrCreateInstance(document.getElementById('recoverModal')).show();
        <?php endif; ?>
    </script>
</body>

</html>

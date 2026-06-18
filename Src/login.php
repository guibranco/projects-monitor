<?php
require_once 'session.php';
require_once 'vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\Database;

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

$error = $_SERVER['REQUEST_METHOD'] === 'POST' ? login($conn) : '';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Projects Monitor | Login</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha384-5e2ESR8Ycmos6g3gAKr1Jvwye8sW4U1u/cAKulfVJnkakCcMqhOudbtPnvJ+nbv7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="static/styles-public.css?<?php echo filemtime("static/styles-public.css"); ?>">
</head>

<body class="bg-light">
    <div class="container-fluid">
        <div class="row bg-white shadow-sm mb-4">
            <div class="col-12 py-3">
                <h2 class="mb-0 text-center">
                    <i class="fas fa-project-diagram text-primary me-2"></i>
                    Projects Monitor
                </h2>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card login-card">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Login</h3>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-1"></i>Username
                                </label>
                                <input type="text" class="form-control" id="username" name="username" required
                                    aria-required="true" autocomplete="username">
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required
                                    aria-required="true" autocomplete="current-password">
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </form>

                        <div class="text-center">
                            <a href="recover.php" class="text-decoration-none">
                                <i class="fas fa-key me-1"></i>Forgot your password?
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <!-- Stats Cards -->
                <div class="row mb-4" id="stats-row">
                    <div class="col-md-3 col-6 mb-3">
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
                    <div class="col-md-3 col-6 mb-3">
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
                    <div class="col-md-3 col-6 mb-3">
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
                    <div class="col-md-3 col-6 mb-3">
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
                </div>

                <!-- Recent Activity -->
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Recent Monitor Activity
                        </h5>
                        <small class="text-muted" id="last-updated"></small>
                    </div>
                    <div class="card-body p-0" id="activity-list">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item text-center text-muted py-4">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                Loading monitor data…
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- System Status -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-server me-2"></i>System Status
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

                    <!-- Performance Metrics -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-chart-line me-2"></i>Resource Usage
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
                </div>

                <!-- Queue Lengths -->
                <div class="card data-card mb-4">
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

                <!-- Bot Processing State -->
                <div class="card data-card mb-4">
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

        function updateActivity(items) {
            const el = document.getElementById('activity-list');
            if (!items || items.length === 0) {
                el.innerHTML = '<div class="list-group list-group-flush"><div class="list-group-item text-center text-muted py-4">No monitor data available</div></div>';
                return;
            }
            const rows = items.map(item => `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">${escHtml(item.name)}</h6>
                            <small class="text-muted">Last change: ${formatRelativeTime(item.lastChange)}</small>
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
                const cfg  = STATUS_CONFIG[item.status] ?? STATUS_CONFIG.unknown;
                const pct  = item.percent !== null ? `${item.percent}%` : cfg.text;
                return `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>${escHtml(item.label)}</span>
                    <span class="badge ${cfg.badge}">${pct}</span>
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
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>${labels[key] ?? key}</span>
                        <span>${value}</span>
                    </div>
                    <div class="progress mt-1" style="height:6px;">
                        <div class="progress-bar ${cfg.bar}" style="width:${Math.min(pct, 100)}%"></div>
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
                return;
            }
            const states = ['NEW', 'RE_REQUESTED', 'UPDATED', 'PROCESSING'];
            const labels = ['New', 'Re-Requested', 'Updated', 'Processing'];
            const tables = [
                'github_branches', 'github_comments', 'github_installations',
                'github_issues', 'github_pull_requests', 'github_pushes',
                'github_repositories', 'github_signature', 'github_users'
            ];
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
            ['stat-total','stat-healthy','stat-warning','stat-critical'].forEach(id => {
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
    </script>
</body>

</html>

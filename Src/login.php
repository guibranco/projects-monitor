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

$error = '';

/**
 * @param string $value
 * @param array $flags
 * @return string
 */
function sanitizeFilterString($value, array $flags): string
{
    $noQuotes = in_array(FILTER_FLAG_NO_ENCODE_QUOTES, $flags);
    $options = ($noQuotes ? ENT_NOQUOTES : ENT_QUOTES) | ENT_SUBSTITUTE;
    $optionsDecode = ($noQuotes ? ENT_QUOTES : ENT_NOQUOTES) | ENT_SUBSTITUTE;
    $value = strip_tags($value);
    $value = htmlspecialchars($value, $options);
    $value = str_replace(["&quot;", "&#039;"], ["&#34;", "&#39;"], $value);
    return html_entity_decode($value, $optionsDecode);
}

function login()
{
    global $error, $conn;

    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid request';
        return;
    }

    $username = sanitizeFilterString($_POST['username'], []);
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

    $stmt = $conn->prepare('SELECT id, username, email, password FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $user = $result->fetch_assoc();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
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
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Projects Monitor | Login</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .stats-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }
        
        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .bg-primary-light { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; }
        .bg-success-light { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
        .bg-warning-light { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .bg-danger-light { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .bg-info-light { background-color: rgba(13, 202, 240, 0.1); color: #0dcaf0; }
        
        .login-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .project-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s ease;
        }
        
        .project-card:hover {
            border-left-color: #0b5ed7;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .status-healthy { background-color: #d1e7dd; color: #0f5132; }
        .status-warning { background-color: #fff3cd; color: #664d03; }
        .status-critical { background-color: #f8d7da; color: #842029; }
        .status-unknown { background-color: #e2e3e5; color: #41464b; }
        
        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .tech-badge {
            background-color: #f8f9fa;
            color: #6c757d;
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            border-radius: 0.25rem;
            margin-right: 0.3rem;
            margin-bottom: 0.3rem;
            display: inline-block;
        }
    </style>
</head>

<body class="bg-light">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row bg-white shadow-sm mb-4">
            <div class="col-12 py-3">
                <h2 class="mb-0 text-center">
                    <i class="fas fa-project-diagram text-primary me-2"></i>
                    Projects Monitor
                </h2>
            </div>
        </div>
        
        <div class="row">
            <!-- Login Section -->
            <div class="col-lg-4 mb-4">
                <div class="card login-card">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Login</h3>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
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
            
            <!-- Stats and Projects Section -->
            <div class="col-lg-8">
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 col-6 mb-3">
                        <div class="card stats-card fade-in">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-primary-light mx-auto">
                                    <i class="fas fa-project-diagram"></i>
                                </div>
                                <h4 class="fw-bold mb-1">24</h4>
                                <p class="text-muted mb-0">Active Projects</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-6 mb-3">
                        <div class="card stats-card fade-in">
                            <div class="card-body text-center">
                                <div class="stats-icon bg-success-light mx-auto">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h4 class="fw-bold mb-1">18</h4>
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
                                <h4 class="fw-bold mb-1">4</h4>
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
                                <h4 class="fw-bold mb-1">2</h4>
                                <p class="text-muted mb-0">Critical</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Projects -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Recent Project Activity
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">E-Commerce Platform</h6>
                                        <p class="mb-1 text-muted">API response time: 145ms</p>
                                        <small class="text-muted">
                                            <span class="tech-badge">PHP</span>
                                            <span class="tech-badge">MySQL</span>
                                            <span class="tech-badge">Redis</span>
                                        </small>
                                    </div>
                                    <span class="badge status-healthy status-badge">Healthy</span>
                                </div>
                            </div>
                            
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">User Management API</h6>
                                        <p class="mb-1 text-muted">High CPU usage: 78%</p>
                                        <small class="text-muted">
                                            <span class="tech-badge">Node.js</span>
                                            <span class="tech-badge">MongoDB</span>
                                        </small>
                                    </div>
                                    <span class="badge status-warning status-badge">Warning</span>
                                </div>
                            </div>
                            
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Analytics Dashboard</h6>
                                        <p class="mb-1 text-muted">Service unavailable</p>
                                        <small class="text-muted">
                                            <span class="tech-badge">React</span>
                                            <span class="tech-badge">Python</span>
                                            <span class="tech-badge">PostgreSQL</span>
                                        </small>
                                    </div>
                                    <span class="badge status-critical status-badge">Critical</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Status -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-server me-2"></i>System Status
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Server Uptime</span>
                                    <span class="badge bg-success">99.9%</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Database Connection</span>
                                    <span class="badge bg-success">Active</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Cache System</span>
                                    <span class="badge bg-success">Operational</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>API Gateway</span>
                                    <span class="badge bg-warning">Degraded</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-chart-line me-2"></i>Performance Metrics
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Avg Response Time</span>
                                        <span>234ms</span>
                                    </div>
                                    <div class="progress mt-1" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: 75%"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Error Rate</span>
                                        <span>0.03%</span>
                                    </div>
                                    <div class="progress mt-1" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: 97%"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Throughput</span>
                                        <span>1,247 req/min</span>
                                    </div>
                                    <div class="progress mt-1" style="height: 6px;">
                                        <div class="progress-bar bg-info" style="width: 82%"></div>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="d-flex justify-content-between">
                                        <span>Memory Usage</span>
                                        <span>68%</span>
                                    </div>
                                    <div class="progress mt-1" style="height: 6px;">
                                        <div class="progress-bar bg-warning" style="width: 68%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mock API data structure for reference
        const mockApiData = {
            stats: {
                totalProjects: 24,
                healthyProjects: 18,
                warningProjects: 4,
                criticalProjects: 2
            },
            recentActivity: [
                {
                    id: 1,
                    name: "E-Commerce Platform",
                    status: "healthy",
                    message: "API response time: 145ms",
                    technologies: ["PHP", "MySQL", "Redis"],
                    lastUpdated: "2025-07-04T10:30:00Z"
                },
                {
                    id: 2,
                    name: "User Management API",
                    status: "warning",
                    message: "High CPU usage: 78%",
                    technologies: ["Node.js", "MongoDB"],
                    lastUpdated: "2025-07-04T10:25:00Z"
                },
                {
                    id: 3,
                    name: "Analytics Dashboard",
                    status: "critical",
                    message: "Service unavailable",
                    technologies: ["React", "Python", "PostgreSQL"],
                    lastUpdated: "2025-07-04T10:20:00Z"
                }
            ],
            systemStatus: {
                serverUptime: 99.9,
                databaseConnection: "active",
                cacheSystem: "operational",
                apiGateway: "degraded"
            },
            performance: {
                avgResponseTime: 234,
                errorRate: 0.03,
                throughput: 1247,
                memoryUsage: 68
            }
        };

        // Function to load data from API (replace with actual API call)
        function loadProjectData() {
            // Example API call structure:
            // fetch('/api/public/dashboard-data')
            //     .then(response => response.json())
            //     .then(data => updateDashboard(data))
            //     .catch(error => console.error('Error loading data:', error));
            
            // For now, using mock data
            console.log('Mock API data structure:', mockApiData);
        }

        // Function to update dashboard with API data
        function updateDashboard(data) {
            // Update stats cards
            // Update recent activity
            // Update system status
            // Update performance metrics
        }

        // Auto-refresh data every 30 seconds
        setInterval(loadProjectData, 30000);
        
        // Initial load
        loadProjectData();
    </script>
</body>

</html>

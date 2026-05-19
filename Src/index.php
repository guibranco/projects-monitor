<?php
require_once 'session.php';
require_once 'vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Configuration;

if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    exit;
}
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$configuration = new Configuration();
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <title>Projects Monitor | Dashboard</title>
   <meta charset="utf-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous">
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
   <link rel="stylesheet" href="static/styles.css?<?php echo filemtime("static/styles.css"); ?>">
</head>

<body>
   <div id="toast-container"></div>

   <nav class="navbar navbar-dark fixed-top" id="main-navbar">
      <div class="container-fluid px-4">
         <span class="navbar-brand d-flex align-items-center gap-2">
            <i class="bi bi-activity fs-5"></i>
            <span class="fw-bold">Projects Monitor</span>
         </span>
         <div class="d-none d-md-flex gap-2">
            <button type="button" class="btn btn-sm btn-nav-action" onclick="collapseAllSections()">
               <i class="bi bi-chevron-bar-up"></i> Collapse All
            </button>
            <button type="button" class="btn btn-sm btn-nav-action" onclick="expandAllSections()">
               <i class="bi bi-chevron-bar-down"></i> Expand All
            </button>
         </div>
         <div class="d-flex align-items-center gap-3">
            <span class="navbar-user d-none d-lg-flex align-items-center gap-2">
               <i class="bi bi-person-circle"></i>
               <span><?php echo htmlspecialchars($username); ?></span>
            </span>
            <a href="logout.php" class="btn btn-sm btn-danger">
               <i class="bi bi-box-arrow-right"></i>
               <span class="d-none d-md-inline ms-1">Logout</span>
            </a>
            <button class="btn btn-sm btn-nav-settings" type="button" data-bs-toggle="offcanvas" data-bs-target="#settingsPanel" aria-controls="settingsPanel" title="Settings">
               <i class="bi bi-sliders"></i>
            </button>
         </div>
      </div>
   </nav>

   <div class="offcanvas offcanvas-end settings-offcanvas" tabindex="-1" id="settingsPanel" aria-labelledby="settingsPanelLabel">
      <div class="offcanvas-header">
         <h5 class="offcanvas-title" id="settingsPanelLabel">
            <i class="bi bi-sliders me-2"></i>Options
         </h5>
         <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
         <p class="mb-3 settings-welcome">
            <i class="bi bi-person-circle me-1"></i>
            Welcome, <strong><?php echo htmlspecialchars($username); ?></strong>!
         </p>
         <hr class="settings-divider">
         <h6 class="settings-section-label">Feed</h6>
         <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="feedToggle">
            <label class="form-check-label" for="feedToggle">
               <i class="bi bi-rss me-1"></i> Show All / Only mine
            </label>
         </div>
         <hr class="settings-divider">
         <h6 class="settings-section-label">Workflow</h6>
         <div class="form-check form-switch mb-3">
            <input type="checkbox" class="form-check-input" id="workflowToggle" />
            <label class="form-check-label" for="workflowToggle">
               <i class="bi bi-funnel me-1"></i> Workflow Limiter
            </label>
         </div>
         <div id="workflowLimitContainer" class="mb-4" style="display: none;">
            <label for="workflowLimitInput" class="form-label small">Limit Results</label>
            <input type="number" class="form-control form-control-sm settings-input" id="workflowLimitInput" min="1" max="10000"
               placeholder="Enter limit (1–10,000)" required oninput="this.setCustomValidity('')"
               oninvalid="this.setCustomValidity('Please enter a number between 1 and 10,000')" />
            <div class="invalid-feedback">Please enter a number between 1 and 10,000</div>
         </div>
         <hr class="settings-divider">
         <h6 class="settings-section-label">Sections</h6>
         <div class="d-grid gap-2">
            <button type="button" class="btn btn-outline-light btn-sm" onclick="collapseAllSections()">
               <i class="bi bi-chevron-bar-up me-1"></i> Collapse All Sections
            </button>
            <button type="button" class="btn btn-outline-light btn-sm" onclick="expandAllSections()">
               <i class="bi bi-chevron-bar-down me-1"></i> Expand All Sections
            </button>
         </div>
      </div>
   </div>

   <div class="dashboard-container">
      <div class="full-width-section">
         <div class="section-header">
            <i class="bi bi-bar-chart-line me-2"></i>GitHub Webhooks Statistics <span id="counter_webhooks_statistics_github" class="badge rounded-pill"></span>
         </div>
         <div id="webhooks_statistics_github" class="section-content"></div>
      </div>

      <div class="gauges-grid">
         <div id="gauge_chart_cpu" class="gauge"></div>
         <div id="gauge_chart_memory" class="gauge"></div>
         <div id="gauge_chart_process" class="gauge"></div>
         <div id="gauge_chart_emails" class="gauge"></div>
         <div id="gauge_chart_log_errors" class="gauge"></div>
         <div id="gauge_chart_github_usage" class="gauge"></div>
         <div id="gauge_chart_webhooks" class="gauge"></div>
         <div id="gauge_chart_issues" class="gauge"></div>
         <div id="gauge_chart_pull_requests" class="gauge"></div>
         <div id="gauge_chart_workflows_runs" class="gauge"></div>
         <div id="gauge_chart_queues" class="gauge"></div>
         <div id="gauge_chart_pm_messages" class="gauge"></div>
         <div id="gauge_chart_bot_installations" class="gauge"></div>
         <div id="gauge_chart_bot_repositories" class="gauge"></div>
      </div>

      <div class="stats-section">
         <div class="stats-left">
            <div id="queues"></div>
            <div id="accounts_usage"></div>
         </div>
         <div class="stats-right">
            <div class="github-stats">
               <div>
                  <img id="gh_stats" alt="GH Stats" src="" />
                  <img id="gh_streak" alt="GH Streak" src="" />
               </div>
            </div>
            <div class="wakatime-postman">
               <a href="https://wakatime.com/@6be975b7-7258-4475-bc73-9c0fc554430e" target='_blank'
                  rel='noopener noreferrer'>
                  <img id="wakatime" alt="Wakatime stats" src="" />
               </a>
               <div id="postman"></div>
            </div>
         </div>
      </div>

      <div class="pie-charts">
         <div class="pie-chart">
            <div id="pie_chart_1"></div>
         </div>
         <div class="pie-chart">
            <div id="pie_chart_2"></div>
         </div>
         <div class="pie-chart">
            <div id="pie_chart_3"></div>
         </div>
      </div>

      <div class="bottom-grid">
         <div class="chart-container">
            <div id="latest_release"></div>
         </div>
         <div class="chart-container">
            <div id="error_log_files"></div>
         </div>
      </div>

      <div class="bottom-grid">
         <div class="chart-container">
            <div id="hooks_last_check"></div>
         </div>
         <div class="chart-container">
            <div class="text-end mb-1">
               <button id="btn_truncate_messages" class="btn btn-warning btn-sm" style="display:none" onclick="if(window.confirmTruncateMessages?.()) window.truncateMessages?.()">Truncate All Messages</button>
            </div>
            <div id="messages_by_applications"></div>
         </div>
      </div>

      <div class="full-width-section">
         <div class="section-header">
            <i class="bi bi-collection me-2"></i>Messages Grouped <span id="counter_messages_grouped" class="badge rounded-pill"></span>
         </div>
         <div id="messages_grouped" class="section-content"></div>
      </div>

      <div class="full-width-section">
         <div class="section-header">
            <i class="bi bi-exclamation-triangle me-2"></i>Error Log Messages <span id="counter_error_log_messages" class="badge rounded-pill"></span>
         </div>
         <div id="error_log_messages" class="section-content"></div>
      </div>

      <div class="full-width-section">
         <div class="section-header">
            <i class="bi bi-gear me-2"></i>Workflow Runs <span id="counter_workflow_runs" class="badge rounded-pill"></span>
         </div>
         <div id="workflow_runs" class="section-content"></div>
      </div>

      <div class="full-width-section">
         <div class="section-header">
            <i class="bi bi-diagram-2 me-2"></i>Branches <span id="counter_branches" class="badge rounded-pill"></span>
         </div>
         <div id="branches" class="section-content"></div>
      </div>

      <div class="full-width-section">
         <div class="section-header">
            <i class="bi bi-arrow-left-right me-2"></i>Pull Requests <span id="counter_pull_requests" class="badge rounded-pill"></span>
         </div>
         <div id="pull_requests" class="section-content"></div>
      </div>

      <div class="data-lists">
         <div class="data-column">
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-slash-circle me-2"></i>Issues Blocked <span id="counter_issues_blocked" class="badge rounded-pill"></span>
               </div>
               <div id="issues_blocked" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-slash-circle me-2"></i>Pull Requests Blocked <span id="counter_pull_requests_blocked" class="badge rounded-pill"></span>
               </div>
               <div id="pull_requests_blocked" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-cloud me-2"></i>GitHub API Usage <span id="counter_api_usage" class="badge rounded-pill"></span>
               </div>
               <div id="api_usage" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-shield-lock me-2"></i>WireGuard <span id="counter_wireguard" class="badge rounded-pill"></span>
               </div>
               <div id="wireguard" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-heart-pulse me-2"></i>HealthChecksIo <span id="counter_healthchecksio" class="badge rounded-pill"></span>
               </div>
               <div id="healthchecksio" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-robot me-2"></i>UpTimeRobot <span id="counter_uptimerobot" class="badge rounded-pill"></span>
               </div>
               <div id="uptimerobot" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-globe me-2"></i>Domains <span id="counter_domains" class="badge rounded-pill"></span>
               </div>
               <div id="domains" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-pencil me-2"></i>Pull Requests Authored <span id="counter_pull_requests_authored" class="badge rounded-pill"></span>
               </div>
               <div id="pull_requests_authored" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-pencil me-2"></i>Issues Authored <span id="counter_issues_authored" class="badge rounded-pill"></span>
               </div>
               <div id="issues_authored" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-box me-2"></i>Installed Repositories <span id="counter_installed_repositories" class="badge rounded-pill"></span>
               </div>
               <div id="installed_repositories" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-rss me-2"></i>Feed <span id="counter_feed" class="badge rounded-pill"></span>
               </div>
               <div id="feed" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-folder2 me-2"></i>Repositories <span id="counter_repositories" class="badge rounded-pill"></span>
               </div>
               <div id="repositories" class="section-content"></div>
            </div>
         </div>
         <div class="data-column">
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-question-circle me-2"></i>Issues Awaiting Triage <span id="counter_triage" class="badge rounded-pill"></span>
               </div>
               <div id="triage" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-question-circle me-2"></i>Pull Requests Awaiting Triage <span id="counter_pull_requests_triage" class="badge rounded-pill"></span>
               </div>
               <div id="pull_requests_triage" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-arrow-left-right me-2"></i>Pull Requests <span id="counter_pull_requests_latest" class="badge rounded-pill"></span>
               </div>
               <div id="pull_requests_latest" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-person-check me-2"></i>Issues Assigned <span id="counter_assigned" class="badge rounded-pill"></span>
               </div>
               <div id="assigned" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-hammer me-2"></i>Issues WIP <span id="counter_wip" class="badge rounded-pill"></span>
               </div>
               <div id="wip" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-bug me-2"></i>Issues Bug <span id="counter_bug" class="badge rounded-pill"></span>
               </div>
               <div id="bug" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-list-task me-2"></i>Issues <span id="counter_issues" class="badge rounded-pill"></span>
               </div>
               <div id="issues" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-clock me-2"></i>Cronjobs <span id="counter_cronjobs" class="badge rounded-pill"></span>
               </div>
               <div id="cronjobs" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-envelope me-2"></i>Senders <span id="counter_senders" class="badge rounded-pill"></span>
               </div>
               <div id="senders" class="section-content"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  <i class="bi bi-cpu me-2"></i>AppVeyor <span id="counter_appveyor" class="badge rounded-pill"></span>
               </div>
               <div id="appveyor" class="section-content"></div>
            </div>
         </div>
      </div>

      <div class="full-width-section">
         <div class="section-header">
            <i class="bi bi-broadcast me-2"></i>Webhooks Statistics <span id="counter_webhooks_statistics" class="badge rounded-pill"></span>
         </div>
         <div id="webhooks_statistics" class="section-content"></div>
      </div>
   </div>

   <footer class="text-center py-3 mt-2 text-muted small">
      <a href="https://github.com/guibranco/projects-monitor" target="_blank" rel="noopener noreferrer" class="text-muted text-decoration-none">
         <i class="bi bi-github me-1"></i>guibranco/projects-monitor
      </a>
   </footer>

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha256-CDOy6cOibCWEdsRiZuaHf8dSGGJRYuBGC+mjoJimHGw=" crossorigin="anonymous"></script>
   <script src="https://www.gstatic.com/charts/loader.js"></script>
   <script type="module" src="static/main.js?<?php echo filemtime("static/main.js"); ?>"></script>
   <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
   <script>
      window.OneSignalDeferred = window.OneSignalDeferred || [];
      OneSignalDeferred.push(function (OneSignal) {
         OneSignal.init({
            appId: "90c57079-9ef2-4719-bddf-361e6510de17",
            googleProjectNumber: "256841700684"
         });
      });
   </script>
</body>

</html>

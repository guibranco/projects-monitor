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
   <link rel="stylesheet" href="static/styles.css?<?php echo filemtime("static/styles.css"); ?>">
</head>

<body>
   <div id="toast-container"></div>

   <div class="floating-box">
      <div class="card shadow">
         <div class="card-header">
            <h6 class="mb-0">
               <a href="#" class="text-decoration-none text-white" data-bs-toggle="collapse" data-bs-target="#userMenu"
                  aria-expanded="true" aria-controls="userMenu">
                  Options
               </a>
            </h6>
         </div>
         <div id="userMenu" class="collapse show">
            <div class="card-body">
               <p class="mb-2">Welcome, <strong><?php echo htmlspecialchars($username); ?></strong>!</p>
               <a href="logout.php" class="btn btn-danger btn-sm w-100">Logout</a>
               <div class="form-check form-switch mt-3">
                  <input class="form-check-input" type="checkbox" id="feedToggle">
                  <label class="form-check-label" for="feedToggle">
                     Feed - Show All / Only mine
                  </label>
               </div>
               <div class="form-check form-switch mb-3">
                  <input type="checkbox" class="form-check-input" id="workflowToggle" />
                  <label class="form-check-label" for="workflowToggle">Workflow Limiter</label>
               </div>
               <div id="workflowLimitContainer" class="mb-3" style="display: none;">
                  <label for="workflowLimitInput" class="form-label">Limit Results</label>
                  <input type="number" class="form-control" id="workflowLimitInput" min="1" max="10000"
                     placeholder="Enter limit (1-10,000)" required oninput="this.setCustomValidity('')"
                     oninvalid="this.setCustomValidity('Please enter a number between 1 and 10,000')" />
                  <div class="invalid-feedback">
                     Please enter a number between 1 and 10,000
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>

   <div class="dashboard-container">
      <div class="top-chart">
         <div id="webhooks_statistics_github"></div>
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
         <div id="gauge_chart_webhooks_failed" class="gauge"></div>
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
           <div id="messages_by_applications"></div>
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

      <div class="full-width-section">
         <div id="messages_grouped"></div>
      </div>

      <div class="full-width-section">
         <div id="error_log_messages"></div>
      </div>

      <div class="full-width-section">
         <div class="section-header">
            Workflow Runs <span id="counter_workflow_runs" class="badge rounded-pill"></span>
         </div>
         <div id="workflow_runs"></div>
      </div>

      <div class="full-width-section">
         <div class="section-header">
            Branches <span id="counter_branches" class="badge rounded-pill"></span>
         </div>
         <div id="branches"></div>
      </div>
       
      <div class="data-lists">
         <div class="data-column">
            <div class="data-item">
               <div class="section-header">
                  Issues Blocked <span id="counter_issues_blocked" class="badge rounded-pill"></span>
               </div>
               <div id="issues_blocked"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Pull Requests Blocked <span id="counter_pull_requests_blocked" class="badge rounded-pill"></span>
               </div>
               <div id="pull_requests_blocked"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  GitHub API Usage <span id="counter_api_usage" class="badge rounded-pill"></span>
               </div>
               <div id="api_usage"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  WireGuard <span id="counter_wireguard" class="badge rounded-pill"></span>
               </div>
               <div id="wireguard"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  HealthChecksIo <span id="counter_healthchecksio" class="badge rounded-pill"></span>
               </div>
               <div id="healthchecksio"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  UpTimeRobot <span id="counter_uptimerobot" class="badge rounded-pill"></span>
               </div>
               <div id="uptimerobot"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Domains <span id="counter_domains" class="badge rounded-pill"></span>
               </div>
               <div id="domains"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Pull Requests Authored <span id="counter_pull_requests_authored" class="badge rounded-pill"></span>
               </div>
               <div id="pull_requests_authored"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Issues Authored <span id="counter_issues_authored" class="badge rounded-pill"></span>
               </div>
               <div id="issues_authored"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Installed Repositories <span id="counter_installed_repositories" class="badge rounded-pill"></span>
               </div>
               <div id="installed_repositories"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Feed <span id="counter_feed" class="badge rounded-pill"></span>
               </div>
               <div id="feed"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Repositories <span id="counter_repositories" class="badge rounded-pill"></span>
               </div>
               <div id="repositories"></div>
            </div>
         </div>
         <div class="data-column">
            <div class="data-item">
               <div class="section-header">
                  Issues Awaiting Triage <span id="counter_triage" class="badge rounded-pill"></span>
               </div>
               <div id="triage"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Pull Requests Awaiting Triage <span id="counter_pull_requests_triage"
                     class="badge rounded-pill"></span>
               </div>
               <div id="pull_requests_triage"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Pull Requests <span id="counter_pull_requests_latest" class="badge rounded-pill"></span>
               </div>
               <div id="pull_requests_latest"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Issues Assigned <span id="counter_assigned" class="badge rounded-pill"></span>
               </div>
               <div id="assigned"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Issues WIP <span id="counter_wip" class="badge rounded-pill"></span>
               </div>
               <div id="wip"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Issues Bug <span id="counter_bug" class="badge rounded-pill"></span>
               </div>
               <div id="bug"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Issues <span id="counter_issues" class="badge rounded-pill"></span>
               </div>
               <div id="issues"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Cronjobs <span id="counter_cronjobs" class="badge rounded-pill"></span>
               </div>
               <div id="cronjobs"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  Senders <span id="counter_senders" class="badge rounded-pill"></span>
               </div>
               <div id="senders"></div>
            </div>
            <div class="data-item">
               <div class="section-header">
                  AppVeyor <span id="counter_appveyor" class="badge rounded-pill"></span>
               </div>
               <div id="appveyor"></div>
            </div>
         </div>
      </div>

      <div class="full-width-section">
         <div id="webhooks_statistics"></div>
      </div>
   </div>

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha256-CDOy6cOibCWEdsRiZuaHf8dSGGJRYuBGC+mjoJimHGw=" crossorigin="anonymous"></script>
   <script src="https://www.gstatic.com/charts/loader.js"></script>
   <script src="static/scripts.js?<?php echo filemtime("static/scripts.js"); ?>"></script>
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

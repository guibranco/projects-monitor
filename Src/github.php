<?php
$activePage = "github";
$pageTitle  = "GitHub work";
require_once "partials/dashboard-head.php";
?>
      <div class="full-width-section" data-section="kpis">
         <div class="section-header">
            <i class="bi bi-speedometer2 me-2"></i>Key numbers
         </div>
         <div class="kpi-grid section-content">
            <div id="gauge_chart_issues" class="kpi-tile"></div>
            <div id="gauge_chart_pull_requests" class="kpi-tile"></div>
            <div id="gauge_chart_workflows_runs" class="kpi-tile"></div>
         </div>
      </div>

      <section class="page-group">
         <h2 class="page-group-title"><i class="bi bi-person-exclamation me-2"></i>Needs me<small>triage, assigned work and failing CI</small></h2>
         <div class="full-width-section" data-section="workflow_runs_management">
            <div class="section-header">
               <i class="bi bi-gear-wide-connected me-2"></i>Workflow Runs <span id="counter_workflow_runs_management" class="badge rounded-pill"></span>
               <a href="workflow-runs.php" class="btn btn-sm btn-nav-action ms-2" title="All workflow runs, with filters and a pausable refresh"
                  onclick="event.stopPropagation()">
                  <i class="bi bi-list-ul"></i>
               </a>
            </div>
            <div id="workflow_runs_management" class="section-content"></div>
         </div>
         <div class="data-lists">
         <div class="data-column">
         <div class="data-item" data-section="triage">
            <div class="section-header">
               <i class="bi bi-question-circle me-2"></i>Issues Awaiting Triage <span id="counter_triage" class="badge rounded-pill"></span>
            </div>
            <div id="triage" class="section-content"></div>
         </div>
         <div class="data-item" data-section="assigned">
            <div class="section-header">
               <i class="bi bi-person-check me-2"></i>Issues Assigned <span id="counter_assigned" class="badge rounded-pill"></span>
            </div>
            <div id="assigned" class="section-content"></div>
         </div>
         </div>
         <div class="data-column">
         <div class="data-item" data-section="pull_requests_triage">
            <div class="section-header">
               <i class="bi bi-question-circle me-2"></i>Pull Requests Awaiting Triage <span id="counter_pull_requests_triage" class="badge rounded-pill"></span>
            </div>
            <div id="pull_requests_triage" class="section-content"></div>
         </div>
         <div class="data-item" data-section="bug">
            <div class="section-header">
               <i class="bi bi-bug me-2"></i>Issues Bug <span id="counter_bug" class="badge rounded-pill"></span>
            </div>
            <div id="bug" class="section-content"></div>
         </div>
         </div>
         </div>
      </section>

      <section class="page-group">
         <h2 class="page-group-title"><i class="bi bi-hourglass me-2"></i>Waiting on others<small>what I opened and work in progress</small></h2>
         <div class="data-lists">
         <div class="data-column">
         <div class="data-item" data-section="pull_requests_authored">
            <div class="section-header">
               <i class="bi bi-pencil me-2"></i>Pull Requests Authored <span id="counter_pull_requests_authored" class="badge rounded-pill"></span>
            </div>
            <div id="pull_requests_authored" class="section-content"></div>
         </div>
         <div class="data-item" data-section="wip">
            <div class="section-header">
               <i class="bi bi-hammer me-2"></i>Issues WIP <span id="counter_wip" class="badge rounded-pill"></span>
            </div>
            <div id="wip" class="section-content"></div>
         </div>
         </div>
         <div class="data-column">
         <div class="data-item" data-section="issues_authored">
            <div class="section-header">
               <i class="bi bi-pencil me-2"></i>Issues Authored <span id="counter_issues_authored" class="badge rounded-pill"></span>
            </div>
            <div id="issues_authored" class="section-content"></div>
         </div>
         </div>
         </div>
      </section>

      <section class="page-group">
         <h2 class="page-group-title"><i class="bi bi-slash-circle me-2"></i>Blocked<small>issues and pull requests labelled blocked</small></h2>
         <div class="data-lists">
         <div class="data-column">
         <div class="data-item" data-section="issues_blocked">
            <div class="section-header">
               <i class="bi bi-slash-circle me-2"></i>Issues Blocked <span id="counter_issues_blocked" class="badge rounded-pill"></span>
            </div>
            <div id="issues_blocked" class="section-content"></div>
         </div>
         </div>
         <div class="data-column">
         <div class="data-item" data-section="pull_requests_blocked">
            <div class="section-header">
               <i class="bi bi-slash-circle me-2"></i>Pull Requests Blocked <span id="counter_pull_requests_blocked" class="badge rounded-pill"></span>
            </div>
            <div id="pull_requests_blocked" class="section-content"></div>
         </div>
         </div>
         </div>
      </section>

      <section class="page-group">
         <h2 class="page-group-title"><i class="bi bi-activity me-2"></i>Activity<small>everything else, newest first</small></h2>
         <div class="data-lists">
         <div class="data-column">
         <div class="data-item" data-section="pull_requests_latest">
            <div class="section-header">
               <i class="bi bi-arrow-left-right me-2"></i>Pull Requests (latest) <span id="counter_pull_requests_latest" class="badge rounded-pill"></span>
            </div>
            <div id="pull_requests_latest" class="section-content"></div>
         </div>
         <div class="data-item" data-section="pull_requests">
            <div class="section-header">
               <i class="bi bi-arrow-left-right me-2"></i>Pull Requests (webhooks) <span id="counter_pull_requests" class="badge rounded-pill"></span>
            </div>
            <div id="pull_requests" class="section-content"></div>
         </div>
         <div class="data-item" data-section="branches">
            <div class="section-header">
               <i class="bi bi-diagram-2 me-2"></i>Branches <span id="counter_branches" class="badge rounded-pill"></span>
            </div>
            <div id="branches" class="section-content"></div>
         </div>
         </div>
         <div class="data-column">
         <div class="data-item" data-section="issues">
            <div class="section-header">
               <i class="bi bi-list-task me-2"></i>Issues <span id="counter_issues" class="badge rounded-pill"></span>
            </div>
            <div id="issues" class="section-content"></div>
         </div>
         <div class="data-item" data-section="releases_recent">
            <div class="section-header">
               <i class="bi bi-tag me-2"></i>Recent Releases <span id="counter_releases_recent" class="badge rounded-pill"></span>
               <a href="releases.php" class="btn btn-sm btn-nav-action ms-2" title="View All Releases"
                  onclick="event.stopPropagation()">
                  <i class="bi bi-list-ul"></i>
               </a>
            </div>
            <div id="releases_recent" class="section-content"></div>
         </div>
         <div class="data-item" data-section="latest_release">
            <div class="section-header">
               <i class="bi bi-box-seam me-2"></i>Latest BancosBrasileiros release
            </div>
            <div id="latest_release" class="section-content"></div>
         </div>
         </div>
         </div>
      </section>

      <section class="page-group">
         <h2 class="page-group-title"><i class="bi bi-shuffle me-2"></i>GitHub API usage<small>calls through github-api-proxy</small></h2>
         <div class="full-width-section" data-section="github_api_proxy">
            <div class="section-header">
               <i class="bi bi-graph-up me-2"></i>GitHub API proxy <span id="counter_github_api_proxy" class="badge rounded-pill"></span>
            </div>
            <div class="section-content">
               <div id="github_api_proxy_range"></div>
               <div id="github_api_proxy_caption" class="small text-white-50 mb-2"></div>
               <div id="github_api_proxy_timeline"></div>
            </div>
         </div>
         <div class="full-width-section" data-section="github_api_proxy_callers">
            <div class="section-header">
               <i class="bi bi-people me-2"></i>GitHub API callers <span id="counter_github_api_proxy_callers" class="badge rounded-pill"></span>
            </div>
            <div id="github_api_proxy_callers" class="section-content"></div>
         </div>
         <div class="full-width-section" data-section="github_api_proxy_rate_limits">
            <div class="section-header">
               <i class="bi bi-speedometer me-2"></i>GitHub API rate limits <span id="counter_github_api_proxy_rate_limits" class="badge rounded-pill"></span>
            </div>
            <div id="github_api_proxy_rate_limits" class="section-content"></div>
         </div>
      </section>
<?php
require_once "partials/dashboard-foot.php";

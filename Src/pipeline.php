<?php
$activePage = "pipeline";
$pageTitle  = "Pipeline";
require_once "partials/dashboard-head.php";
?>
      <div class="full-width-section" data-section="kpis">
         <div class="section-header">
            <i class="bi bi-speedometer2 me-2"></i>Key numbers
         </div>
         <div class="kpi-grid section-content">
            <div id="gauge_chart_webhooks" class="kpi-tile"></div>
            <div id="gauge_chart_queues" class="kpi-tile"></div>
            <div id="gauge_chart_broker_usage" class="kpi-tile"></div>
            <div id="gauge_chart_bot_queue" class="kpi-tile"></div>
            <div id="gauge_chart_bot_installations" class="kpi-tile"></div>
            <div id="gauge_chart_bot_repositories" class="kpi-tile"></div>
         </div>
      </div>

      <div class="full-width-section" data-section="pending_processing">
         <div class="section-header">
            <i class="bi bi-hourglass-split me-2"></i>Pending processing <span id="counter_pending_processing" class="badge rounded-pill"></span>
         </div>
         <div id="pending_processing" class="section-content"></div>
      </div>

      <div class="full-width-section" data-section="webhook_processing_stats">
         <div class="section-header">
            <i class="bi bi-stack me-2"></i>Processing State Counts <span id="counter_webhook_processing_stats" class="badge rounded-pill"></span>
         </div>
         <div id="webhook_processing_stats" class="section-content"></div>
      </div>

      <div class="full-width-section" data-section="pr_idle">
         <div class="section-header">
            <i class="bi bi-clock-history me-2"></i>Idle Pull Requests <span id="counter_pr_idle" class="badge rounded-pill"></span>
         </div>
         <div class="section-content">
            <div id="pr_idle_worker" class="section-note"></div>
            <div id="pr_idle"></div>
         </div>
      </div>

      <div class="full-width-section" data-section="queues_and_brokers">
         <div class="section-header">
            <i class="bi bi-inboxes me-2"></i>Queues and brokers
         </div>
         <div class="section-content">
            <div id="queues"></div>
            <div id="broker_usage_caption" class="small text-white-50 mt-3"></div>
            <div id="broker_usage"></div>
            <div id="throttled_repositories_caption" class="small text-white-50 mt-3"></div>
            <div id="throttled_repositories"></div>
         </div>
      </div>

      <div class="data-lists">
      <div class="data-column">
      <div class="data-item" data-section="webhooks_workers">
         <div class="section-header">
            <i class="bi bi-hdd-rack me-2"></i>Webhooks workers <span id="counter_webhooks_workers" class="badge rounded-pill"></span>
         </div>
         <div id="webhooks_workers" class="section-content action-list"></div>
      </div>
      <div class="data-item" data-section="feed">
         <div class="section-header">
            <i class="bi bi-rss me-2"></i>Feed <span id="counter_feed" class="badge rounded-pill"></span>
         </div>
         <div id="feed" class="section-content"></div>
      </div>
      <div class="data-item" data-section="senders">
         <div class="section-header">
            <i class="bi bi-envelope me-2"></i>Senders <span id="counter_senders" class="badge rounded-pill"></span>
         </div>
         <div id="senders" class="section-content"></div>
      </div>
      </div>
      <div class="data-column">
      <div class="data-item" data-section="gstraccini_jobs">
         <div class="section-header">
            <i class="bi bi-robot me-2"></i>GStraccini Bot jobs <span id="counter_gstraccini_jobs" class="badge rounded-pill"></span>
         </div>
         <div id="gstraccini_jobs" class="section-content action-list"></div>
      </div>
      <div class="data-item" data-section="repositories">
         <div class="section-header">
            <i class="bi bi-folder2 me-2"></i>Repositories <span id="counter_repositories" class="badge rounded-pill"></span>
         </div>
         <div id="repositories" class="section-content"></div>
      </div>
      <div class="data-item" data-section="installed_repositories">
         <div class="section-header">
            <i class="bi bi-box me-2"></i>Installed Repositories <span id="counter_installed_repositories" class="badge rounded-pill"></span>
         </div>
         <div id="installed_repositories" class="section-content"></div>
      </div>
      </div>
      </div>

      <div class="full-width-section" data-section="webhooks_statistics_github">
         <div class="section-header">
            <i class="bi bi-bar-chart-line me-2"></i>GitHub Webhooks Statistics <span id="counter_webhooks_statistics_github" class="badge rounded-pill"></span>
         </div>
         <div id="webhooks_statistics_github" class="section-content"></div>
      </div>

      <div class="full-width-section" data-section="webhooks_statistics">
         <div class="section-header">
            <i class="bi bi-broadcast me-2"></i>Webhooks Statistics <span id="counter_webhooks_statistics" class="badge rounded-pill"></span>
         </div>
         <div id="webhooks_statistics" class="section-content"></div>
      </div>

      <div class="full-width-section" data-section="hooks_last_check">
         <div class="section-header">
            <i class="bi bi-clock me-2"></i>Webhooks last check
         </div>
         <div id="hooks_last_check" class="section-content"></div>
      </div>
<?php
require_once "partials/dashboard-foot.php";

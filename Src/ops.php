<?php
$activePage = "ops";
$pageTitle  = "Ops";
require_once "partials/dashboard-head.php";
?>
      <div class="full-width-section" data-section="kpis">
         <div class="section-header">
            <i class="bi bi-speedometer2 me-2"></i>Key numbers
         </div>
         <div class="kpi-grid section-content">
            <div id="gauge_chart_emails" class="kpi-tile"></div>
            <div id="gauge_chart_github_usage" class="kpi-tile"></div>
         </div>
      </div>

      <div class="full-width-section" data-section="server_health">
         <div class="section-header">
            <i class="bi bi-hdd-rack me-2"></i>Server Health <span id="counter_server_health" class="badge rounded-pill"></span>
         </div>
         <div id="server_health" class="section-content">
            <div class="server-health-card">
               <div class="server-health-name">Osasco (cPanel)</div>
               <div class="server-health-gauges">
                  <div id="gauge_chart_cpu" class="kpi-tile"></div>
                  <div id="gauge_chart_memory" class="kpi-tile"></div>
                  <div id="gauge_chart_process" class="kpi-tile"></div>
               </div>
            </div>
            <div id="server_health_ssh_hosts"></div>
         </div>
      </div>

      <div class="data-lists">
      <div class="data-column">
      <div class="data-item" data-section="healthchecksio">
         <div class="section-header">
            <i class="bi bi-heart-pulse me-2"></i>HealthChecksIo <span id="counter_healthchecksio" class="badge rounded-pill"></span>
         </div>
         <div id="healthchecksio" class="section-content"></div>
      </div>
      <div class="data-item" data-section="wireguard">
         <div class="section-header">
            <i class="bi bi-shield-lock me-2"></i>WireGuard <span id="counter_wireguard" class="badge rounded-pill"></span>
         </div>
         <div id="wireguard" class="section-content"></div>
      </div>
      <div class="data-item" data-section="cronjobs">
         <div class="section-header">
            <i class="bi bi-clock me-2"></i>Cronjobs <span id="counter_cronjobs" class="badge rounded-pill"></span>
         </div>
         <div id="cronjobs" class="section-content"></div>
      </div>
      <div class="data-item" data-section="api_usage">
         <div class="section-header">
            <i class="bi bi-cloud me-2"></i>GitHub API Usage <span id="counter_api_usage" class="badge rounded-pill"></span>
         </div>
         <div id="api_usage" class="section-content"></div>
      </div>
      <div class="data-item" data-section="appveyor">
         <div class="section-header">
            <i class="bi bi-cpu me-2"></i>AppVeyor <span id="counter_appveyor" class="badge rounded-pill"></span>
         </div>
         <div id="appveyor" class="section-content"></div>
      </div>
      </div>
      <div class="data-column">
      <div class="data-item" data-section="uptimerobot">
         <div class="section-header">
            <i class="bi bi-robot me-2"></i>UpTimeRobot <span id="counter_uptimerobot" class="badge rounded-pill"></span>
         </div>
         <div id="uptimerobot" class="section-content"></div>
      </div>
      <div class="data-item" data-section="domains">
         <div class="section-header">
            <i class="bi bi-globe me-2"></i>Domains <span id="counter_domains" class="badge rounded-pill"></span>
            <a href="dns.php" class="btn btn-sm btn-nav-action ms-2" title="View DNS Records"
               onclick="event.stopPropagation()">
               <i class="bi bi-hdd-network"></i>
            </a>
         </div>
         <div id="domains" class="section-content"></div>
      </div>
      <div class="data-item" data-section="cloudflare">
         <div class="section-header">
            <i class="bi bi-cloud me-2"></i>Cloudflare <span id="counter_cloudflare" class="badge rounded-pill"></span>
         </div>
         <div id="cloudflare" class="section-content"></div>
      </div>
      <div class="data-item" data-section="vercel">
         <div class="section-header">
            <i class="bi bi-triangle me-2"></i>Vercel <span id="counter_vercel" class="badge rounded-pill"></span>
         </div>
         <div id="vercel" class="section-content"></div>
      </div>
      <div class="data-item" data-section="render">
         <div class="section-header">
            <i class="bi bi-hdd-stack me-2"></i>Render <span id="counter_render" class="badge rounded-pill"></span>
         </div>
         <div id="render" class="section-content"></div>
      </div>
      </div>
      </div>

      <div class="full-width-section" data-section="accounts_usage">
         <div class="section-header">
            <i class="bi bi-stopwatch me-2"></i>GitHub Actions usage <span id="counter_accounts_usage" class="badge rounded-pill"></span>
         </div>
         <div id="accounts_usage" class="section-content"></div>
      </div>
<?php
require_once "partials/dashboard-foot.php";

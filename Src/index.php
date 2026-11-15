<?php
$activePage = "overview";
$pageTitle  = "Overview";
require_once "partials/dashboard-head.php";
?>
      <section id="attention_panel" class="attention-panel" aria-labelledby="attention_heading">
         <div id="attention_strip" class="status-strip"></div>
         <div class="attention-heading">
            <h2 id="attention_heading" class="attention-heading-title"><i class="bi bi-exclamation-diamond me-2"></i>Needs attention</h2>
            <span id="counter_attention" class="badge rounded-pill attention-counter"></span>
         </div>
         <div id="attention_list" class="attention-list">
            <div class="attention-empty">
               <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Checking every source…
            </div>
         </div>
         <div id="attention_all_clear" class="attention-all-clear"></div>
      </section>

      <div class="full-width-section" data-section="kpis">
         <div class="section-header">
            <i class="bi bi-speedometer2 me-2"></i>Key numbers
         </div>
         <div class="kpi-grid section-content">
            <div id="gauge_chart_webhooks" class="kpi-tile"></div>
            <div id="gauge_chart_queues" class="kpi-tile"></div>
            <div id="gauge_chart_bot_queue" class="kpi-tile"></div>
            <div id="gauge_chart_pm_messages" class="kpi-tile"></div>
         </div>
      </div>
<?php
require_once "partials/dashboard-foot.php";

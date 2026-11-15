<?php
$activePage = "errors";
$pageTitle  = "Errors";
require_once "partials/dashboard-head.php";
?>
      <div class="full-width-section" data-section="kpis">
         <div class="section-header">
            <i class="bi bi-speedometer2 me-2"></i>Key numbers
         </div>
         <div class="kpi-grid section-content">
            <div id="gauge_chart_log_errors" class="kpi-tile"></div>
            <div id="gauge_chart_pm_messages" class="kpi-tile"></div>
         </div>
      </div>

      <div class="full-width-section" data-section="messages_grouped">
         <div class="section-header">
            <i class="bi bi-collection me-2"></i>Messages Grouped <span id="counter_messages_grouped" class="badge rounded-pill"></span>
         </div>
         <div class="section-content">
            <div class="text-end mb-1">
               <button id="btn_truncate_messages_grouped" class="btn btn-warning btn-sm" style="display:none"
                  onclick="window.confirmTruncateMessages?.(() => window.truncateMessages?.())">
                  <i class="bi bi-trash me-1"></i>Truncate All Messages</button>
            </div>
            <div id="messages_grouped"></div>
         </div>
      </div>

      <div class="full-width-section" data-section="db_error_messages">
         <div class="section-header">
            <i class="bi bi-database-exclamation me-2"></i>Error Log (Database) <span id="counter_db_error_messages" class="badge rounded-pill"></span>
         </div>
         <div class="section-content">
            <div class="text-end mb-1">
               <button id="btn_truncate_db_errors" class="btn btn-warning btn-sm" style="display:none"
                  onclick="window.confirmTruncateDbErrors?.(() => window.truncateDbErrors?.())">
                  <i class="bi bi-trash me-1"></i>Truncate All
               </button>
            </div>
            <div id="db_error_messages"></div>
         </div>
      </div>

      <div class="full-width-section" data-section="error_log_messages">
         <div class="section-header">
            <i class="bi bi-exclamation-triangle me-2"></i>Error Log Messages <span id="counter_error_log_messages" class="badge rounded-pill"></span>
         </div>
         <div id="error_log_messages" class="section-content"></div>
      </div>

      <div class="data-lists">
      <div class="data-column">
      <div class="data-item" data-section="error_log_files">
         <div class="section-header">
            <i class="bi bi-file-earmark-text me-2"></i>Error log files <span id="counter_error_log_files" class="badge rounded-pill"></span>
         </div>
         <div id="error_log_files" class="section-content"></div>
      </div>
      </div>
      <div class="data-column">
      <div class="data-item" data-section="messages_by_applications">
         <div class="section-header">
            <i class="bi bi-app-indicator me-2"></i>Messages by application <span id="counter_messages_by_applications" class="badge rounded-pill"></span>
         </div>
         <div class="section-content">
            <div class="text-end mb-1">
               <button id="btn_truncate_messages" class="btn btn-warning btn-sm" style="display:none"
                  onclick="window.confirmTruncateMessages?.(() => window.truncateMessages?.())">Truncate All
                  Messages</button>
            </div>
            <div id="messages_by_applications"></div>
         </div>
      </div>
      </div>
      </div>
         <!-- Message detail modal -->
         <div class="modal fade" id="messageDetailsModal" tabindex="-1"
            aria-labelledby="messageDetailsModalLabel" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
               <div class="modal-content"
                  style="background:rgba(13,17,23,.97);border:1px solid rgba(255,255,255,.15);color:#fff;">
                  <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.1);">
                     <div class="me-auto">
                        <h5 class="modal-title mb-1" id="messageDetailsModalLabel">
                           <i class="bi bi-list-ul me-2"></i>Message Details
                        </h5>
                        <small id="msgDetailSubtitle" class="opacity-75 d-block text-truncate" style="max-width:60vw;"></small>
                     </div>
                     <button type="button" class="btn btn-sm btn-danger me-3" id="btn_delete_message_group">
                        <i class="bi bi-trash me-1"></i>Delete Group
                     </button>
                     <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                  </div>
                  <div class="modal-body p-0" id="msgDetailBody">
                     <div class="text-center py-4">
                        <div class="spinner-border text-light" role="status">
                           <span class="visually-hidden">Loading…</span>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
<?php
require_once "partials/dashboard-foot.php";

<?php
$activePage = "workflows";
$pageTitle  = "Workflow runs";
require_once "partials/dashboard-head.php";
?>
      <div class="full-width-section" data-section="workflow_runs_page">
         <div class="section-header">
            <i class="bi bi-gear-wide-connected me-2"></i>Workflow Runs <span id="wr_counter" class="badge rounded-pill"></span>
         </div>
         <div class="section-content">
            <div class="wr-toolbar" role="toolbar" aria-label="Workflow runs refresh and filters">
               <div class="wr-toolbar-group">
                  <button type="button" id="wr_pause" class="btn btn-sm btn-outline-light" aria-pressed="false"
                     title="Pause the automatic refresh">
                     <i class="bi bi-pause-fill"></i><span class="ms-1">Pause</span>
                  </button>
                  <button type="button" id="wr_refresh_now" class="btn btn-sm btn-outline-light" title="Refresh now">
                     <i class="bi bi-arrow-clockwise"></i><span class="ms-1 d-none d-sm-inline">Refresh now</span>
                  </button>
                  <label class="wr-label" for="wr_interval">Every</label>
                  <select id="wr_interval" class="form-select form-select-sm wr-select" aria-label="Refresh interval">
                     <option value="30">30 sec</option>
                     <option value="60" selected>1 min</option>
                     <option value="120">2 min</option>
                     <option value="300">5 min</option>
                     <option value="600">10 min</option>
                  </select>
                  <span id="wr_countdown" class="wr-countdown" aria-live="polite"></span>
               </div>
               <div class="wr-toolbar-group">
                  <div id="wr_states" class="entity-chips wr-states" role="group" aria-label="Filter by outcome"></div>
                  <input type="search" id="wr_search" class="form-control form-control-sm wr-search"
                     placeholder="Search runs…" aria-label="Search workflow runs" autocomplete="off" spellcheck="false">
               </div>
            </div>
            <div id="wr_note" class="repo-filter-note" hidden></div>
            <div id="wr_table" class="wr-table-wrapper">
               <div class="text-muted py-3 text-center">
                  <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading workflow runs…
               </div>
            </div>
            <div class="wr-pager">
               <span id="wr_summary" class="wr-summary"></span>
               <nav id="wr_pages" aria-label="Workflow runs pages"></nav>
               <label class="wr-label">Rows per page
                  <select id="wr_page_size" class="form-select form-select-sm wr-select" aria-label="Rows per page">
                     <option value="10">10</option>
                     <option value="25" selected>25</option>
                     <option value="50">50</option>
                     <option value="100">100</option>
                     <option value="all">All</option>
                  </select>
               </label>
            </div>
         </div>
      </div>
<?php
require_once "partials/dashboard-foot.php";

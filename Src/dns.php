<?php
$pageTitle = "DNS Records";
$pageIcon  = "bi-hdd-network";
require_once "partials/standalone-head.php";
?>

   <div class="dashboard-layout">
      <div class="dashboard-container">

         <div class="full-width-section">
            <div class="section-header">
               <i class="bi bi-funnel me-2"></i>Filters
            </div>
            <div class="section-content">
               <div class="dns-filters">
                  <div class="dns-filter-group">
                     <div class="dns-filter-group-header">
                        <span><i class="bi bi-globe me-1"></i>Source Domain</span>
                        <span>
                           <button type="button" class="btn btn-sm btn-outline-light" id="dns_domains_all">All</button>
                           <button type="button" class="btn btn-sm btn-outline-light" id="dns_domains_none">None</button>
                        </span>
                     </div>
                     <div class="dns-filter-list" id="dns_filter_domains">
                        <div class="text-muted small px-2">Loading…</div>
                     </div>
                  </div>
                  <div class="dns-filter-group">
                     <div class="dns-filter-group-header">
                        <span><i class="bi bi-tag me-1"></i>Record Type</span>
                        <span>
                           <button type="button" class="btn btn-sm btn-outline-light" id="dns_types_all">All</button>
                           <button type="button" class="btn btn-sm btn-outline-light" id="dns_types_none">None</button>
                        </span>
                     </div>
                     <div class="dns-filter-list" id="dns_filter_types">
                        <div class="text-muted small px-2">Loading…</div>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <div class="full-width-section">
            <div class="section-header">
               <i class="bi bi-hdd-network me-2"></i>DNS Zone Records <span id="counter_dns_records"
                  class="badge rounded-pill"></span>
            </div>
            <div class="section-content">
               <div id="dns_records_table"></div>
            </div>
         </div>

      </div>
   </div>

   <?php include_once __DIR__ . '/footer.php'; ?>

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha256-CDOy6cOibCWEdsRiZuaHf8dSGGJRYuBGC+mjoJimHGw=" crossorigin="anonymous"></script>
   <script src="https://cdn.jsdelivr.net/npm/gridjs@6.2.0/dist/gridjs.umd.js"
      integrity="sha384-y62I+ZvjNRolkugL/AMpUZykqrL6oqYxBruObmlDAhDpmapM0s+xgvVK+wGVpza0"
      crossorigin="anonymous"></script>
   <script type="module" src="static/dns.js?<?php echo filemtime("static/dns.js"); ?>"></script>
</body>

</html>

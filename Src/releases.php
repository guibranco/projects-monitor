<?php
$pageTitle = "Releases";
$pageIcon  = "bi-tag";
require_once "partials/standalone-head.php";
?>

   <div class="dashboard-layout">
      <div class="dashboard-container">

         <div class="full-width-section">
            <div class="section-header">
               <i class="bi bi-tag me-2"></i>All Releases <span id="counter_releases_table"
                  class="badge rounded-pill"></span>
            </div>
            <div class="section-content">
               <div id="releases_table"></div>
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
   <script type="module" src="static/releases.js?<?php echo filemtime("static/releases.js"); ?>"></script>
</body>

</html>

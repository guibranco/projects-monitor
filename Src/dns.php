<?php
require_once 'session.php';
require_once 'vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Configuration;

if (!isset($_SESSION['last_activity']) || time() - $_SESSION['last_activity'] > 1800) {
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
$username = $_SESSION['username'] ?? '';
$configuration = new Configuration();
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <title>Projects Monitor | DNS Records</title>
   <meta charset="utf-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
      integrity="sha512-dPXYcDub/aeb08c63jRq/k6GaKccl256JQy/AnOq7CAnEZ9FzSL9wSbcZkMp4R26vBsMLFYH4kQ67/bbV8XaCQ=="
      crossorigin="anonymous">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridjs@6.2.0/dist/theme/mermaid.min.css"
      integrity="sha384-i8iPOOXHyYKlqvjJjbORq7m/VrfUhgupTg3IZvtXz8M7c0CiTPUUhM5gdjiQiGbv"
      crossorigin="anonymous">
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
            <i class="bi bi-hdd-network fs-5"></i>
            <span class="fw-bold">DNS Records</span>
         </span>
         <div class="d-flex align-items-center gap-3">
            <a href="index.php" class="btn btn-sm btn-nav-action">
               <i class="bi bi-arrow-left"></i>
               <span class="ms-1">Back to Dashboard</span>
            </a>
            <span class="navbar-user d-none d-lg-flex align-items-center gap-2">
               <i class="bi bi-person-circle"></i>
               <span><?php echo htmlspecialchars($username); ?></span>
            </span>
            <a href="logout.php" class="btn btn-sm btn-danger">
               <i class="bi bi-box-arrow-right"></i>
               <span class="d-none d-md-inline ms-1">Logout</span>
            </a>
         </div>
      </div>
   </nav>

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

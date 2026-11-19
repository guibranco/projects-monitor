<?php
/*
 * Shared top of every dashboard page: session check, <head>, the navbar
 * with the page tabs, the freshness indicator and the Ctrl+K palette
 * button, and the settings panel. Set $pageTitle and $activePage
 * (overview, pipeline, errors, github, ops or stats) before including it.
 */

require_once "session.php";
require_once "vendor/autoload.php";

use GuiBranco\ProjectsMonitor\Library\DashboardPage;

$lastActivity = ($_SESSION["last_activity"] ?? 0);
if ((time() - $lastActivity) > 1800 || isset($_SESSION["user_id"]) === false) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    exit;
}

$_SESSION["last_activity"] = time();
$username = htmlspecialchars(($_SESSION["username"] ?? ""), ENT_QUOTES);

// Release the session lock: nothing below writes to $_SESSION, so the
// page's parallel API calls don't have to wait for it to finish.
session_write_close();

$dashboardPage = new DashboardPage(($activePage ?? "overview"), ($pageTitle ?? "Dashboard"));
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <title>Projects Monitor | <?php echo $dashboardPage->getTitle(); ?></title>
   <meta charset="utf-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="icon" type="image/svg+xml" href="static/brand/favicon.svg">
   <link rel="icon" type="image/png" sizes="32x32" href="static/brand/favicon-32.png">
   <link rel="apple-touch-icon" href="static/brand/apple-touch-icon.png">
   <meta name="theme-color" content="#0d1117">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
      integrity="sha512-dPXYcDub/aeb08c63jRq/k6GaKccl256JQy/AnOq7CAnEZ9FzSL9wSbcZkMp4R26vBsMLFYH4kQ67/bbV8XaCQ=="
      crossorigin="anonymous">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridjs@6.2.0/dist/theme/mermaid.min.css"
      integrity="sha384-i8iPOOXHyYKlqvjJjbORq7m/VrfUhgupTg3IZvtXz8M7c0CiTPUUhM5gdjiQiGbv"
      crossorigin="anonymous">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-confirm@3.3.4/css/jquery-confirm.min.css"
      integrity="sha384-v1a2HgwbebXbthfsXlkngJva8FG00HYE6Z/U0Apw3A6z6Q4l1gYyCAFPQ7T7A5h8"
      crossorigin="anonymous">
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
   <link rel="stylesheet" href="static/styles.css?<?php echo filemtime("static/styles.css"); ?>">
</head>

<body data-page="<?php echo $dashboardPage->getActivePage(); ?>">
   <script src="static/versionCheck.js?<?php echo filemtime("static/versionCheck.js"); ?>"></script>

   <div id="toast-container"></div>

   <nav class="navbar navbar-dark fixed-top" id="main-navbar">
      <div class="container-fluid px-4 gap-2">
         <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <img src="static/brand/logo.svg" alt="" width="28" height="28" class="brand-logo">
            <span class="fw-bold d-none d-sm-inline">Projects Monitor</span>
         </a>
         <ul class="page-tabs nav me-auto">
            <?php echo $dashboardPage->renderTabs(); ?>
         </ul>
         <div class="d-flex align-items-center gap-2">
            <div class="repo-filter" role="search">
               <i class="bi bi-funnel" aria-hidden="true"></i>
               <input type="search" id="repo_filter" class="repo-filter-input" placeholder="Filter repo…"
                  aria-label="Filter every page by repository (press / to focus)" autocomplete="off" spellcheck="false"
                  title="Filter every page by repository: owner/name or part of it (press / to focus)">
               <button type="button" id="repo_filter_clear" class="repo-filter-clear" hidden
                  aria-label="Clear the repo filter" title="Clear the repo filter"><i class="bi bi-x"></i></button>
            </div>
            <button type="button" id="data_freshness" class="freshness-indicator d-none d-md-inline-flex"
               title="Click to re-check every source now">
               <span class="status-dot" aria-hidden="true"></span>Loading…
            </button>
            <button type="button" class="btn btn-sm btn-nav-action" id="command_palette_button"
               title="Jump to a page or section (Ctrl+K)">
               <i class="bi bi-search"></i><span class="d-none d-xl-inline ms-1">Ctrl+K</span>
            </button>
            <a href="logout.php" class="btn btn-sm btn-danger" title="Logout">
               <i class="bi bi-box-arrow-right"></i>
            </a>
            <button class="btn btn-sm btn-nav-settings" type="button" data-bs-toggle="offcanvas"
               data-bs-target="#settingsPanel" aria-controls="settingsPanel" title="Settings">
               <i class="bi bi-sliders"></i>
            </button>
         </div>
      </div>
   </nav>

   <div class="offcanvas offcanvas-end settings-offcanvas" tabindex="-1" id="settingsPanel"
      aria-labelledby="settingsPanelLabel">
      <div class="offcanvas-header">
         <h5 class="offcanvas-title" id="settingsPanelLabel">
            <i class="bi bi-sliders me-2"></i>Options
         </h5>
         <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
            aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
         <p class="mb-3 settings-welcome">
            <i class="bi bi-person-circle me-1"></i>
            Welcome, <strong><?php echo $username; ?></strong>!
         </p>
         <hr class="settings-divider">
         <h6 class="settings-section-label">Feed</h6>
         <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="feedToggle">
            <label class="form-check-label" for="feedToggle">
               <i class="bi bi-rss me-1"></i> Show All / Only mine
            </label>
         </div>
         <hr class="settings-divider">
         <h6 class="settings-section-label">Sections</h6>
         <div class="d-grid gap-2">
            <button type="button" class="btn btn-outline-light btn-sm" onclick="collapseAllSections()">
               <i class="bi bi-chevron-bar-up me-1"></i> Collapse All Sections
            </button>
            <button type="button" class="btn btn-outline-light btn-sm" onclick="expandAllSections()">
               <i class="bi bi-chevron-bar-down me-1"></i> Expand All Sections
            </button>
         </div>
      </div>
   </div>

   <div class="dashboard-layout">
      <div class="dashboard-container">
         <div class="page-heading">
            <h1 class="page-heading-title"><?php echo $dashboardPage->getTitle(); ?></h1>
            <span id="page_updated" class="page-updated">Loading…</span>
         </div>

<?php
/*
 * Shared top of the standalone pages (Releases, DNS Records, Applications):
 * session check, <head> and the navbar with "Back to Dashboard" and Logout.
 * Set $pageTitle and $pageIcon (a Bootstrap icon class) before including it.
 */

require_once __DIR__.'/../session.php';

$lastActivity = ($_SESSION['last_activity'] ?? 0);
if ((time() - $lastActivity) > 1800 || isset($_SESSION['user_id']) === false) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    exit;
}

$_SESSION['last_activity'] = time();
$username = ($_SESSION['username'] ?? '');

// Release the session lock: nothing below writes to $_SESSION, so the
// dashboard's parallel API calls don't have to wait for this page to finish.
session_write_close();

$standaloneTitle = htmlspecialchars(($pageTitle ?? ''), ENT_QUOTES);
$standaloneIcon = htmlspecialchars(($pageIcon ?? 'bi-window'), ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <title>Projects Monitor | <?php echo $standaloneTitle; ?></title>
   <meta charset="utf-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
      integrity="sha512-dPXYcDub/aeb08c63jRq/k6GaKccl256JQy/AnOq7CAnEZ9FzSL9wSbcZkMp4R26vBsMLFYH4kQ67/bbV8XaCQ=="
      crossorigin="anonymous">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridjs@6.2.0/dist/theme/mermaid.min.css"
      integrity="sha384-i8iPOOXHyYKlqvjJjbORq7m/VrfUhgupTg3IZvtXz8M7c0CiTPUUhM5gdjiQiGbv"
      crossorigin="anonymous">
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
   <link rel="stylesheet" href="static/styles.css?<?php echo filemtime(__DIR__."/../static/styles.css"); ?>">
</head>

<body>
   <div id="toast-container"></div>

   <nav class="navbar navbar-dark fixed-top" id="main-navbar">
      <div class="container-fluid px-4">
         <span class="navbar-brand d-flex align-items-center gap-2">
            <i class="bi <?php echo $standaloneIcon; ?> fs-5"></i>
            <span class="fw-bold"><?php echo $standaloneTitle; ?></span>
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


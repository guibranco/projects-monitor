<?php

require_once 'vendor/autoload.php';
    <div id="issue_templates"></div>

use GuiBranco\ProjectsMonitor\Library\Configuration;

$configuration = new Configuration();
?>

<!DOCTYPE html>
    <div id="issue_templates_status"></div>
<html lang="en">

<head>
  <title>Projects Monitor</title>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="static/styles.css?<?php echo filemtime("static/styles.css"); ?>">
  <script src="https://www.gstatic.com/charts/loader.js"></script>
  <script src="static/scripts.js?<?php echo filemtime("static/scripts.js"); ?>"></script>
  <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
  <script>
    window.OneSignalDeferred = window.OneSignalDeferred || [];
    OneSignalDeferred.push(function (OneSignal) {
      OneSignal.init({
        appId: "90c57079-9ef2-4719-bddf-361e6510de17",
        googleProjectNumber: "256841700684"
      });
    });
  </script>
</head>

<body>
  <div id="webhooks_statistics_github" style="width: 100%; height: 400px;"></div>
  <div style="clear:both;"></div>
  <div style="width: 50%; float: left;">
    <div id="gauge_chart_1" class="gauge"></div>
    <div id="gauge_chart_5" class="gauge"></div>
    <div id="gauge_chart_6" class="gauge"></div>
    <div id="gauge_chart_8" class="gauge"></div>
    <div id="gauge_chart_9" class="gauge"></div>
    <div id="gauge_chart_2" class="gauge"></div>
    <div id="gauge_chart_4" class="gauge"></div>
    <div id="gauge_chart_3" class="gauge"></div>
    <div id="gauge_chart_7" class="gauge"></div>
    <div id="gauge_chart_installation_repositories" class="gauge"></div>
  </div>
  <div style="width: 50%; float: left;">
    <div style="width: 100%;">
      <div style="width:465px; float: left;">
        <img id="gh_stats" style="width: 465px;" alt="GH Stats" src="" />
        <img id="gh_streak" style="width: 465px;" alt="GH Streak" src="" />
      </div>
      <div style="width: calc(100%-465px); float: left;">
        <a href="https://wakatime.com/@6be975b7-7258-4475-bc73-9c0fc554430e" target='_blank' rel='noopener noreferrer'>
          <img src="https://wakatime.com/badge/user/6be975b7-7258-4475-bc73-9c0fc554430e.svg?style=for-the-badge" />
        </a>
        <div id="postman"></div>
      </div>
    </div>
  </div>
  <div style="clear:both;"></div>
  <div id="queues" style="width: 50%; height: 600px; float: left; background-color: white;"></div>
  <div style="width: 50%; height: 600px; float: left; background-color: white;">
    <div id="accounts_usage" style="width: 100%; height: 200px;"></div>
    <div id="latest_release" style="width: 100%; height: 200px; "></div>
    <div id="hooks_last_check" style="width: 100%; height: 50px;"></div>
    <div id="error_log_files" style="width: 100%; height: 150px;"></div>
  </div>
  <div style="clear:both;"></div>
  <div style="width: 100%; height: 300px; float: left; background-color: white;">
    <div id="pie_chart_1" style="height: 300px; float: left;"></div>
    <div id="pie_chart_2" style="height: 300px; float: left;"></div>
  </div>
  <div style="clear:both;"></div>
  <div id="messages_grouped" style="width: 50%; height: 500px; float: left; background-color: white;"></div>
  <div id="error_log_messages" style="width: 50%; height: 500px; float: left; background-color: white;"></div>
  <div style="clear:both;"></div>
  <div class="topping">Workflow Runs</div>
  <div id="workflow_runs" style="width: 100%; float: left; background-color: white;"></div>
  <div style="clear:both;"></div>
  <div style="width: 50%; float: left; background-color: white;">
    <div class="topping">Issues Blocked</div>
    <div id="blocked"></div>
    <div class="topping">Pull Requests Blocked</div>
    <div id="pull_requests_blocked"></div>    
    <div class="topping">Feed</div>
    <div id="feed"></div>
    <div class="topping">WireGuard</div>
    <div id="wireguard"></div>
    <div class="topping">HealthChecksIo</div>
    <div id="healthchecksio"></div>
    <div class="topping">UpTimeRobot</div>
    <div id="uptimerobot"></div>
    <div class="topping">Domains</div>
    <div id="domains"></div>
    <div class="topping">Pull Requests Authored</div>
    <div id="pull_requests_authored"></div>
    <div class="topping">Issues Authored</div>
    <div id="issues_authored"></div>
    <div class="topping">Repositories</div>
    <div id="repositories"></div>
    <div class="topping">GitHub API Usage</div>
    <div id="api_usage"></div>
    <div class="topping">Installed Repositories</div>
    <div id="installed_repositories"></div>
  </div>
  <div style="width: 50%; float: left; background-color: white;">
    <div class="topping">Issues Awaiting Triage</div>
    <div id="triage"></div>
    <div class="topping">Pull Requests Awaiting Triage</div>
    <div id="pull_requests_triage"></div>
    <div class="topping">Pull Requests</div>
    <div id="pull_requests_latest"></div>
    <div class="topping">Issues Assigned</div>
    <div id="assigned"></div>
    <div class="topping">Issues WIP</div>
    <div id="wip"></div>    
    <div class="topping">Issues Bug</div>
    <div id="bug"></div>
    <div class="topping">Issues</div>
    <div id="issues"></div>
    <div class="topping">Cronjobs</div>
    <div id="cronjobs"></div>
    <div class="topping">Senders</div>
    <div id="senders"></div>
    <div class="topping">AppVeyor</div>
    <div id="appveyor"></div>
  </div>
  <div style="clear:both;"></div>
  <div id="webhooks_statistics" style="width: 100%; height: 400px;"></div>
  <div style="clear:both;"></div>
</body>

</html>

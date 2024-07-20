<?php
ini_set("default_charset", "UTF-8");
ini_set("date.timezone", "Europe/Dublin");
mb_internal_encoding("UTF-8");
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <title>Projects Monitor</title>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background: rgb(13, 17, 23)
    }

    .gauge {
      width: 20%;
      height: 200px;
      float: left;
      background-color: white;
    }

    code {
      background-color: #000000;
      color: #FFFFFF;
      padding: 0 7px;
      border-radius: 24px;
      border: 1px solid #000;
      line-height: 21px;
      text-wrap: nowrap;
    }
  </style>
  <script src="https://www.gstatic.com/charts/loader.js"></script>
  <script src="static/scripts.js?<?php echo filemtime("static/scripts.js"); ?>"></script>
  <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
  <script>
      window.OneSignalDeferred = window.OneSignalDeferred || [];
      OneSignalDeferred.push(function(OneSignal) {
        OneSignal.init({
          appId: "90c57079-9ef2-4719-bddf-361e6510de17",
          googleProjectNumber: "256841700684"
        });
      });
   </script>
</head>

<body>
  <div id="webhooks_statistics_github" style="width: 100%; height: 500px;"></div>
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
    <div id="gauge_chart_10" class="gauge"></div>
  </div>
  <div style="width: 50%; float: left;">
    <div style="width: 100%;">
      <img id="gh_stats" style="width: 465px; float: left;" alt="GH Stats" src="" />
      <img id="gh_streak" style="width: 465px; float: left;" alt="GH Streak" src="" />
    </div>
    <div style="width: 100%;">
      <div id="webhooks_statistics" style="width: 100%; height: 200px;"></div>
    </div>
  </div>
  <div style="clear:both;"></div>
  <div style="width: 30%; height: 600px; float: left; background-color: white;">
    <div id="pie_chart_1" style="width: 100%; height: 300px;"></div>
    <div id="pie_chart_2" style="width: 100%; height: 300px;"></div>
  </div>
  <div id="queues" style="width: 30%; height: 600px; float: left; background-color: white;"></div>
  <div style="width: 40%; height: 600px; float: left; background-color: white;">
    <div id="accounts_usage" style="width: 100%; height: 200px;"></div>
    <div id="latest_release" style="width: 100%; height: 250px;"></div>
    <div id="error_log_files" style="width: 100%; height: 150px;"></div>
  </div>
  <div style="clear:both;"></div>
  <div id="repositories" style="width: 50%; height: 500px; float: left; background-color: white;"></div>
  <div id="error_log_messages" style="width: 50%; height: 500px; float: left; background-color: white;"></div>
  <div style="clear:both;"></div>
  <div id="workflow_runs" style="width: 100%; float: left; background-color: white;"></div>
  <div style="clear:both;"></div>
  <div style="width: 50%; float: left; background-color: white;">
    <div id="feed"></div>
    <div id="healthchecksio"></div>
    <div id="uptimerobot"></div>
    <div id="domains"></div>
    <div id="blocked"></div>
    <div id="pull_requests_blocked"></div>
    <div id="pull_requests_authored"></div>
    <div id="issues_authored"></div>
  </div>
  <div style="width: 50%; float: left; background-color: white;">
    <div id="pull_requests_latest"></div>
    <div id="assigned"></div>
    <div id="wip"></div>
    <div id="triage"></div>
    <div id="bug"></div>
    <div id="issues"></div>  
    <div id="cronjobs"></div>
    <div id="bots"></div>
  </div>
  <div style="clear:both;"></div>
  <div id="messages" style="width: 100%; float: left; background-color: white;"></div>
  <div style="clear:both;"></div>
</body>

</html>

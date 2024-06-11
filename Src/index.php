<?php

require_once 'vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Configuration;

$config = new Configuration();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <title>Projects Monitor</title>
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
  <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
  <script type="text/javascript" src="static/scripts.js?<?php echo filemtime("static/scripts.js"); ?>"></script>
</head>

<body>
  <div id="line_chart" style="width: 100%; height: 400px; float: left;"></div>
  <div style="clear:both;"></div>
  <div style="width: 50%; float: left;">
    <div id="gauge_chart_1" class="gauge"></div>
    <div id="gauge_chart_5" class="gauge"></div>
    <div id="gauge_chart_6" class="gauge"></div>
    <div id="gauge_chart_8" class="gauge"></div>
    <div id="gauge_chart_2" class="gauge"></div>
    <div id="gauge_chart_4" class="gauge"></div>
    <div id="gauge_chart_3" class="gauge"></div>
    <div id="gauge_chart_7" class="gauge"></div>
  </div>
  <div style="width: 50%; float: left;">
    <img id="gh_stats" style="width: 465px; float: left;" alt="GH Stats" src="" />
    <img id="gh_streak" style="width: 465px; float: left;" alt="GH Streak" src="" />
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
    <div id="errorLogFiles" style="width: 100%; height: 150px;"></div>
  </div>
  <div style="clear:both;"></div>
  <div id="repositories" style="width: 50%; height: 500px; float: left; background-color: white;"></div>
  <div id="errorLogMessages" style="width: 50%; height: 500px; float: left; background-color: white;"></div>
  <div style="clear:both;"></div>
  <div style="width: 50%; float: left; background-color: white;">
    <div id="feed"></div>
    <div id="healthchecksio"></div>
    <div id="uptimerobot"></div>
  </div>
  <div style="width: 50%; float: left; background-color: white;">
    <div id="workflow_runs"></div>
    <div id="pull_requests"></div>
    <div id="bug"></div>
    <div id="triage"></div>
    <div id="wip"></div>
    <div id="issues"></div>
    <div id="cronjobs"></div>    
  </div>
  <div style="clear:both;"></div>
  <div id="messages" style="width: 100%; float: left; background-color: white;"></div>
  <div style="clear:both;"></div>
</body>

</html>

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
  </style>
  <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
  <script type="text/javascript" src="static/scripts.js?20240605212000"></script>
</head>

<body>
  <div id="line_chart" style="width: 73%; height: 450px; float: left;"></div>
  <img id="gh_stats" alt="" src="" />
  <img id="gh_streak" alt="" src="" />
  <div style="clear:both;"></div>
  <div id="gauge_chart_1" style="width: 15%; height: 300px; float: left;background-color: white;"></div>
  <div id="gauge_chart_2" style="width: 15%; height: 300px; float: left;background-color: white;"></div>
  <div id="gauge_chart_3" style="width: 15%; height: 300px; float: left;background-color: white;"></div>
  <div id="gauge_chart_4" style="width: 15%; height: 300px; float: left;background-color: white;"></div>
  <div id="gauge_chart_5" style="width: 15%; height: 300px; float: left;background-color: white;"></div>
  <div id="gauge_chart_6" style="width: 15%; height: 300px; float: left;background-color: white;"></div>
  <div style="clear:both;"></div>
  <div id="pie_chart_1" style="width: 30%; height: 300px; float: left;"></div>
  <div id="pie_chart_2" style="width: 30%; height: 300px; float: left;"></div>
  <div id="queues" style="width: 30%; height: 300px; float: left;background-color: white;"></div>
  <div style="clear:both;"></div>
  <div id="repositories" style="width: 50%; height: 500px; float: left;background-color: white;"></div>
  <div style="width: 50%; height: 500px; float: left;background-color: white;">
    <div id="errorLogFiles" style="width: 100%; height: 100px"></div>
    <div id="errorLogMessages" style="width: 100%; height: 400px"></div>
  </div>
  <div style="clear:both;"></div>
  <div id="feed" style="width: 50%; float: left;background-color: white;"></div>
  <div style="width: 50%; float: left;background-color: white;">
    <div id="latest_release"></div>
    <div id="pull_requests"></div>
    <div id="bug"></div>
    <div id="triage"></div>
    <div id="wip"></div>
    <div id="issues"></div>
    <div id="healthchecksio"></div>
    <div id="uptimerobot"></div>
    <div id="cronjobs"></div>
  </div>
  <div style="clear:both;"></div>
  <div id="messages" style="width: 100%; float: left;background-color: white;"></div>
  <div style="clear:both;"></div>
</body>

</html>
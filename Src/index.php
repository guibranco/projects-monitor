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
  <script type="text/javascript" src="static/scripts.js"></script>
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
  <div style="width: 50%; float: left;background-color: white;">
    <div id="pull_requests"></div>
    <div id="issues"></div>
  </div>
  <div id="feed" style="width: 50%; float: left;background-color: white;"></div>
  <div id="messages" style="width: 50%; float: left;background-color: white;"></div>
  <div style="clear:both;"></div>
</body>
<script>
  var ghStats = document.getElementById("gh_stats");
  ghStats.src = "https://github-readme-stats-guibranco.vercel.app/api" +
    "?username=guibranco&line_height=28&card_width=490&hide_title=true&hide_border=true" +
    "&show_icons=true&theme=chartreuse-dark&icon_color=7FFF00&include_all_commits=true" +
    "&count_private=true&show=reviews,discussions_started&count_private=true";

  var ghStreak = document.getElementById("gh_streak");
  ghStreak.src = "https://github-readme-streak-stats-guibranco.vercel.app/" +
    "?user=guibranco&theme=github-green-purple&fire=FF6600";
</script>

</html>
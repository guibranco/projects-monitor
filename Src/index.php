<?php

require_once("config.php");

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
  <script type="text/javascript">
    google.charts.load('current', { 'packages': ['corechart', 'table', 'gauge'] });
    google.charts.setOnLoadCallback(drawChart);


    function loadData() {
      var xhr = new XMLHttpRequest();
      xhr.open("GET", "https://guilhermebranco.com.br/webhooks/api.php", false);
      xhr.send();
      return JSON.parse(xhr.responseText);
    }

    function loadQueueStats() {
      var xhr = new XMLHttpRequest();
      xhr.open("GET", "https://guilhermebranco.com.br/webhooks/queue.php", false);
      xhr.send();
      return JSON.parse(xhr.responseText);
    }

    function drawChart() {
      var response = loadData();
      var dataWebhooks = google.visualization.arrayToDataTable(response["webhooks"]);
      var dataEvents = google.visualization.arrayToDataTable(response["events"]);
      var dataFeed = google.visualization.arrayToDataTable(response["feed"]);
      var dataTotal = google.visualization.arrayToDataTable([["Hits", "Total"], ["Webhooks", response["total"]]]);
      var dataFailed = google.visualization.arrayToDataTable([["Hits", "Failed"], ["Failed", response["failed"]]]);

      var optionsWebhooks = {
        title: 'GitHub webhooks by date',
        legend: { position: 'none' },
        colors: ["#0c5922"],
        pointSize: 7,
        hAxis: {
          title: 'Webhooks',
          textStyle: {
            fontSize: 10
          }
        }
      };

      var optionsEvents = {
        title: 'GitHub events by type',
        legend: { position: 'right' }
      };

      var optionsFeed = {
        title: 'GitHub feed',
        legend: { position: 'none' },
        showRowNumber: true,
        width: '100%',
        height: '100%'
      };

      var optionsTotal = {
        legend: { position: 'none' },
        showRowNumber: true,
        width: '100%',
        height: '100%',
        min: 0,
        max: 120000,
        greenFrom: 0, greenTo: 40000,
        yellowFrom: 40000, yellowTo: 80000,
        redFrom: 80000, redTo: 120000
      };

      var optionsFailed = {
        legend: { position: 'none' },
        showRowNumber: true,
        width: '100%',
        height: '100%',
        min: 0,
        max: 1000,
        greenFrom: 0, greenTo: 50,
        yellowFrom: 50, yellowTo: 100,
        redFrom: 100, redTo: 1000
      };

      var lineChart = new google.visualization.LineChart(document.getElementById('line_chart'));
      lineChart.draw(dataWebhooks, optionsWebhooks);
      var pieChart = new google.visualization.PieChart(document.getElementById('pie_chart'));
      pieChart.draw(dataEvents, optionsEvents);
      var feed = new google.visualization.Table(document.getElementById('feed'));
      feed.draw(dataFeed, optionsFeed);
      var guageChart = new google.visualization.Gauge(document.getElementById('guage_chart'));
      guageChart.draw(dataTotal, optionsTotal);
      var guageChart2 = new google.visualization.Gauge(document.getElementById('guage_chart_2'));
      guageChart2.draw(dataFailed, optionsFailed);
      showQueues();
      setTimeout(drawChart, 30000);
    }

    function showQueues() {
      var response = loadQueueStats();
      var dataQueues = google.visualization.arrayToDataTable(response["queues"]);

      var optionsQueues = {
        title: 'Messages',
        legend: { position: 'none' },
        showRowNumber: true,
        width: '100%',
        height: '100%'
      };

      var queues = new google.visualization.Table(document.getElementById('queues'));
      queues.draw(dataQueues, optionsQueues);
    }
  </script>
</head>

<body>
  <div id="line_chart" style="width: 73%; height: 450px; float: left;"></div>
  <img id="gh_stats" alt="" src="" />
  <img id="gh_streak" alt="" src="" />
  <div style="clear:both;"></div>
  <div id="pie_chart" style="width: 35%; height: 300px; float: left;"></div>
  <div id="guage_chart" style="width: 20%; height: 300px; float: left;background-color: rgb(13, 17, 23);"></div>
  <div id="guage_chart_2" style="width: 20%; height: 300px; float: left;background-color: rgb(13, 17, 23);"></div>
  <div id="queues" style="width:25%; padding-top: 10px; float: left;"></div>
  <div style="clear:both;"></div>
  <div id="feed"></div>
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
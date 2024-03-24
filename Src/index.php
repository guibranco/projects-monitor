<?php

require_once 'vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Configuration;

$config = new Configuration();
$hostPrefix = strpos($_SERVER['HTTP_HOST'], "localhost") >= 0 ? $_SERVER['HTTP_POST'] : "https://guilhermebranco.com.br/projects-monitor";
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

    function loadMessages() {
      var xhr = new XMLHttpRequest();
      xhr.open("GET", "<?php echo $hostPrefix; ?>/api/v1/messages", false);
      xhr.send();
      return JSON.parse(xhr.responseText);
    }

    function loadQueueStats() {
      var xhr = new XMLHttpRequest();
      xhr.open("GET", "<?php echo $hostPrefix; ?>/api/v1/queues", false);
      xhr.send();
      return JSON.parse(xhr.responseText);
    }

    function drawChart() {
      setTimeout(showChartsAndFeed, 1000);
      setTimeout(showQueues, 1000);
      setTimeout(showMessages, 1000);
      setTimeout(drawChart, 30000);
    }

    function showChartsAndFeed() {
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
      var guageChart1 = new google.visualization.Gauge(document.getElementById('guage_chart_1'));
      guageChart1.draw(dataTotal, optionsTotal);
      var guageChart2 = new google.visualization.Gauge(document.getElementById('guage_chart_2'));
      guageChart2.draw(dataFailed, optionsFailed);
    }

    function showQueues() {
      var response = loadQueueStats();
      var dataQueues = google.visualization.arrayToDataTable(response);

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

    function showMessages() {
      var response = loadMessages();
      var dataMessages = google.visualization.arrayToDataTable(response["messages"]);
      var dataTotal = google.visualization.arrayToDataTable([["Items", "Total"], ["Messages", response["total"]]]);

      var optionsMessages = {
        title: 'Messages',
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
        max: 1000,
        greenFrom: 0, greenTo: 250,
        yellowFrom: 250, yellowTo: 500,
        redFrom: 500, redTo: 1000
      };

      var messages = new google.visualization.Table(document.getElementById('messages'));
      messages.draw(dataMessages, optionsMessages);
      var guageChart3 = new google.visualization.Gauge(document.getElementById('guage_chart_3'));
      guageChart3.draw(dataTotal, optionsTotal);
    }
  </script>
</head>

<body>
  <div id="line_chart" style="width: 73%; height: 450px; float: left;"></div>
  <img id="gh_stats" alt="" src="" />
  <img id="gh_streak" alt="" src="" />
  <div style="clear:both;"></div>
  <div id="guage_chart_1" style="width: 20%; height: 300px; float: left;background-color: white;"></div>
  <div id="guage_chart_2" style="width: 20%; height: 300px; float: left;background-color: white;"></div>
  <div id="guage_chart_3" style="width: 20%; height: 300px; float: left;background-color: white;"></div>
  <div id="pie_chart" style="width: 33%; height: 300px; float: left;"></div>
  <div style="clear:both;"></div>
  <div id="queues"></div>
  <div id="feed"></div>
  <div id="messages"></div>
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
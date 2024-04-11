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
  <script type="text/javascript">
    google.charts.load('current', { 'packages': ['corechart', 'table', 'gauge'] });
    google.charts.setOnLoadCallback(drawChart);
    function load(url, callback) {
      var xhr = new XMLHttpRequest();
      xhr.open("GET", url, true);
      xhr.onreadystatechange = function () {
        if (this.readyState === 4 && this.status === 200) {
          callback(JSON.parse(this.responseText));
        }
      };
      xhr.send();
    }

    var showPreset = true;

    function preset() {
      showWebhook(JSON.parse('{"events":[["Event","Hits"]],"failed":0,"feed":[["Sequence","Date","Event","Action","Repository"]],"repositories":[["Repository","Hits"]],"total":0,"webhooks":[["Date","Hits"], ["01/01", 0]]}'));
      showMessages(JSON.parse('{"total":0,"byApplications":[["Application","Messages"]],"messages":[["Id","Application","Message","Created At"]]}'));
      showQueues(JSON.parse('{"queues":[["Server","Queue","Messages"]],"total":0}'));
      showGitHub(JSON.parse('{"issues":0,"pull_requests":0}'));
    }

    function loadAll() {
      load("https://guilhermebranco.com.br/webhooks/api.php", showWebhook);
      load("api/v1/messages", showMessages);
      load("api/v1/queues", showQueues);
      load("api/v1/github", showGitHub);
    }

    function drawChart() {
      if (showPreset) {
        preset();
        showPreset = false;
      }
      loadAll();
      setTimeout(drawChart, 30000);
    }

    function showWebhook(response) {
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
      var pieChart1 = new google.visualization.PieChart(document.getElementById('pie_chart_1'));
      pieChart1.draw(dataEvents, optionsEvents);
      var feed = new google.visualization.Table(document.getElementById('feed'));
      feed.draw(dataFeed, optionsFeed);
      var gaugeChart1 = new google.visualization.Gauge(document.getElementById('gauge_chart_1'));
      gaugeChart1.draw(dataTotal, optionsTotal);
      var gaugeChart2 = new google.visualization.Gauge(document.getElementById('gauge_chart_2'));
      gaugeChart2.draw(dataFailed, optionsFailed);
    }

    function showMessages(response) {
      var dataMessages = google.visualization.arrayToDataTable(response["messages"]);
      var dataTotal = google.visualization.arrayToDataTable([["Items", "Total"], ["Errors", response["total"]]]);
      var dataByApplications = google.visualization.arrayToDataTable(response["byApplications"]);

      var optionsMessages = {
        title: 'Errors',
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

      var optionsByApplications = {
        title: 'Messages by applications',
        legend: { position: 'right' }
      };

      var messages = new google.visualization.Table(document.getElementById('messages'));
      messages.draw(dataMessages, optionsMessages);
      var gaugeChart3 = new google.visualization.Gauge(document.getElementById('gauge_chart_3'));
      gaugeChart3.draw(dataTotal, optionsTotal);
      var pieChart2 = new google.visualization.PieChart(document.getElementById('pie_chart_2'));
      pieChart2.draw(dataByApplications, optionsByApplications);
    }

    function showQueues(response) {
      var dataTotal = google.visualization.arrayToDataTable([["Items", "Total"], ["RabbitMQ", response["total"]]]);
      var dataQueues = google.visualization.arrayToDataTable(response["queues"]);

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

      var optionsQueues = {
        title: 'Errors',
        legend: { position: 'none' },
        showRowNumber: true,
        width: '100%',
        height: '100%'
      };

      var queues = new google.visualization.Table(document.getElementById('queues'));
      queues.draw(dataQueues, optionsQueues);
      var gaugeChart4 = new google.visualization.Gauge(document.getElementById('gauge_chart_4'));
      gaugeChart4.draw(dataTotal, optionsTotal);
    }

    function showGitHub(response) {
      var dataIssues = google.visualization.arrayToDataTable([["Hits", "Total"], ["Issues", response["issues"]]]);
      var dataPullRequests = google.visualization.arrayToDataTable(
        [
          ["Hits", "Total"],
          ["Pull Requests", response["pull_requests"]]
        ]
      );

      var optionsIssues = {
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

      var optionsPullRequests = {
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

      var gaugeChart5 = new google.visualization.Gauge(document.getElementById('gauge_chart_5'));
      gaugeChart5.draw(dataIssues, optionsIssues);
      var gaugeChart6 = new google.visualization.Gauge(document.getElementById('gauge_chart_6'));
      gaugeChart6.draw(dataPullRequests, optionsPullRequests);
    }
  </script>
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
  <div id="feed" style="width: 50%; float: left;background-color: white;"></div>
  <div id="messages" style="width: 50%; float: left;background-color: white;"></div>
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
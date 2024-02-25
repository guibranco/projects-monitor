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

    function drawChart() {
      var response = loadData();
      var dataWebhooks = google.visualization.arrayToDataTable(response["webhooks"]);
      var dataEvents = google.visualization.arrayToDataTable(response["events"]);
      var dataFeed = google.visualization.arrayToDataTable(response["feed"]);
      var dataTotal = google.visualization.arrayToDataTable([["Hits", "Total"], ["Total", response["total"]]]);

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

      var maxTo = Math.round(response["total"] * 1.1);
      var maxFrom = Math.round(response["total"] * 0.9);

      var minTo = Math.round(response["total"] * 0.5);

      var optionsTotal = {
        title: 'GitHub total',
        legend: { position: 'none' },
        showRowNumber: true,
        width: '100%',
        height: '100%',
        min: 0,
        max: maxTo,
        greenFrom: 0, greenTo: minTo,
        yellowFrom: minTo, yellowTo: maxFrom,
        redFrom: maxFrom, redTo: maxTo
      };

      var lineChart = new google.visualization.LineChart(document.getElementById('line_chart'));
      lineChart.draw(dataWebhooks, optionsWebhooks);
      var pieChart = new google.visualization.PieChart(document.getElementById('pie_chart'));
      pieChart.draw(dataEvents, optionsEvents);
      var table = new google.visualization.Table(document.getElementById('table_div'));
      table.draw(dataFeed, optionsFeed);
      var guageChart = new google.visualization.Gauge(document.getElementById('guage_chart'));
      guageChart.draw(dataTotal, optionsTotal);
      setTimeout(drawChart, 30000);
    }
  </script>
</head>

<body>
  <div id="line_chart" style="width: 70%; height: 400px; float: left;"></div>
  <img alt=""
    src="https://github-readme-stats-guibranco.vercel.app/api?username=guibranco&line_height=28&card_width=490&hide_title=true&hide_border=true&show_icons=true&theme=chartreuse-dark&icon_color=7FFF00&include_all_commits=true&count_private=true&show=reviews,discussions_started&count_private=true" />
  <img alt=""
    src="https://github-readme-streak-stats-flax.vercel.app/?user=guibranco&theme=github-green-purple&fire=FF6600" />
  <div style="clear:both;"></div>
  <div id="pie_chart" style="width: 70%; height: 300px; float: left;"></div>
  <div id="guage_chart" style="width: 20%; height: 300px; float: left;background-color: rgb(13, 17, 23);"></div>
  <div style="clear:both;"></div>
  <div id="table_div"></div>
</body>

</html>
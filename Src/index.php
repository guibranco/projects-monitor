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
    google.charts.load('current', { 'packages': ['corechart', 'table'] });
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

      var lineChart = new google.visualization.LineChart(document.getElementById('line_chart'));
      lineChart.draw(dataWebhooks, optionsWebhooks);
      var pieChart = new google.visualization.PieChart(document.getElementById('pie_chart'));
      pieChart.draw(dataEvents, optionsEvents);
      var table = new google.visualization.Table(document.getElementById('table_div'));
      table.draw(dataFeed, optionsFeed);
    }
  </script>
</head>

<body>
  <div id="line_chart" style="width: 60%; height: 300px; float: left;"></div>
  <img alt=""
    src="https://github-readme-stats-guibranco.vercel.app/api?username=guibranco&line_height=28&card_width=490&hide_title=true&hide_border=true&show_icons=true&theme=chartreuse-dark&icon_color=7FFF00&include_all_commits=true&count_private=true&show=reviews,discussions_started&count_private=true" />
  <div style="clear:both;"></div>
  <div id="pie_chart" style="width: 60%; height: 300px; float: left;"></div>
  <img alt=""
    src="https://github-readme-streak-stats-flax.vercel.app/?user=guibranco&theme=github-green-purple&fire=FF6600" />
  <div style="clear:both;"></div>
  <div id="table_div"></div>
</body>

</html>

google.charts.load("current", { packages: ["corechart", "table", "gauge"] });
google.charts.setOnLoadCallback(drawChart);
function load(url, callback) {
  const xhr = new XMLHttpRequest();
  xhr.open("GET", url, true);
  xhr.onreadystatechange = function () {
    if (this.readyState === 4 && this.status === 200) {
      callback(JSON.parse(this.responseText));
    }
  };
  xhr.send();
}

function preset() {
  showWebhook(
    JSON.parse(
      '{"events":[["Event","Hits"]],"failed":0,"feed":[["Sequence","Date","Event","Action","Repository"]],"repositories":[["Repository","Hits"]],"total":0,"webhooks":[["Date","Hits"], ["01/01", 0]]}'
    )
  );
  showMessages(
    JSON.parse(
      '{"total":0,"byApplications":[["Application","Messages"]],"messages":[["Id","Application","Message","Created At"]]}'
    )
  );
  showQueues(
    JSON.parse('{"queues":[["Server","Queue","Messages"]],"total":0}')
  );
  showGitHub(
    JSON.parse(
      '{"issues":{"total_issues":0, "latest":[], "bug":[], "triage":[], "wip":[]},"pull_requests":{"total_issues":0, "latest":[]}}'
    )
  );
}

function loadAll() {
  load("https://guilhermebranco.com.br/webhooks/api.php", showWebhook);
  load("api/v1/messages", showMessages);
  load("api/v1/queues", showQueues);
  load("api/v1/github", showGitHub);
}

let showPreset = true;
function drawChart() {
  if (showPreset) {
    preset();
    showPreset = false;
  }
  loadAll();
  setTimeout(drawChart, 30000);
}

function showWebhook(response) {
  const dataWebhooks = google.visualization.arrayToDataTable(
    response["webhooks"]
  );
  const dataEvents = google.visualization.arrayToDataTable(response["events"]);
  const dataFeed = google.visualization.arrayToDataTable(response["feed"]);
  const dataTotal = google.visualization.arrayToDataTable([
    ["Hits", "Total"],
    ["GH WH", response["total"]],
  ]);
  const dataFailed = google.visualization.arrayToDataTable([
    ["Hits", "Failed"],
    ["WH Failed", response["failed"]],
  ]);

  const optionsWebhooks = {
    title: "GitHub webhooks by date",
    legend: { position: "none" },
    colors: ["#0c5922"],
    pointSize: 7,
    hAxis: {
      title: "Webhooks",
      textStyle: {
        fontSize: 10,
      },
    },
  };

  const optionsEvents = {
    title: "GitHub events by type",
    legend: { position: "right" },
  };

  const optionsFeed = {
    title: "GitHub feed",
    legend: { position: "none" },
    showRowNumber: true,
    width: "100%",
    height: "100%",
  };

  const optionsTotal = {
    legend: { position: "none" },
    showRowNumber: true,
    width: "100%",
    height: "100%",
    min: 0,
    max: 120000,
    greenFrom: 0,
    greenTo: 40000,
    yellowFrom: 40000,
    yellowTo: 80000,
    redFrom: 80000,
    redTo: 120000,
  };

  const optionsFailed = {
    legend: { position: "none" },
    showRowNumber: true,
    width: "100%",
    height: "100%",
    min: 0,
    max: 1000,
    greenFrom: 0,
    greenTo: 50,
    yellowFrom: 50,
    yellowTo: 100,
    redFrom: 100,
    redTo: 1000,
  };

  const lineChart = new google.visualization.LineChart(
    document.getElementById("line_chart")
  );
  lineChart.draw(dataWebhooks, optionsWebhooks);
  const pieChart1 = new google.visualization.PieChart(
    document.getElementById("pie_chart_1")
  );
  pieChart1.draw(dataEvents, optionsEvents);
  const feed = new google.visualization.Table(document.getElementById("feed"));
  feed.draw(dataFeed, optionsFeed);
  const gaugeChart1 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_1")
  );
  gaugeChart1.draw(dataTotal, optionsTotal);
  const gaugeChart2 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_2")
  );
  gaugeChart2.draw(dataFailed, optionsFailed);
}

function showMessages(response) {
  const dataMessages = google.visualization.arrayToDataTable(
    response["messages"]
  );
  const dataTotal = google.visualization.arrayToDataTable([
    ["Items", "Total"],
    ["PM Errors", response["total"]],
  ]);
  const dataByApplications = google.visualization.arrayToDataTable(
    response["byApplications"]
  );

  const optionsMessages = {
    title: "Errors",
    legend: { position: "none" },
    showRowNumber: true,
    width: "100%",
    height: "100%",
  };

  const optionsTotal = {
    legend: { position: "none" },
    showRowNumber: true,
    width: "100%",
    height: "100%",
    min: 0,
    max: 1000,
    greenFrom: 0,
    greenTo: 250,
    yellowFrom: 250,
    yellowTo: 500,
    redFrom: 500,
    redTo: 1000,
  };

  const optionsByApplications = {
    title: "Messages by applications",
    legend: { position: "right" },
  };

  const messages = new google.visualization.Table(
    document.getElementById("messages")
  );
  messages.draw(dataMessages, optionsMessages);
  const gaugeChart3 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_3")
  );
  gaugeChart3.draw(dataTotal, optionsTotal);
  const pieChart2 = new google.visualization.PieChart(
    document.getElementById("pie_chart_2")
  );
  pieChart2.draw(dataByApplications, optionsByApplications);
}

function showQueues(response) {
  const dataTotal = google.visualization.arrayToDataTable([
    ["Items", "Total"],
    ["RabbitMQ", response["total"]],
  ]);
  const dataQueues = google.visualization.arrayToDataTable(response["queues"]);

  const optionsTotal = {
    legend: { position: "none" },
    showRowNumber: true,
    width: "100%",
    height: "100%",
    min: 0,
    max: 1000,
    greenFrom: 0,
    greenTo: 250,
    yellowFrom: 250,
    yellowTo: 500,
    redFrom: 500,
    redTo: 1000,
  };

  const optionsQueues = {
    title: "Errors",
    legend: { position: "none" },
    showRowNumber: true,
    width: "100%",
    height: "100%",
  };

  const queues = new google.visualization.Table(
    document.getElementById("queues")
  );
  queues.draw(dataQueues, optionsQueues);
  const gaugeChart4 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_4")
  );
  gaugeChart4.draw(dataTotal, optionsTotal);
}

function showGitHub(response) {
  const dataIssues = google.visualization.arrayToDataTable([
    ["Hits", "Total"],
    ["GH Issues", response["issues"]["total_count"]],
  ]);
  const dataPullRequests = google.visualization.arrayToDataTable([
    ["Hits", "Total"],
    ["GH PR", response["pull_requests"]["total_count"]],
  ]);
  const dataPullRequestsTable = google.visualization.arrayToDataTable(
    response["pull_requests"]["latest"]
  );
  const dataBugsTable = google.visualization.arrayToDataTable(
    response["issues"]["bug"]
  );
  const dataTriageTable = google.visualization.arrayToDataTable(
    response["issues"]["triage"]
  );
  const dataWipTable = google.visualization.arrayToDataTable(
    response["issues"]["wip"]
  );
  const dataIssuesTable = google.visualization.arrayToDataTable(
    response["issues"]["latest"]
  );

  const gaugueOptions = {
    legend: { position: "none" },
    showRowNumber: true,
    width: "100%",
    height: "100%",
    min: 0,
    max: 1000,
    greenFrom: 0,
    greenTo: 250,
    yellowFrom: 250,
    yellowTo: 500,
    redFrom: 500,
    redTo: 1000,
  };

  const tableOptions = {
    legend: { position: "none" },
    showRowNumber: true,
    width: "100%",
    height: "100%",
    allowHtml: true,
  };

  const gaugeChart5 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_5")
  );
  gaugeChart5.draw(dataIssues, gaugueOptions);
  const gaugeChart6 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_6")
  );
  gaugeChart6.draw(dataPullRequests, gaugueOptions);

  const pullRequests = new google.visualization.Table(
    document.getElementById("pull_requests")
  );
  pullRequests.draw(dataPullRequestsTable, tableOptions);

  const bug = new google.visualization.Table(document.getElementById("bug"));
  bug.draw(dataBugsTable, tableOptions);

  const triage = new google.visualization.Table(
    document.getElementById("triage")
  );
  triage.draw(dataTriageTable, tableOptions);

  const wip = new google.visualization.Table(document.getElementById("wip"));
  wip.draw(dataWipTable, tableOptions);

  const issues = new google.visualization.Table(
    document.getElementById("issues")
  );
  issues.draw(dataIssuesTable, tableOptions);
}

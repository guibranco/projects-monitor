const tableOptions = {
  legend: { position: "none" },
  allowHtml: true,
  showRowNumber: true,
  width: "100%",
  height: "100%",
};

window.addEventListener("load", init);

function init() {
  document.getElementById("gh_stats").src =
    "https://github-readme-stats-guibranco.vercel.app/api" +
    "?username=guibranco&line_height=28&card_width=490&hide_title=true&hide_border=true" +
    "&show_icons=true&theme=chartreuse-dark&icon_color=7FFF00&include_all_commits=true" +
    "&count_private=true&show=reviews,discussions_started&count_private=true";

  document.getElementById("gh_streak").src =
    "https://github-readme-streak-stats-guibranco.vercel.app/" +
    "?user=guibranco&theme=github-green-purple&fire=FF6600";
}

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
  showCPanel(
    JSON.parse(
      '{"errorLogFiles":[],"errorLogMessages":[],"totalLogMessages":0,"cronjobs":[]}'
    )
  );
  showGitHub(
    JSON.parse(
      '{"issues":{"total_count":0,"others":[],"bug":[],"triage":[],"wip":[],"assigned":[]},"pull_requests":{"total_count":0,"latest":[],"authored":[]},"accounts_usage":[]}'
    )
  );
  showMessages(
    JSON.parse(
      '{"total":0,"byApplications":[["Applications","Hits"]],"messages":[]}'
    )
  );
  showQueues(
    JSON.parse(
      '{"queues":[],"total":0}'
    )
  );
  showWebhook(
    JSON.parse(
      '{"events":[["Event","Hits"]],"failed":0,"feed":[],"repositories":[],"total":0,"webhooks":[["Date","Hits"], ["01/01", 0]],"workflow_runs":[],"total_workflow_runs":0, "installations":0}'
    )
  );
}

function loadAll() {
  load("api/v1/cpanel", showCPanel);
  load("api/v1/domains", showDomains);
  load("api/v1/github", showGitHub);
  load("api/v1/healthchecksio", showHealthChecksIo);
  load("api/v1/messages", showMessages);
  load("api/v1/queues", showQueues);
  load("api/v1/uptimerobot", showUpTimeRobot);
  load("api/v1/webhooks", showWebhook);
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

function showCPanel(response) {
  const dataLogFiles = google.visualization.arrayToDataTable(
    response["errorLogFiles"]
  );
  const dataLogMessages = google.visualization.arrayToDataTable(
    response["errorLogMessages"]
  );
  const dataCronjobs = google.visualization.arrayToDataTable(
    response["cronjobs"]
  );
  const totalLogMessages = google.visualization.arrayToDataTable([
    ["Hits", "Total"],
    ["Log errors", response["totalLogMessages"]],
  ]);

  const gaugeOptions = {
    legend: { position: "none" },
    showRowNumber: true,
    width: "100%",
    height: "100%",
    min: 0,
    max: 500,
    greenFrom: 0,
    greenTo: 10,
    yellowFrom: 10,
    yellowTo: 200,
    redFrom: 200,
    redTo: 500,
  };

  const gaugeChart7 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_7")
  );
  gaugeChart7.draw(totalLogMessages, gaugeOptions);
  
  const logFiles = new google.visualization.Table(
    document.getElementById("errorLogFiles")
  );
  logFiles.draw(dataLogFiles, tableOptions);
  const logMessages = new google.visualization.Table(
    document.getElementById("errorLogMessages")
  );
  logMessages.draw(dataLogMessages, tableOptions);
  const cronjobs = new google.visualization.Table(
    document.getElementById("cronjobs")
  );
  cronjobs.draw(dataCronjobs, tableOptions);
}

function showDomains(response) {
  const dataDomains = google.visualization.arrayToDataTable(response["domains"]);
  const domains = new google.visualization.Table(
    document.getElementById("domains")
  );
  domains.draw(dataDomains, tableOptions);
}

function showGitHub(response) {
  const dataIssues = google.visualization.arrayToDataTable([
    ["Hits", "Total"],
    ["GH Issues", response["issues"]["total_count"]],
  ]);
  const dataPullRequests = google.visualization.arrayToDataTable([
    ["Hits", "Total"],
    ["GH PRs", response["pull_requests"]["total_count"]],
  ]);
  const dataPullRequestsTable = google.visualization.arrayToDataTable(
    response["pull_requests"]["latest"]
  );
  const dataPullRequestsAuthoredTable = google.visualization.arrayToDataTable(
    response["pull_requests"]["authored"]
  );
  const dataAssignedTable = google.visualization.arrayToDataTable(
    response["issues"]["assigned"]
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
    response["issues"]["other"]
  );
  const dataAccountsUsage = google.visualization.arrayToDataTable(
    response["accounts_usage"]
  );

  if (typeof response["latestRelease"] !== "undefined") {
    const latestRelease = response["latestRelease"];
    document.getElementById("latest_release").innerHTML =      
      "<b>Release Notes:</b> " +
      latestRelease["description"] +
      "<b>Date:</b> " + latestRelease["published"] +
      " | " +
      "<b>Version:</b> " +  "<a href='" + latestRelease["release_url"] + "'>" + latestRelease["title"] + "</a>" +
      " | " +
      "<a href='https://github.com/" + latestRelease["repository"] + "' target='_blank'>" +
      "<img alt='Static Badge' src='https://img.shields.io/badge/" + latestRelease["repository"] + "-black?style=flat&amp;logo=github'></a>" +
      " | " +
      "<a href='https://github.com/" + latestRelease["author"] + "' target='_blank'>" +
      "<img alt='author' src='https://img.shields.io/badge/" + latestRelease["author"] + "-black?style=social&amp;logo=github'></a>";
  }

  const gaugeOptions = {
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

  const gaugeChart5 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_5")
  );
  gaugeChart5.draw(dataIssues, gaugeOptions);
  const gaugeChart6 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_6")
  );
  gaugeChart6.draw(dataPullRequests, gaugeOptions);

  const pullRequests = new google.visualization.Table(
    document.getElementById("pull_requests")
  );
  pullRequests.draw(dataPullRequestsTable, tableOptions);

  const pullRequestsAuthored = new google.visualization.Table(
    document.getElementById("pull_requests_authored")
  );
  pullRequestsAuthored.draw(dataPullRequestsAuthoredTable, tableOptions);

  const assigned = new google.visualization.Table(document.getElementById("assigned"));
  assigned.draw(dataAssignedTable, tableOptions);
  
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
  
  const accountsUsage = new google.visualization.Table(
    document.getElementById("accounts_usage")
  );
  accountsUsage.draw(dataAccountsUsage, tableOptions);
}

function showHealthChecksIo(response) {
  const dataHealthChecksIo = google.visualization.arrayToDataTable(
    response["checks"]
  );
  const healthChecksIo = new google.visualization.Table(
    document.getElementById("healthchecksio")
  );
  healthChecksIo.draw(dataHealthChecksIo, tableOptions);
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
  messages.draw(dataMessages, tableOptions);
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

  const queues = new google.visualization.Table(
    document.getElementById("queues")
  );
  queues.draw(dataQueues, tableOptions);
  const gaugeChart4 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_4")
  );
  gaugeChart4.draw(dataTotal, optionsTotal);
}

function showUpTimeRobot(response) {
  const dataUpTimeRobot = google.visualization.arrayToDataTable(
    response["monitors"]
  );
  const upTimeRobot = new google.visualization.Table(
    document.getElementById("uptimerobot")
  );
  upTimeRobot.draw(dataUpTimeRobot, tableOptions);
}

function showWebhook(response) {
  const dataWebhooks = google.visualization.arrayToDataTable(
    response["webhooks"]
  );
  const dataEvents = google.visualization.arrayToDataTable(response["events"]);
  const dataFeed = google.visualization.arrayToDataTable(response["feed"]);
  const dataRepositories = google.visualization.arrayToDataTable(
    response["repositories"]
  );
  const dataWorkflowRuns = google.visualization.arrayToDataTable(
    response["workflow_runs"]
  );
  const dataTotal = google.visualization.arrayToDataTable([
    ["Hits", "Total"],
    ["GH WH", response["total"]],
  ]);
  const dataFailed = google.visualization.arrayToDataTable([
    ["Hits", "Failed"],
    ["WH Failed", response["failed"]],
  ]);
  const dataTotalWorkflowRuns = google.visualization.arrayToDataTable([
    ["Hits", "GH WRs"],
    ["GH WRs", response["total_workflow_runs"]],
  ]);
  const dataInstallations = google.visualization.arrayToDataTable([
    ["Hits", "GH App"],
    ["GH App", response["installations"]],
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

  const optionsTotalWorkflowRuns = {
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

  const optionsInstallations = {
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
  const repositories = new google.visualization.Table(
    document.getElementById("repositories")
  );
  repositories.draw(dataRepositories, tableOptions);
  const workflowRuns = new google.visualization.Table(
    document.getElementById("workflow_runs")
  );
  workflowRuns.draw(dataWorkflowRuns, tableOptions);
  const feed = new google.visualization.Table(document.getElementById("feed"));
  feed.draw(dataFeed, tableOptions);
  const gaugeChart1 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_1")
  );
  gaugeChart1.draw(dataTotal, optionsTotal);
  const gaugeChart2 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_2")
  );
  gaugeChart2.draw(dataFailed, optionsFailed);
  const gaugeChart8 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_8")
  );
  gaugeChart8.draw(dataTotalWorkflowRuns, optionsTotalWorkflowRuns);
  const gaugeChart9 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_9")
  );
  gaugeChart9.draw(dataInstallations, optionsInstallations);
}

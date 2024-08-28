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

  const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
  const offset = new Date().toString().match(/([-\+][0-9]+)\s/)[1];
  setCookie("timezone", timezone, 10);
  setCookie("offset", offset, 10);
}

function setCookie(name, value, expireDays) {
  const date = new Date();
  date.setTime(date.getTime() + expireDays * 24 * 60 * 60 * 1000);
  let expires = "expires=" + date.toUTCString();
  document.cookie = name + "=" + value + ";" + expires + ";";
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
  if (!showPreset) {
    return;
  }
  showPreset = false;
  showCPanel(
    JSON.parse(
      '{"error_log_files":[],"error_log_messages":[],"total_error_messages":0,"cronjobs":[]}'
    )
  );
  showGitHub(
    JSON.parse(
      '{"issues":{"total_count":0,"others":[],"bug":[],"triage":[],"wip":[],"assigned":[],"authored":[],"blocked":[]},"pull_requests":{"total_count":0,"latest":[],"authored":[],"blocked":[]},"accounts_usage":[]}'
    )
  );
  showMessages(
    JSON.parse(
      '{"total":0,"byApplications":[["Applications","Hits"]],"grouped":[]}'
    )
  );
  showQueues(JSON.parse('{"queues":[],"total":0}'));
  showWebhook(
    JSON.parse(
      '{"bots":[],"events":[["Event","Hits"]],"failed":0,"feed":[],"repositories":[],"total":0,"statistics":[["Date","Table #1"],["01/01",0]],"statistics_github":[["Date","Table #1"],["01/01",0]],"workflow_runs":[],"total_workflow_runs":0, "installations":0}'
    )
  );
}

function load30Interval() {
  load("api/v1/appveyor", showAppVeyor);
  load("api/v1/cpanel", showCPanel);
  load("api/v1/messages", showMessages);
  load("api/v1/queues", showQueues);
  load("api/v1/webhooks", showWebhook);
}

function load60Interval() {
  load("api/v1/domains", showDomains);
  load("api/v1/github", showGitHub);
  load("api/v1/healthchecksio", showHealthChecksIo);
  load("api/v1/uptimerobot", showUpTimeRobot);
}

let showPreset = true;

function drawChart() {
  preset();
  load30Interval();
  load60Interval();
  setInterval(load30Interval, 30 * 1000);
  setInterval(load60Interval, 60 * 1000);
}

function showAppVeyor(response) {
  const dataProjects = google.visualization.arrayToDataTable(
    response["projects"]
  );

  const projects = new google.visualization.Table(
    document.getElementById("appveyor")
  );
  projects.draw(dataProjects, tableOptions);
}

function showCPanel(response) {
  const dataLogFiles = google.visualization.arrayToDataTable(
    response["error_log_files"]
  );
  const dataLogMessages = google.visualization.arrayToDataTable(
    response["error_log_messages"]
  );
  const dataCronjobs = google.visualization.arrayToDataTable(
    response["cronjobs"]
  );
  const totalLogMessages = google.visualization.arrayToDataTable([
    ["Hits", "Total"],
    ["Log errors", response["total_error_messages"]],
  ]);

  const gaugeOptions = {
    legend: { position: "none" },
    showRowNumber: true,
    width: "100%",
    height: "100%",
    min: 0,
    max: 1000,
    greenFrom: 0,
    greenTo: 100,
    yellowFrom: 100,
    yellowTo: 500,
    redFrom: 500,
    redTo: 1000,
  };

  const gaugeChart7 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_7")
  );
  gaugeChart7.draw(totalLogMessages, gaugeOptions);
  const logFiles = new google.visualization.Table(
    document.getElementById("error_log_files")
  );
  logFiles.draw(dataLogFiles, tableOptions);
  const logMessages = new google.visualization.Table(
    document.getElementById("error_log_messages")
  );
  logMessages.draw(dataLogMessages, tableOptions);
  const cronjobs = new google.visualization.Table(
    document.getElementById("cronjobs")
  );
  cronjobs.draw(dataCronjobs, tableOptions);
}

function showDomains(response) {
  const dataDomains = google.visualization.arrayToDataTable(
    response["domains"]
  );
  const domains = new google.visualization.Table(
    document.getElementById("domains")
  );
  domains.draw(dataDomains, tableOptions);
}

/**
 * Displays GitHub statistics and information based on the provided response data.
 *
 * This function processes various metrics related to GitHub issues and pull requests,
 * visualizing them using Google Charts. It also updates the latest release information
 * if available.
 *
 * @param {Object} response - The response object containing GitHub data.
 * @param {Object} response.issues - An object containing issue-related data.
 * @param {number} response.issues.total_count - The total number of issues.
 * @param {Array} response.issues.assigned - Data for assigned issues.
 * @param {Array} response.issues.authored - Data for authored issues.
 * @param {Array} response.issues.blocked - Data for blocked issues.
 * @param {Array} response.issues.bug - Data for bug issues.
 * @param {Array} response.issues.triage - Data for triage issues.
 * @param {Array} response.issues.wip - Data for work-in-progress issues.
 * @param {Array} response.issues.others - Data for other issues.
 * @param {Object} response.pull_requests - An object containing pull request-related data.
 * @param {number} response.pull_requests.total_count - The total number of pull requests.
 * @param {Array} response.pull_requests.latest - Data for the latest pull requests.
 * @param {Array} response.pull_requests.authored - Data for authored pull requests.
 * @param {Array} response.pull_requests.blocked - Data for blocked pull requests.
 * @param {Object} response.latest_release - An object containing information about the latest release.
 * @param {string} response.latest_release.description - Description of the latest release.
 * @param {string} response.latest_release.published - Publication date of the latest release.
 * @param {string} response.latest_release.release_url - URL for the latest release.
 * @param {string} response.latest_release.title - Title of the latest release.
 * @param {string} response.latest_release.repository - Repository name of the latest release.
 * @param {string} response.latest_release.author - Author of the latest release.
 *
 * @throws {Error} Throws an error if the response object does not contain the expected structure.
 *
 * @example
 * const response = {
 *   issues: {
 *     total_count: 10,
 *     assigned: [...],
 *     authored: [...],
 *   },
 *   pull_requests: {
 *     total_count: 5,
 *     latest: [...],
 *   },
 *   latest_release: {
 *     description: "New features added",
 *     published: "2023-10-01",
 *     release_url: "https://github.com/user/repo/releases/tag/v1.0",
 *     title: "Version 1.0",
 *     repository: "user/repo",
 *     author: "user"
 *   }
 * };
 *
 * showGitHub(response);
 */
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
  const dataPullRequestsBlockedTable = google.visualization.arrayToDataTable(
    response["pull_requests"]["blocked"]
  );
  const dataAssignedTable = google.visualization.arrayToDataTable(
    response["issues"]["assigned"]
  );
  const dataAuthoredTable = google.visualization.arrayToDataTable(
    response["issues"]["authored"]
  );
  const dataBlockedTable = google.visualization.arrayToDataTable(
    response["issues"]["blocked"]
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
    response["issues"]["others"]
  );
  const dataAccountsUsage = google.visualization.arrayToDataTable(
    response["accounts_usage"]
  );

  if (typeof response["latest_release"] !== "undefined") {
    const latestRelease = response["latest_release"];
    document.getElementById("latest_release").innerHTML =
      "<b>Release Notes:</b> " +
      latestRelease["description"] +
      "<b>Date:</b> " +
      latestRelease["published"] +
      " | " +
      "<b>Version:</b> " +
      "<a href='" +
      latestRelease["release_url"] +
      "'>" +
      latestRelease["title"] +
      "</a>" +
      " | " +
      "<a href='https://github.com/" +
      latestRelease["repository"] +
      "' target='_blank'>" +
      "<img alt='Static Badge' src='https://img.shields.io/badge/" +
      latestRelease["repository"] +
      "-black?style=flat&amp;logo=github'></a>" +
      " | " +
      "<a href='https://github.com/" +
      latestRelease["author"] +
      "' target='_blank'>" +
      "<img alt='author' src='https://img.shields.io/badge/" +
      latestRelease["author"] +
      "-black?style=social&amp;logo=github'></a>";
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
    document.getElementById("pull_requests_latest")
  );
  pullRequests.draw(dataPullRequestsTable, tableOptions);
  const pullRequestsAuthored = new google.visualization.Table(
    document.getElementById("pull_requests_authored")
  );
  pullRequestsAuthored.draw(dataPullRequestsAuthoredTable, tableOptions);
  const pullRequestsBlocked = new google.visualization.Table(
    document.getElementById("pull_requests_blocked")
  );
  pullRequestsBlocked.draw(dataPullRequestsBlockedTable, tableOptions);
  const assigned = new google.visualization.Table(
    document.getElementById("assigned")
  );
  assigned.draw(dataAssignedTable, tableOptions);
  const authored = new google.visualization.Table(
    document.getElementById("issues_authored")
  );
  authored.draw(dataAuthoredTable, tableOptions);
  const bug = new google.visualization.Table(document.getElementById("bug"));
  bug.draw(dataBugsTable, tableOptions);
  const blocked = new google.visualization.Table(
    document.getElementById("blocked")
  );
  blocked.draw(dataBlockedTable, tableOptions);
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
  const dataGrouped = google.visualization.arrayToDataTable(
    response["grouped"]
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
    max: 3000,
    greenFrom: 0,
    greenTo: 500,
    yellowFrom: 500,
    yellowTo: 1000,
    redFrom: 1000,
    redTo: 3000,
  };

  const optionsByApplications = {
    title: "Messages by applications",
    legend: { position: "right" },
  };

  const grouped = new google.visualization.Table(
    document.getElementById("messages_grouped")
  );
  grouped.draw(dataGrouped, tableOptions);
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
    ["Queues", response["total"]],
  ]);
  const dataQueues = google.visualization.arrayToDataTable(response["queues"]);

  const optionsTotal = {
    legend: { position: "none" },
    showRowNumber: true,
    width: "100%",
    height: "100%",
    min: 0,
    max: 10000,
    greenFrom: 0,
    greenTo: 500,
    yellowFrom: 500,
    yellowTo: 1000,
    redFrom: 1000,
    redTo: 10000,
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

/**
 * Renders various charts and tables based on the provided webhook response data.
 *
 * This function processes the response object to create visual representations of webhook statistics,
 * GitHub events, and other related data using Google Charts. It generates line charts, pie charts,
 * and gauge charts to display the information effectively.
 *
 * @param {Object} response - The response object containing webhook data.
 * @param {Array} response.statistics - An array of statistics data for webhooks.
 * @param {Array} response.statistics_github - An array of statistics data for GitHub webhooks.
 * @param {Array} response.events - An array of events data.
 * @param {Array} response.feed - An array of feed data.
 * @param {Array} response.bots - An array of bot data.
 * @param {Array} response.repositories - An array of repository data.
 * @param {Array} response.workflow_runs - An array of workflow run data.
 * @param {number} response.total - The total number of webhooks.
 * @param {number} response.failed - The number of failed webhooks.
 * @param {number} response.total_workflow_runs - The total number of workflow runs.
 * @param {number} response.installations - The number of installations.
 * @param {string} [response.check_hooks_date] - The date when hooks were last checked.
 *
 * @throws {Error} Throws an error if the response object is missing required properties.
 *
 * @example
 * const webhookResponse = {
 *   statistics: [...],
 *   statistics_github: [...],
 *   events: [...],
 *   feed: [...],
 *   bots: [...],
 *   repositories: [...],
 *   workflow_runs: [...],
 *   total: 100,
 *   failed: 5,
 *   total_workflow_runs: 50,
 *   installations: 10,
 *   check_hooks_date: "2023-10-01T12:00:00Z"
 * };
 * showWebhook(responseData);
 */
function showWebhook(response) {
  const dataStatistics = google.visualization.arrayToDataTable(
    response["statistics"]
  );
  const dataStatisticsGitHub = google.visualization.arrayToDataTable(
    response["statistics_github"]
  );
  const dataEvents = google.visualization.arrayToDataTable(response["events"]);
  const dataFeed = google.visualization.arrayToDataTable(response["feed"]);
  const dataBots = google.visualization.arrayToDataTable(response["bots"]);
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

  const optionsStatistics = {
    title: "Webhooks by date",
    legend: { position: "right" },
    pointSize: 7,
    hAxis: {
      title: "Date",
      textStyle: {
        fontSize: 10,
      },
    },
  };

  const optionsStatisticsGitHub = {
    title: "GitHub webhooks by date",
    legend: "none",
    series: {
      0: { color: "green" },
    },
    pointSize: 7,
    hAxis: {
      title: "Date",
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
    max: 400000,
    greenFrom: 0,
    greenTo: 200000,
    yellowFrom: 200000,
    yellowTo: 300000,
    redFrom: 300000,
    redTo: 400000,
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

  if (typeof response["check_hooks_date"] !== "undefined") {
    const checkHooksDate = new Date(response["check_hooks_date"]);
    document.getElementById("hooks_last_check").innerHTML =
      "<b>Date: </b> " + checkHooksDate.toString();
  }

  const statisticsChart = new google.visualization.LineChart(
    document.getElementById("webhooks_statistics")
  );
  statisticsChart.draw(dataStatistics, optionsStatistics);
  const statisticsGitHubChart = new google.visualization.LineChart(
    document.getElementById("webhooks_statistics_github")
  );
  statisticsGitHubChart.draw(dataStatisticsGitHub, optionsStatisticsGitHub);

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
  const bots = new google.visualization.Table(document.getElementById("bots"));
  bots.draw(dataBots, tableOptions);

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

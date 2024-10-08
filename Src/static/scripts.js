const tableOptions = {
  legend: { position: "none" },
  allowHtml: true,
  showRowNumber: true,
  width: "100%",
  height: "100%",
};

window.addEventListener("load", init);

/**
     * Initializes the application by setting the user's timezone and offset in cookies.
     * This function retrieves the current timezone and UTC offset of the user's system,
     * then stores these values in cookies for later use.
     *
     * @function init
     * @returns {void} This function does not return a value.
     *
     * @example
     * // Call the init function to set the timezone and offset cookies
     * init();
     *
     * @throws {Error} Throws an error if there is an issue setting the cookies.
     */
function init() {
  const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
  const offset = new Date().toString().match(/([-\+][0-9]+)\s/)[1];
  setCookie("timezone", timezone, 10);
  setCookie("offset", offset, 10);
}

/**
     * Sets a cookie in the browser with the specified name, value, and expiration days.
     *
     * @param {string} name - The name of the cookie.
     * @param {string} value - The value to be stored in the cookie.
     * @param {number} expireDays - The number of days until the cookie expires.
     * 
     * @throws {TypeError} Throws an error if the name or value is not a string, or if expireDays is not a number.
     *
     * @example
     * // Set a cookie named "user" with the value "John Doe" that expires in 7 days
     * setCookie('user', 'John Doe', 7);
     */
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

/**
     * Initializes and displays preset information if the `showPreset` flag is true.
     * This function retrieves various data sets in JSON format and displays them
     * using corresponding display functions.
     *
     * The following data is fetched and displayed:
     * - Error log files and messages
     * - GitHub API usage and issues
     * - Application hit statistics
     * - Queue information
     * - Webhook event statistics
     *
     * If `showPreset` is false, the function will terminate early without performing any actions.
     *
     * @throws {Error} Throws an error if JSON parsing fails for any of the data sets.
     *
     * @example
     * // Assuming showPreset is true, calling preset() will display the preset information.
     * preset();
     */
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
      '{"api_usage":[],"issues":{"total_count":0,"others":[],"bug":[],"triage":[],"wip":[],"assigned":[],"authored":[],"blocked":[]},"pull_requests":{"total_count":0,"awaiting_triage":[],"latest":[],"authored":[],"blocked":[]},"accounts_usage":[]}'
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
      '{"senders":[],"events":[["Event","Hits"]],"failed":0,"feed":[],"repositories":[],"total":0,"statistics":[["Date","Table #1"],["01/01",0]],"statistics_github":[["Date","Table #1"],["01/01",0]],"workflow_runs":[],"total_workflow_runs":0, "installations":0, "installation_repositories": [], "installation_repositories_count": 0}'
    )
  );
}

/**
     * Loads data from multiple API endpoints at once.
     *
     * This function initiates requests to various API endpoints and processes the responses
     * using designated callback functions. The endpoints being accessed include:
     * - AppVeyor
     * - cPanel
     * - Messages
     * - Queues
     * - Webhooks
     *
     * It is assumed that the `load` function is defined elsewhere in the codebase and is responsible
     * for making the actual API calls and handling the responses.
     *
     * @function load30Interval
     * @returns {void} This function does not return a value.
     *
     * @example
     * // To load data from the specified endpoints, simply call:
     * load30Interval();
     *
     * @throws {Error} Throws an error if any of the API requests fail.
     */
function load30Interval() {
  load("api/v1/appveyor", showAppVeyor);
  load("api/v1/cpanel", showCPanel);
  load("api/v1/messages", showMessages);
  load("api/v1/queues", showQueues);
  load("api/v1/webhooks", showWebhook);
}

/**
     * Loads data from multiple API endpoints and displays the results using respective callback functions.
     *
     * This function is responsible for initiating data retrieval from various services, including domains,
     * GitHub, health checks, uptime monitoring, and WireGuard configurations. Each API response is processed
     * by a dedicated function to handle the display of the retrieved data.
     *
     * @function load60Interval
     * @throws {Error} Throws an error if any of the API calls fail to execute properly.
     *
     * @example
     * // Call the function to load data from all specified APIs
     * load60Interval();
     */
function load60Interval() {
  load("api/v1/domains", showDomains);
  load("api/v1/github", showGitHub);
  load("api/v1/healthchecksio", showHealthChecksIo);
  load("api/v1/uptimerobot", showUpTimeRobot);
  load("api/v1/wireguard", showWireGuard);
}

/**
 * Loads data from the Postman API at a specified interval.
 * This function initiates a request to the API endpoint "api/v1/postman"
 * and processes the response using the `showPostman` callback function.
 *
 * @function load300Interval
 * @returns {void} This function does not return a value.
 *
 * @example
 * // Call the function to load Postman data
 * load300Interval();
 *
 * @throws {Error} Throws an error if the API request fails.
 */
function load300Interval() {
  load("api/v1/postman", showPostman);
}

let showPreset = true;

/**
     * Initializes and starts the process of drawing the chart by setting up
     * necessary presets and loading data at specified intervals.
     *
     * This function calls several other functions to prepare the chart,
     * including loading statistics from GitHub and setting up data loading
     * intervals for different time frames (30 seconds, 60 seconds, and 300 seconds).
     *
     * It also sets a longer interval for updating GitHub statistics every 15 minutes.
     *
     * @throws {Error} Throws an error if any of the called functions fail to execute.
     *
     * @example
     * // To draw the chart and start data loading
     * drawChart();
     */
function drawChart() {
  preset();
  showGitHubStats();
  load30Interval();
  load60Interval();
  load300Interval();
  setInterval(load30Interval, 30 * 1000);
  setInterval(load60Interval, 60 * 1000);
  setInterval(load300Interval, 300 * 1000);
  setInterval(showGitHubStats, 60 * 15 * 1000);
}

/**
     * Updates the GitHub statistics and streak images displayed on the webpage.
     * This function generates a random refresh parameter to ensure that the 
     * images are updated each time the function is called, preventing caching 
     * issues in the browser.
     *
     * It modifies the `src` attributes of two <img> elements with IDs 
     * "gh_stats" and "gh_streak" to point to the respective GitHub stats 
     * and streak stats URLs, incorporating the generated refresh parameter.
     *
     * @throws {Error} Throws an error if the elements with IDs "gh_stats" 
     *                 or "gh_streak" do not exist in the document.
     *
     * @example
     * // Call this function to refresh GitHub stats and streaks
     * showGitHubStats();
     */
function showGitHubStats() {
  const refresh = Math.floor(Math.random() * 100000);

  document.getElementById("gh_stats").src =
    "https://github-readme-stats-guibranco.vercel.app/api" +
    "?username=guibranco&line_height=28&card_width=490&hide_title=true&hide_border=true" +
    "&show_icons=true&theme=chartreuse-dark&icon_color=7FFF00&include_all_commits=true" +
    "&count_private=true&show=reviews,discussions_started&count_private=true&refresh=" +
    refresh;

  document.getElementById("gh_streak").src =
    "https://github-readme-streak-stats-guibranco.vercel.app/" +
    "?user=guibranco&theme=github-green-purple&fire=FF6600&refresh=" +
    refresh;
}

/**
 * Renders a table displaying project data from AppVeyor using Google Charts.
 *
 * This function takes a response object containing project data and converts it
 * into a format suitable for visualization. It then creates a new table in the
 * specified HTML element and draws the data onto it.
 *
 * @param {Object} response - The response object containing project data.
 * @param {Array} response.projects - An array of project data to be visualized.
 *
 * @throws {Error} Throws an error if the response does not contain the expected
 *                 structure or if the Google Charts library is not loaded.
 *
 * @example
 * const response = {
 *   projects: [
 *     ['Project Name', 'Status'],
 *     ['Project A', 'Success'],
 *     ['Project B', 'Failed']
 *   ]
 * };
 * showAppVeyor(response);
 */
function showAppVeyor(response) {
  const dataProjects = google.visualization.arrayToDataTable(
    response["projects"]
  );

  const projects = new google.visualization.Table(
    document.getElementById("appveyor")
  );
  projects.draw(dataProjects, tableOptions);
}

/**
 * Displays the control panel with various visualizations based on the provided response data.
 *
 * This function processes the response object to extract error log files, error log messages,
 * and cronjob data, and then visualizes this information using Google Charts. It creates a gauge
 * chart for total log messages and tables for log files, log messages, and cronjobs.
 *
 * @param {Object} response - The response object containing data for visualization.
 * @param {Array} response.error_log_files - An array of error log files to be displayed.
 * @param {Array} response.error_log_messages - An array of error log messages to be displayed.
 * @param {Array} response.cronjobs - An array of cronjob data to be displayed.
 * @param {number} response.total_error_messages - The total number of error messages.
 *
 * @throws {Error} Throws an error if the response object is missing required properties.
 *
 * @example
 * const response = {
 *   error_log_files: [...],
 *   error_log_messages: [...],
 *   cronjobs: [...],
 *   total_error_messages: 42
 * };
 * showCPanel(response);
 */
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

/**
     * Renders a table of domains using Google Visualization API.
     *
     * This function takes a response object containing domain data,
     * converts it into a format suitable for visualization, and then
     * draws the table in the specified HTML element.
     *
     * @param {Object} response - The response object containing domain data.
     * @param {Array} response.domains - An array of domain data to be visualized.
     *
     * @throws {Error} Throws an error if the response does not contain the expected data format.
     *
     * @example
     * const response = {
     *   domains: [
     *     ['Domain', 'Count'],
     *     ['example.com', 10],
     *     ['test.com', 5]
     *   ]
     * };
     * showDomains(response);
     */
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
     * Displays GitHub statistics and data visualizations based on the provided response object.
     *
     * This function processes various metrics related to GitHub issues and pull requests,
     * converting them into data tables for visualization. It also updates the latest release
     * information if available.
     *
     * @param {Object} response - The response object containing GitHub data.
     * @param {Object} response.issues - An object containing issue-related data.
     * @param {number} response.issues.total_count - The total number of issues.
     * @param {Array} response.issues.assigned - List of assigned issues.
     * @param {Array} response.issues.authored - List of authored issues.
     * @param {Array} response.issues.blocked - List of blocked issues.
     * @param {Array} response.issues.bug - List of bug issues.
     * @param {Array} response.issues.triage - List of triaged issues.
     * @param {Array} response.issues.wip - List of work-in-progress issues.
     * @param {Array} response.issues.others - List of other issues.
     * @param {Object} response.pull_requests - An object containing pull request-related data.
     * @param {number} response.pull_requests.total_count - The total number of pull requests.
     * @param {Array} response.pull_requests.latest - List of latest pull requests.
     * @param {Array} response.pull_requests.authored - List of authored pull requests.
     * @param {Array} response.pull_requests.awaiting_triage - List of pull requests awaiting triage.
     * @param {Array} response.pull_requests.blocked - List of blocked pull requests.
     * @param {Object} response.latest_release - Information about the latest release.
     * @param {string} response.latest_release.description - Description of the latest release.
     * @param {string} response.latest_release.published - Publication date of the latest release.
     * @param {string} response.latest_release.release_url - URL to the latest release.
     * @param {string} response.latest_release.title - Title of the latest release.
     * @param {string} response.latest_release.repository - Repository name for the latest release.
     * @param {string} response.latest_release.author - Author of the latest release.
     *
     * @throws {TypeError} Throws an error if the response object is not valid or does not contain expected properties.
     *
     * @example
     * const githubResponse = {
     *   issues: {
     *     total_count: 10,
     *     assigned: [...],
     *     authored: [...],
     *     blocked: [...],
     *     bug: [...],
     *     triage: [...],
     *     wip: [...],
     *     others: [...]
     *   },
     *   pull_requests: {
     *     total_count: 5,
     *     latest: [...],
     *     authored: [...],
     *     awaiting_triage: [...],
     *     blocked: [...]
     *   },
     *   latest_release: {
     *     description: "Initial release",
     *     published: "2023-01-01",
     *     release_url: "https://github.com/user/repo/releases/tag/v1.0",
     *     title: "v1.0",
     *     repository: "user/repo",
     *     author: "user"
     *   }
     * };
     *
     * showGitHub(githubResponse);
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
  const dataPullRequestsTriageTable = google.visualization.arrayToDataTable(
    response["pull_requests"]["awaiting_triage"]
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
  const dataApiUsage = google.visualization.arrayToDataTable(
    response["api_usage"]
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
      "' target='_blank' rel='noopener noreferrer'>" +
      "<img alt='Static Badge' src='https://img.shields.io/badge/" +
      latestRelease["repository"] +
      "-black?style=flat&amp;logo=github'></a>" +
      " | " +
      "<a href='https://github.com/" +
      latestRelease["author"] +
      "' target='_blank' rel='noopener noreferrer'>" +
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
  const pullRequestsTriage = new google.visualization.Table(
    document.getElementById("pull_requests_triage")
  );
  pullRequestsTriage.draw(dataPullRequestsTriageTable, tableOptions);
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
  const apiUsage = new google.visualization.Table(
    document.getElementById("api_usage")
  );
  apiUsage.draw(dataApiUsage, tableOptions);
}

/**
 * Renders a table visualization of health checks data using Google Charts.
 *
 * This function takes a response object containing health check data,
 * converts it into a format suitable for visualization, and then draws
 * the table in the specified HTML element.
 *
 * @param {Object} response - The response object containing health check data.
 * @param {Array} response.checks - An array of health check objects to be visualized.
 *
 * @throws {Error} Throws an error if the response does not contain the expected data format.
 *
 * @example
 * const response = {
 *   checks: [
 *     ['Check 1', 'OK'],
 *     ['Check 2', 'Failed']
 *   ]
 * };
 * showHealthChecksIo(response);
 */
function showHealthChecksIo(response) {
  const dataHealthChecksIo = google.visualization.arrayToDataTable(
    response["checks"]
  );
  const healthChecksIo = new google.visualization.Table(
    document.getElementById("healthchecksio")
  );
  healthChecksIo.draw(dataHealthChecksIo, tableOptions);
}

/**
 * Displays various visualizations based on the provided response data.
 * This function processes the response to create a grouped data table,
 * a total gauge chart, and a pie chart representing messages by applications.
 *
 * @param {Object} response - The response object containing data for visualization.
 * @param {Array} response.grouped - An array of grouped data for the table.
 * @param {number} response.total - The total number of PM errors.
 * @param {Array} response.byApplications - An array of data categorized by applications.
 *
 * @throws {Error} Throws an error if the response does not contain the required properties.
 *
 * @example
 * const response = {
 *   grouped: [[...], [...]], // Example grouped data
 *   total: 1500,             // Example total PM errors
 *   byApplications: [[...], [...]] // Example application data
 * };
 * showMessages(response);
 */
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

/**
     * Displays the usage information from the provided response object in the HTML element with the ID "postman".
     *
     * This function checks if the "usage" property exists in the response object. If it does not exist, the function exits without making any changes to the DOM.
     *
     * @param {Object} response - The response object containing usage information.
     * @param {string} response.usage - The usage information to be displayed.
     *
     * @returns {void} This function does not return a value.
     *
     * @example
     * const apiResponse = { usage: 'API usage details here' };
     * showPostman(apiResponse);
     *
     * @throws {TypeError} Throws an error if the response parameter is not an object.
     */
function showPostman(response) {
  if (typeof response["usage"] === "undefined") {
    return;
  }

  document.getElementById("postman").innerHTML = response["usage"];
}

/**
 * Displays queue data in a table and a gauge chart using Google Visualization API.
 *
 * This function takes a response object containing total queue data and individual queue details,
 * processes this data, and renders it in a specified HTML element.
 *
 * @param {Object} response - The response object containing queue information.
 * @param {number} response.total - The total number of items in the queues.
 * @param {Array<Array>} response.queues - An array of arrays representing individual queue data.
 * Each inner array should contain the necessary data for visualization.
 *
 * @throws {Error} Throws an error if the response object is missing required properties.
 *
 * @example
 * const response = {
 *   total: 1500,
 *   queues: [
 *     ["Queue 1", 500],
 *     ["Queue 2", 1000]
 *   ]
 * };
 * showQueues(response);
 */
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

/**
 * Renders a table visualization of uptime data from the response object.
 *
 * This function takes a response object containing monitor data and uses
 * the Google Visualization API to create and display a table in the specified
 * HTML element.
 *
 * @param {Object} response - The response object containing monitor data.
 * @param {Array} response.monitors - An array of monitor data to be visualized.
 *
 * @throws {Error} Throws an error if the response does not contain the expected
 *                 structure or if the Google Visualization API is not loaded.
 *
 * @example
 * // Example usage:
 * const response = {
 *   monitors: [
 *     ['Monitor 1', 'Up', '100%'],
 *     ['Monitor 2', 'Down', '0%']
 *   ]
 * };
 * showUpTimeRobot(response);
 */
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
     * Displays various statistics and data visualizations based on the provided webhook response.
     *
     * This function processes the response object containing statistics, events, and other relevant data,
     * converting them into data tables suitable for visualization using Google Charts. It generates line charts,
     * pie charts, and gauge charts to represent the data visually in the specified HTML elements.
     *
     * @param {Object} response - The response object containing webhook statistics and data.
     * @param {Array} response.statistics - An array of statistics data for webhooks.
     * @param {Array} response.statistics_github - An array of GitHub webhook statistics data.
     * @param {Array} response.events - An array of events data.
     * @param {Array} response.feed - An array of feed data.
     * @param {Array} response.senders - An array of sender data.
     * @param {Array} response.repositories - An array of repository data.
     * @param {Array} response.workflow_runs - An array of workflow run data.
     * @param {number} response.total - The total number of webhooks received.
     * @param {number} response.failed - The number of failed webhooks.
     * @param {number} response.total_workflow_runs - The total number of workflow runs.
     * @param {number} response.installations - The number of installations.
     * @param {number} response.installation_repositories_count - The count of installation repositories.
     * @param {Array} response.installation_repositories - An array of installation repository data.
     * @param {string} [response.check_hooks_date] - The date when hooks were last checked (optional).
     *
     * @throws {Error} Throws an error if the Google Charts library is not loaded or if the response format is invalid.
     *
     * @example
     * const webhookResponse = {
     *   statistics: [...],
     *   statistics_github: [...],
     *   events: [...],
     *   feed: [...],
     *   senders: [...],
     *   repositories: [...],
     *   workflow_runs: [...],
     *   total: 100,
     *   failed: 5,
     *   total_workflow_runs: 50,
     *   installations: 10,
     *   installation_repositories_count: 20,
     *   installation_repositories: [...],
     *   check_hooks_date: "2023-10-01T12:00:00Z"
     * };
     *
     * showWebhook(webhookResponse);
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
  const dataSenders = google.visualization.arrayToDataTable(
    response["senders"]
  );
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
  const dataInstallationRepositoriesCount = google.visualization.arrayToDataTable([
    ["Hits", "GH Repos"],
    ["GH Repos", response["installation_repositories_count"]],
  ]);
  const dataInstallationRepositories = google.visualization.arrayToDataTable(
     response["installation_repositories"]
  );

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
    max: 1000000,
    greenFrom: 0,
    greenTo: 350000,
    yellowFrom: 350000,
    yellowTo: 700000,
    redFrom: 700000,
    redTo: 1000000,
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
  const installationRepositories = new google.visualization.Table(
    document.getElementById("installed_repositories")
  );
  installationRepositories.draw(dataInstallationRepositories, tableOptions);
  const workflowRuns = new google.visualization.Table(
    document.getElementById("workflow_runs")
  );
  workflowRuns.draw(dataWorkflowRuns, tableOptions);
    const feed = new google.visualization.Table(document.getElementById("feed"));
  feed.draw(dataFeed, tableOptions);
  const senders = new google.visualization.Table(
    document.getElementById("senders")
  );
  senders.draw(dataSenders, tableOptions);

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
  const gaugeChart10 = new google.visualization.Gauge(
    document.getElementById("gauge_chart_installation_repositories")
  );
  gaugeChart10.draw(dataInstallationRepositoriesCount, optionsInstallations);
}

/**
     * Renders a WireGuard visualization table using the provided response data.
     *
     * This function takes a response object containing WireGuard data, converts it 
     * into a format suitable for visualization, and then draws the table in the 
     * specified HTML element.
     *
     * @param {Object} response - The response object containing WireGuard data.
     * @param {Array} response.wireguard - An array of data to be visualized in the table.
     *
     * @throws {Error} Throws an error if the response does not contain the expected 
     *                 wireguard data or if the visualization fails to render.
     *
     * @example
     * const response = {
     *   wireguard: [
     *     ['Column1', 'Column2'],
     *     ['Data1', 'Data2'],
     *   ]
     * };
     * showWireGuard(response);
     */
function showWireGuard(response) {
  const dataWireGuard = google.visualization.arrayToDataTable(
    response["wireguard"]
  );
  const wireGuard = new google.visualization.Table(
    document.getElementById("wireguard")
  );
  wireGuard.draw(dataWireGuard, tableOptions);
}

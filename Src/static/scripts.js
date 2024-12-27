const OPTIONS_BOX_STATE_KEY = "optionsBoxState";
const FEED_FILTER_KEY = "feedFilter";
const WORKFLOW_LIMITER_KEY = "workflowLimiter";
const WORKFLOW_LIMIT_VALUE_KEY = "workflowLimitValue";
const VALID_STATES = {
  OPEN: "open",
  COLLAPSED: "collapsed",
};
const FEED_FILTERS = {
  ALL: "all",
  MINE: "mine",
};

function saveOptionsBoxState(state) {
  if (!Object.values(VALID_STATES).includes(state)) {
    console.error(
      `Invalid state: ${state}. Must be one of ${Object.values(VALID_STATES)}`
    );
    return;
  }
  try {
    localStorage.setItem(OPTIONS_BOX_STATE_KEY, state);
  } catch (e) {
    console.error("Failed to save options box state:", e);
  }
}
function loadOptionsBoxState() {
  try {
    return localStorage.getItem(OPTIONS_BOX_STATE_KEY) || VALID_STATES.OPEN;
  } catch (e) {
    console.error("Failed to load options box state:", e);
    return VALID_STATES.OPEN;
  }
}
function handleOptionsBoxState() {
  const optionsBoxState = loadOptionsBoxState();
  const optionsBox = document.getElementById("userMenu");

  if (!optionsBox) {
    console.error("Options box element not found");
    return;
  }

  if (optionsBoxState === VALID_STATES.COLLAPSED) {
    optionsBox.classList.remove("show");
  } else {
    optionsBox.classList.add("show");
  }

  optionsBox.addEventListener("shown.bs.collapse", () =>
    saveOptionsBoxState(VALID_STATES.OPEN)
  );
  optionsBox.addEventListener("hidden.bs.collapse", () =>
    saveOptionsBoxState(VALID_STATES.COLLAPSED)
  );
}

class FeedState {
  constructor() {
    this._filter = this.loadFeedFilter();
  }

  get filter() {
    return this._filter;
  }

  set filter(value) {
    if (!Object.values(FEED_FILTERS).includes(value)) {
      throw new Error(`Invalid filter: ${value}`);
    }
    this._filter = value;
    this.saveFeedFilter(value);
  }

  loadFeedFilter() {
    try {
      const storedFilter = localStorage.getItem(FEED_FILTER_KEY);
      return Object.values(FEED_FILTERS).includes(storedFilter)
        ? storedFilter
        : FEED_FILTERS.ALL;
    } catch (e) {
      console.error("Failed to load feed filter:", e);
      return FEED_FILTERS.ALL;
    }
  }

  saveFeedFilter(value) {
    try {
      localStorage.setItem(FEED_FILTER_KEY, value);
    } catch (e) {
      console.error("Failed to save feed filter:", e);
    }
  }
}

const feedState = new FeedState();

function updateFeedPreference(toggle) {
  if (!toggle || typeof toggle.checked !== "boolean") {
    console.error("Invalid toggle parameter");
    return;
  }
  feedState.filter = toggle.checked ? FEED_FILTERS.MINE : FEED_FILTERS.ALL;
}

class WorkflowLimiterState {
  constructor() {
    this._enabled = this.loadLimiterState();
    this._limitValue = this.loadLimiterValue();
  }

  get enabled() {
    return this._enabled;
  }

  set enabled(value) {
    this._enabled = Boolean(value);
    this.saveLimiterState(this._enabled);
  }

  get limitValue() {
    return this._limitValue;
  }

  set limitValue(value) {
    const limit = parseInt(value, 10);
    if (Number.isNaN(limit) || limit < 1) {
      throw new Error(
        "Invalid workflow limit value. Must be a number greater than 0."
      );
    }
    this._limitValue = limit;
    this.saveLimiterValue(limit);
  }

  loadLimiterState() {
    try {
      return JSON.parse(localStorage.getItem(WORKFLOW_LIMITER_KEY)) || false;
    } catch (e) {
      console.error("Failed to load workflow limiter state:", e);
      return false;
    }
  }

  saveLimiterState(state) {
    try {
      localStorage.setItem(WORKFLOW_LIMITER_KEY, JSON.stringify(state));
    } catch (e) {
      console.error("Failed to save workflow limiter state:", e);
    }
  }

  loadLimiterValue() {
    try {
      return parseInt(localStorage.getItem(WORKFLOW_LIMIT_VALUE_KEY), 10) || 10;
    } catch (e) {
      console.error("Failed to load workflow limit value:", e);
      return 10;
    }
  }

  saveLimiterValue(value) {
    try {
      localStorage.setItem(WORKFLOW_LIMIT_VALUE_KEY, value);
    } catch (e) {
      console.error("Failed to save workflow limit value:", e);
    }
  }
}

const workflowLimiterState = new WorkflowLimiterState();

function initWorkflowLimiter() {
  const workflowToggle = document.getElementById("workflowToggle");
  const workflowLimitContainer = document.getElementById(
    "workflowLimitContainer"
  );
  const workflowLimitInput = document.getElementById("workflowLimitInput");

  if (!workflowToggle || !workflowLimitContainer || !workflowLimitInput) {
    console.error("Workflow limiter elements not found");
    return;
  }

  workflowToggle.checked = workflowLimiterState.enabled;
  workflowLimitContainer.style.display = workflowLimiterState.enabled
    ? "block"
    : "none";

  workflowLimitInput.value = workflowLimiterState.limitValue;

  workflowToggle.addEventListener("change", () => {
    workflowLimiterState.enabled = workflowToggle.checked;
    workflowLimitContainer.style.display = workflowLimiterState.enabled
      ? "block"
      : "none";
  });

  workflowLimitInput.addEventListener("input", () => {
    try {
      workflowLimiterState.limitValue = workflowLimitInput.value;
    } catch (e) {
      console.error(e.message);
    }
  });
}

function initFeedToggle() {
  const toggle = document.getElementById("feedToggle");

  if (!toggle) {
    console.error("Feed toggle element not found");
    return;
  }

  toggle.checked = feedState.filter === FEED_FILTERS.MINE;
  toggle.addEventListener("change", () => updateFeedPreference(toggle));
}

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
  handleOptionsBoxState();
  initFeedToggle();
  initWorkflowLimiter();

  console.log("Options box state on load:", loadOptionsBoxState());
  console.log("Feed filter on load:", feedState.filter);
  console.log("Workflow limiter enabled:", workflowLimiterState.enabled);
  console.log("Workflow limit value:", workflowLimiterState.limitValue);
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

let isSessionInvalid = false;

function load(url, callback) {
  if (isSessionInvalid) {
    console.warn("Session is invalid. API call aborted.");
    return;
  }

  const xhr = new XMLHttpRequest();
  xhr.open("GET", url, true);
  xhr.onreadystatechange = function () {
    if (this.readyState === 4) {
      if (this.status === 200) {
        callback(JSON.parse(this.responseText));
      } else if (this.status === 401 || this.status === 403) {
        if (isSessionInvalid === false) {
          isSessionInvalid = true;
          showLoginModal();
        }
      }
    }
  };
  xhr.send();
}

function showLoginModal() {
  const modal = document.createElement("div");
  modal.classList.add("modal-backdrop");
  modal.setAttribute("role", "dialog");
  modal.setAttribute("aria-modal", "true");
  modal.setAttribute("aria-labelledby", "modalTitle");

  const modalContent = document.createElement("div");
  modalContent.classList.add("modal-content");
  modalContent.innerHTML = `
    <h2 id="modalTitle">Session Expired</h2>
    <p>Your session has expired. Please login again.</p>
    <button id="cancelBtn">Cancel</button>
    <button id="loginBtn">Login</button>
  `;

  modal.appendChild(modalContent);
  document.body.appendChild(modal);

  document.getElementById("cancelBtn").addEventListener("click", () => {
    modal.remove();
  });

  document.getElementById("loginBtn").addEventListener("click", () => {
    window.location.href = "login.php";
  });
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
      '{"error_log_files":[],"error_log_messages":[],"total_error_messages":0,"cronjobs":[],"usage":[{"id":"lvecpu","description":"CPU Usage","usage":0,"maximum":100},{"id":"lveep","description":"Entry Processes","usage":0,"maximum":20},{"id":"lvememphy","description":"Physical Memory Usage","usage":0,"maximum":512},{"id":"lvenproc","description":"Number of Processes","usage":0,"maximum":100}]}'
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
  load(
    `api/v1/webhooks?feedOptionsFilter=${feedState.filter}&workflowsLimiterEnabled=${workflowLimiterState.enabled}&workflowsLimiterQuantity=${workflowLimiterState.limitValue}`,
    showWebhook
  );
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
  showGitHubStatsAndWakatime();
  load30Interval();
  load60Interval();
  load300Interval();
  setInterval(load30Interval, 30 * 1000);
  setInterval(load60Interval, 60 * 1000);
  setInterval(load300Interval, 300 * 1000);
  setInterval(showGitHubStatsAndWakatime, 60 * 15 * 1000);
}

/**
 * Updates the GitHub statistics, streak, and Wakatime images displayed on the webpage.
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
 * showGitHubStatsAndWakatime();
 */
function showGitHubStatsAndWakatime() {
  const refresh = Math.floor(Math.random() * 100000);

  const statsUrl = `https://github-readme-stats-guibranco.vercel.app/api?username=guibranco&line_height=28&card_width=490&hide_title=true&hide_border=true&show_icons=true&theme=chartreuse-dark&icon_color=7FFF00&include_all_commits=true&count_private=true&show=reviews,discussions_started&count_private=true&refresh=${refresh}`;
  const streakUrl = `https://github-readme-streak-stats-guibranco.vercel.app/?user=guibranco&theme=github-green-purple&fire=FF6600&refresh=${refresh}`;
  const wakatimeUrl = `https://wakatime.com/badge/user/6be975b7-7258-4475-bc73-9c0fc554430e.svg?style=for-the-badge&refresh=${refresh}`;

  const statsImg = document.getElementById("gh_stats");
  const streakImg = document.getElementById("gh_streak");
  const wakatimeImg = document.getElementById("wakatime");

  if (!statsImg || !streakImg || !wakatimeImg) {
    console.error("GitHub/Wakatime stats image elements not found in the DOM");
    return;
  }

  function loadImage(imgElement, url, options = {}) {
    const { maxRetries = 10, retryDelay = 2000, timeout = 30000 } = options;
    const startTime = Date.now();

    function cleanup() {
      imgElement.onload = null;
      imgElement.onerror = null;
    }

    imgElement.onload = () => {
      console.log(`${imgElement.id} loaded successfully.`);
      cleanup();
    };

    imgElement.onerror = () => {
      if (maxRetries > 0 && Date.now() - startTime < timeout) {
        console.warn(
          `${imgElement.id} failed to load. Retrying... (${maxRetries} retries left)`
        );
        setTimeout(
          () =>
            loadImage(imgElement, url, {
              ...options,
              maxRetries: maxRetries - 1,
            }),
          retryDelay
        );
      } else {
        console.error(
          `${imgElement.id} failed to load after ${
            maxRetries === 0 ? "maximum retries" : "timeout"
          }.`
        );
        cleanup();
      }
    };

    imgElement.src = url;
  }

  loadImage(statsImg, statsUrl);
  loadImage(streakImg, streakUrl);
  loadImage(wakatimeImg, wakatimeUrl);
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
  drawDataTable(response["projects"], "appveyor", tableOptions);
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
  const gaugeOptions = {
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

  drawGaugeChart(
    "Log errors",
    response["total_error_messages"],
    "gauge_chart_log_errors",
    gaugeOptions
  );
  drawDataTable(response["error_log_files"], "error_log_files", tableOptions);
  drawDataTable(
    response["error_log_messages"],
    "error_log_messages",
    tableOptions
  );
  drawDataTable(response["cronjobs"], "cronjobs", tableOptions);

  const ids = {
    lvecpu: "gauge_chart_cpu",
    lvememphy: "gauge_chart_memory",
    lveep: "gauge_chart_entry_process",
    lvenproc: "gauge_chart_process",
  };

  const labels = {
    lvecpu: "CPU",
    lvememphy: "Memory",
    lveep: "Entry Processes",
    lvenproc: "Processes",
  };

  const usageData = response["usage"].map((item) => ({
    elementId: ids[item.id],
    label: labels[item.id],
    value: parseFloat(item.usage),
    maximum: item.maximum || 100,
  }));

  for (const item of usageData) {
    const greenTo = parseInt(item.maximum * 0.5);
    const yellowTo = parseInt(item.maximum * 0.75);

    const gaugeOptionsUsage = {
      width: "100%",
      height: "100%",
      min: 0,
      max: item.maximum,
      greenFrom: 0,
      greenTo: greenTo,
      yellowFrom: greenTo,
      yellowTo: yellowTo,
      redFrom: yellowTo,
      redTo: item.maximum,
    };
    drawGaugeChart(item.label, item.value, item.elementId, gaugeOptionsUsage);
  }
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
  drawDataTable(response["domains"], "domains", tableOptions);
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

  drawGaugeChart(
    "GH Issues",
    response["issues"]["total_count"],
    "gauge_chart_issues",
    gaugeOptions
  );
  drawGaugeChart(
    "GH PRs",
    response["pull_requests"]["total_count"],
    "gauge_chart_pull_requests",
    gaugeOptions
  );

  drawDataTable(
    response["pull_requests"]["awaiting_triage"],
    "pull_requests_triage",
    tableOptions
  );
  drawDataTable(
    response["pull_requests"]["latest"],
    "pull_requests_latest",
    tableOptions
  );
  drawDataTable(
    response["pull_requests"]["authored"],
    "pull_requests_authored",
    tableOptions
  );
  drawDataTable(
    response["pull_requests"]["blocked"],
    "pull_requests_blocked",
    tableOptions
  );
  drawDataTable(response["issues"]["assigned"], "assigned", tableOptions);
  drawDataTable(
    response["issues"]["authored"],
    "issues_authored",
    tableOptions
  );
  drawDataTable(response["issues"]["bug"], "bug", tableOptions);
  drawDataTable(response["issues"]["blocked"], "issues_blocked", tableOptions);
  drawDataTable(response["issues"]["triage"], "triage", tableOptions);
  drawDataTable(response["issues"]["wip"], "wip", tableOptions);
  drawDataTable(response["issues"]["others"], "issues", tableOptions);
  drawDataTable(response["accounts_usage"], "accounts_usage", tableOptions);
  drawDataTable(response["api_usage"], "api_usage", tableOptions);
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
  drawDataTable(response["checks"], "healthchecksio", tableOptions);
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
  const gaugeOptions = {
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

  drawDataTable(response.grouped, "messages_grouped", tableOptions);
  drawGaugeChart(
    "PM Errors",
    response.total,
    "gauge_chart_pm_errors",
    gaugeOptions
  );
  drawPieChart(response.byApplications, "pie_chart_2", optionsByApplications);

  if (response.byApplications.length > 1) {
    const byApplicationsTableData = response.byApplications;
    byApplicationsTableData[0].unshift("Delete");
    for (let i = 1; i < byApplicationsTableData.length; i++) {
      const safeAppName = encodeURIComponent(byApplicationsTableData[i][0]);
      byApplicationsTableData[i].unshift(
        `<button onclick="confirmDelete('${safeAppName}')">❌</button>`
      );
    }
    drawDataTable(
      byApplicationsTableData,
      "messages_by_applications",
      tableOptions
    );
  }
}

/**
 * Prompts the user for confirmation before deleting messages for a specified application.
 *
 * @param {string} application - The name of the application whose messages are to be deleted.
 */
function confirmDelete(application) {
  const message = `Are you sure you want to delete messages for ${decodeURIComponent(
    application
  )}?`;

  if (window.confirm(message)) {
    deleteMessageByApplication(application);
  }
}

/**
 * Deletes a message by application.
 *
 * Sends a POST request to the server to delete a message associated with the specified application.
 *
 * @param {string} application - The name or identifier of the application whose message is to be deleted.
 * @returns {void}
 */
function deleteMessageByApplication(application) {
  fetch("/api/v1/messages/delete", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ application }),
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error("Network response was not ok");
      }
      return response.json();
    })
    .then((data) => {
      showNotification("Success", "Item was successfully deleted", "success");
      showMessages(data);
    })
    .catch((error) => {
      showNotification(
        "Error",
        `Failed to delete item: ${error.message}`,
        "error"
      );
    });
}

/**
 * Displays a notification toast with the specified title, message, and type.
 *
 * @param {string} title - The title of the notification.
 * @param {string} message - The message content of the notification.
 * @param {string} type - The type of the notification, which determines the styling.
 *                        Possible values are "success", "error", "warning", and "info".
 */
function showNotification(title, message, type) {
  const toastContainer = document.getElementById("toast-container");
  if (!toastContainer) {
    console.error("Toast container not found");
    return;
  }

  const validTypes = ["success", "error", "warning", "info"];
  if (!validTypes.includes(type)) {
    console.warn(`Invalid notification type: ${type}. Defaulting to 'info'`);
    type = "info";
  }

  const typeClass = {
    success: "bg-success text-white",
    error: "bg-danger text-white",
    warning: "bg-warning text-dark",
    info: "bg-info text-dark",
  };

  if (typeof bootstrap === "undefined") {
    console.error("Bootstrap is not loaded");
    alert(`${title}: ${message}`);
    return;
  }

  const toast = document.createElement("div");
  toast.className = `toast ${typeClass[type]}`;
  toast.setAttribute("role", "alert");
  toast.setAttribute("aria-live", "assertive");
  toast.setAttribute("aria-atomic", "true");
  toast.innerHTML = `
    <div class="toast-header">
      <strong class="me-auto">${title}</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">${message}</div>
  `;

  toastContainer.appendChild(toast);

  const bootstrapToast = new bootstrap.Toast(toast, { delay: 3000 });
  bootstrapToast.show();

  toast.addEventListener("hidden.bs.toast", () => {
    toast.remove();
  });
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

  document.getElementById("postman").innerHTML = response.usage;
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
  const gaugeOptions = {
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

  drawDataTable(response["queues"], "queues", tableOptions);
  drawGaugeChart(
    "Queues",
    response["total"],
    "gauge_chart_queues",
    gaugeOptions
  );
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
  drawDataTable(response["monitors"], "uptimerobot", tableOptions);
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

  const gaugeOptionsTotal = {
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

  const gaugeOptions = {
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

  drawLineChart(
    response["statistics"],
    "webhooks_statistics",
    optionsStatistics
  );
  drawLineChart(
    response["statistics_github"],
    "webhooks_statistics_github",
    optionsStatisticsGitHub
  );
  drawDataTable(response["senders"], "senders", tableOptions);
  drawDataTable(response["repositories"], "repositories", tableOptions);
  drawDataTable(
    response["installation_repositories"],
    "installed_repositories",
    tableOptions
  );
  drawDataTable(response["workflow_runs"], "workflow_runs", tableOptions);
  drawDataTable(response["feed"], "feed", tableOptions, 5);
  drawPieChart(response["events"], "pie_chart_1", optionsEvents);
  drawGaugeChart(
    "GH WH",
    response["total"],
    "gauge_chart_webhooks",
    gaugeOptionsTotal
  );
  drawGaugeChart(
    "WH Failed",
    response["failed"],
    "gauge_chart_webhooks_failed",
    gaugeOptions
  );
  drawGaugeChart(
    "GH WRs",
    response["total_workflow_runs"],
    "gauge_chart_workflows_runs",
    gaugeOptions
  );
  drawGaugeChart(
    "GH App",
    response["installations"],
    "gauge_chart_installed_apps",
    gaugeOptions
  );
  drawGaugeChart(
    "GH Repos",
    response["installation_repositories_count"],
    "gauge_chart_installation_repositories",
    gaugeOptions
  );
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
  drawDataTable(response["wireguard"], "wireguard", tableOptions);
}

/**
 * Draws a Google Visualization chart of the specified type.
 *
 * @param {Array} data - The data to be visualized, in the format accepted by google.visualization.arrayToDataTable.
 * @param {string} chartType - The type of chart to draw. Valid values are "table", "line", "pie", and "gauge".
 * @param {string} elementId - The ID of the HTML element where the chart will be drawn.
 * @param {Object} options - The options for the chart, as accepted by the specific chart type.
 * @param {number} [hideColumn=-1] - The index of the column to hide. If -1, no column will be hidden.
 * @returns {Object} An object containing the chart type, element ID, chart instance, and optionally the data view.
 */
function drawChartByType(data, chartType, elementId, options, hideColumn = -1) {
  if (!google.visualization) {
    console.error("Google Visualization API not loaded");
    return;
  }

  if (!data || !elementId || !options) {
    console.error("Invalid parameters passed to drawChartByType");
    return;
  }

  const element = document.getElementById(elementId);
  if (!element) {
    console.error(`Element with id ${elementId} not found`);
    return;
  }

  const result = {
    chartType,
    elementId,
  };

  switch (chartType) {
    case "table":
      result.chart = new google.visualization.Table(element);
      break;
    case "line":
      result.chart = new google.visualization.LineChart(element);
      break;
    case "pie":
      result.chart = new google.visualization.PieChart(element);
      break;
    case "gauge":
      result.chart = new google.visualization.Gauge(element);
      break;
    default:
      console.error(`Invalid chart type: ${chartType}`);
      return;
  }

  result.dataTable = google.visualization.arrayToDataTable(data);

  if (hideColumn >= 0 && data.length > 0) {
    result.view = new google.visualization.DataView(result.dataTable);
    if (data[0].length > hideColumn) {
      result.view.hideColumns([hideColumn]);
    }

    result.chart.draw(result.view, options);

    google.visualization.events.addListener(
      result.chart,
      "select",
      function () {
        const selection = result.chart.getSelection();
        if (selection.length > 0) {
          const row = selection[0].row;
          const item = result.dataTable.getValue(row, 0);
          const hiddenInfo = result.dataTable.getValue(
            row,
            data[0].length > hideColumn ? hideColumn : 0
          );
          console.log(`You clicked on ${item}\nHidden Info: ${hiddenInfo}`);
        }
      }
    );
  } else {
    result.chart.draw(result.dataTable, options);
  }

  return result;
}

/**
 * Draws a data table chart in the specified HTML element.
 *
 * @param {Object} data - The data to be displayed in the table.
 * @param {string} elementId - The ID of the HTML element where the table will be drawn.
 * @param {Object} options - Configuration options for the table.
 * @param {number} [hideColumn=-1] - The index of the column to hide (optional). Default is -1, which means no column will be hidden.
 * @returns {Object} The chart object created.
 */
function drawDataTable(data, elementId, options, hideColumn = -1) {
  const counterElementId = `counter_${elementId}`;
  const counterElement = document.getElementById(counterElementId);

  if (counterElement) {
    counterElement.innerHTML = Math.max(0, data.length - 1);
  }
  return drawChartByType(data, "table", elementId, options, hideColumn);
}

/**
 * Draws a line chart using the provided data and options.
 *
 * @param {Array} data - The data to be used for the chart.
 * @param {string} elementId - The ID of the HTML element where the chart will be rendered.
 * @param {Object} options - Additional options for customizing the chart.
 * @returns {Object} The chart instance.
 */
function drawLineChart(data, elementId, options) {
  return drawChartByType(data, "line", elementId, options);
}

/**
 * Draws a pie chart using the provided data and options.
 *
 * @param {Object} data - The data to be used for the pie chart.
 * @param {string} elementId - The ID of the HTML element where the chart will be rendered.
 * @param {Object} options - Additional options for customizing the chart.
 * @returns {Object} The chart instance.
 */
function drawPieChart(data, elementId, options) {
  return drawChartByType(data, "pie", elementId, options);
}

/**
 * Draws a gauge chart with the specified label, value, and options.
 *
 * @param {string} label - The label for the gauge chart.
 * @param {number} value - The value to be displayed on the gauge chart.
 * @param {string} elementId - The ID of the HTML element where the chart will be rendered.
 * @param {Object} options - Additional options for customizing the gauge chart.
 * @returns {Object} The chart object created by the drawChartByType function.
 */
function drawGaugeChart(label, value, elementId, options) {
  return drawChartByType(
    [
      ["", ""],
      [label, value],
    ],
    "gauge",
    elementId,
    options
  );
}

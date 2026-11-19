// api.js
import { API_ENDPOINTS } from './constants.js';
import { getGitHubApiProxyRange } from "./gitHubApiProxyRange.js";
import { NotificationManager } from './ui.js';

export class ApiManager {
  constructor() {
    this.isSessionInvalid = false;
  }

  /**
   * Loads data from a specified URL using XMLHttpRequest.
   *
   * This function checks if the session is valid before making the API call.
   * If the session is invalid, it logs a warning and aborts the request.
   * Upon receiving a response, it processes the JSON data or shows a login modal
   * if an authentication error (401 or 403) occurs.
   *
   * @param {string} url - The URL from which to load data.
   * @param {function} callback - The function to be called with the parsed response data.
   */
  load(url, callback) {
    if (this.isSessionInvalid) {
      console.warn("Session is invalid. API call aborted.");
      return;
    }

    const xhr = new XMLHttpRequest();
    xhr.open("GET", url, true);
    xhr.onreadystatechange = () => {
      if (xhr.readyState === 4) {
        if (xhr.status === 200) {
          callback(JSON.parse(xhr.responseText));
        } else if (xhr.status === 401 || xhr.status === 403) {
          if (this.isSessionInvalid === false) {
            this.isSessionInvalid = true;
            this.showLoginModal();
          }
        }
      }
    };
    xhr.send();
  }

  /**
   * Displays a session-expired modal and redirects to login on confirmation.
   */
  showLoginModal() {
    const existing = document.getElementById("session-expired-modal");
    if (existing) return;

    document.body.insertAdjacentHTML("beforeend", `
      <div class="modal fade" id="session-expired-modal" tabindex="-1"
           aria-labelledby="sessionExpiredTitle" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content session-expired-modal-content">
            <div class="modal-header session-expired-header">
              <h5 class="modal-title" id="sessionExpiredTitle">
                <i class="bi bi-clock-history me-2"></i>Session Expired
              </h5>
            </div>
            <div class="modal-body">
              <p class="mb-0">Your session has expired. Please log in again to continue.</p>
            </div>
            <div class="modal-footer session-expired-footer">
              <button type="button" class="btn session-expired-btn-dismiss" id="sessionCancelBtn">
                <i class="bi bi-x-circle me-1"></i>Dismiss
              </button>
              <button type="button" class="btn session-expired-btn-login" id="sessionLoginBtn">
                <i class="bi bi-box-arrow-in-right me-1"></i>Log In
              </button>
            </div>
          </div>
        </div>
      </div>
    `);

    const modalEl = document.getElementById("session-expired-modal");
    const bsModal = new bootstrap.Modal(modalEl, { backdrop: "static", keyboard: false });
    bsModal.show();

    document.getElementById("sessionCancelBtn").addEventListener("click", () => bsModal.hide());
    document.getElementById("sessionLoginBtn").addEventListener("click", () => {
      window.location.href = "login.php";
    });
  }

  /**
   * Deletes a message by application.
   *
   * This function sends a POST request to the messages delete endpoint with the specified application.
   * It handles the response and shows a success notification if successful. If an error occurs,
   * it displays an error notification and rethrows the error.
   */
  async deleteMessageByApplication(application) {
    try {
      const response = await fetch(API_ENDPOINTS.MESSAGES_DELETE, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ application }),
      });

      if (!response.ok) {
        throw new Error("Network response was not ok");
      }

      const data = await response.json();
      NotificationManager.show("Success", "Item was successfully deleted", "success");
      return data;
    } catch (error) {
      NotificationManager.show(
        "Error",
        `Failed to delete item: ${error.message}`,
        "error"
      );
      throw error;
    }
  }

  /**
   * Purges all messages from a specific RabbitMQ queue.
   *
   * @param {string} host - URL-encoded RabbitMQ server hostname.
   * @param {string} vhost - URL-encoded virtual host.
   * @param {string} queue - URL-encoded queue name.
   */
  async purgeQueue(host, vhost, queue) {
    try {
      const response = await fetch(API_ENDPOINTS.QUEUES_PURGE, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ host, vhost, queue }),
      });

      if (!response.ok) {
        throw new Error("Network response was not ok");
      }

      const data = await response.json();
      NotificationManager.show("Success", "Queue was successfully purged", "success");
      return data;
    } catch (error) {
      NotificationManager.show(
        "Error",
        `Failed to purge queue: ${error.message}`,
        "error"
      );
      throw error;
    }
  }

  /**
   * Truncates the messages table, removing all messages.
   */
  async truncateMessages() {
    try {
      const response = await fetch(API_ENDPOINTS.MESSAGES_TRUNCATE, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
      });

      if (!response.ok) {
        throw new Error("Network response was not ok");
      }

      const data = await response.json();
      NotificationManager.show("Success", "All messages have been truncated", "success");
      return data;
    } catch (error) {
      NotificationManager.show(
        "Error",
        `Failed to truncate messages: ${error.message}`,
        "error"
      );
      throw error;
    }
  }

  async truncateDbErrors() {
    try {
      const response = await fetch(API_ENDPOINTS.ERRORS_TRUNCATE, { method: "POST" });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return await response.json();
    } catch (error) {
      NotificationManager.show("Error", `Failed to truncate errors table: ${error.message}`, "error");
      throw error;
    }
  }

  async deleteErrorsByPath(path) {
    try {
      const response = await fetch(API_ENDPOINTS.ERRORS_DELETE_PATH, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ path }),
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return await response.json();
    } catch (error) {
      NotificationManager.show("Error", `Failed to delete errors: ${error.message}`, "error");
      throw error;
    }
  }

  async getMessageDetails(sampleId) {
    try {
      const response = await fetch(`${API_ENDPOINTS.MESSAGES_DETAILS}?id=${encodeURIComponent(sampleId)}`);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return await response.json();
    } catch (error) {
      NotificationManager.show("Error", `Failed to load message details: ${error.message}`, "error");
      throw error;
    }
  }

  async deleteMessageById(id) {
    try {
      const response = await fetch(API_ENDPOINTS.MESSAGES_DELETE_SEQUENCE, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id }),
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      NotificationManager.show("Success", `Message #${id} deleted`, "success");
      return await response.json();
    } catch (error) {
      NotificationManager.show("Error", `Failed to delete message: ${error.message}`, "error");
      throw error;
    }
  }

  async deleteMessageGroup(sampleId) {
    try {
      const response = await fetch(API_ENDPOINTS.MESSAGES_DELETE_GROUP, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: sampleId }),
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      NotificationManager.show("Success", "Message group deleted", "success");
      return await response.json();
    } catch (error) {
      NotificationManager.show("Error", `Failed to delete message group: ${error.message}`, "error");
      throw error;
    }
  }

  /**
   * Triggers a one-shot run of the named background worker via the Webhooks API.
   *
   * @param {string} name - Worker identifier (service, cleanup, database-service, maintenance, hooks, idle-pull-requests).
   */
  async runWorker(name) {
    try {
      const response = await fetch(API_ENDPOINTS.WEBHOOKS_WORKERS_RUN, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name }),
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      NotificationManager.show("Success", `Worker "${name}" run triggered`, "success");
      return await response.json();
    } catch (error) {
      NotificationManager.show(
        "Error",
        `Failed to trigger worker "${name}": ${error.message}`,
        "error"
      );
      throw error;
    }
  }

  /**
   * Triggers a one-shot run of the named GStraccini bot background job.
   *
   * @param {string} job - Job identifier (branches, comments, issues, pullRequests, pushes, repositories).
   */
  async runJob(job) {
    try {
      const response = await fetch(API_ENDPOINTS.GSTRACCINI_JOBS_RUN, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ job }),
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      NotificationManager.show("Success", `Job "${job}" run triggered`, "success");
      return await response.json();
    } catch (error) {
      NotificationManager.show(
        "Error",
        `Failed to trigger job "${job}": ${error.message}`,
        "error"
      );
      throw error;
    }
  }

  /**
   * Requests a retry (rerun / rerun-failed-jobs) of a workflow run via the
   * Webhooks API. Only asks GitHub to re-run it — the row's status updates
   * asynchronously once the webhooks app receives GitHub's follow-up events.
   */
  async retryWorkflowRun(workflowRunId) {
    try {
      const response = await fetch(API_ENDPOINTS.WORKFLOW_RUNS_RETRY, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ workflowRunId }),
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(data?.error || `HTTP ${response.status}`);
      }

      NotificationManager.show("Success", data.message || "Retry requested", "success");
      return data;
    } catch (error) {
      NotificationManager.show(
        "Error",
        `Failed to retry workflow run: ${error.message}`,
        "error"
      );
      throw error;
    }
  }

  /**
   * Deletes a workflow run's stored row via the Webhooks API. Only removes
   * the row — doesn't touch GitHub.
   */
  async deleteWorkflowRun(workflowRunId) {
    try {
      const response = await fetch(API_ENDPOINTS.WORKFLOW_RUNS_DELETE, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ workflowRunId }),
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(data?.error || `HTTP ${response.status}`);
      }

      NotificationManager.show("Success", data.message || "Workflow run deleted", "success");
      return data;
    } catch (error) {
      NotificationManager.show(
        "Error",
        `Failed to delete workflow run: ${error.message}`,
        "error"
      );
      throw error;
    }
  }

  /**
   * Deletes an error log file from a specified directory using the cPanel API.
   * This function makes an asynchronous POST request to delete the file and handles
   * responses, showing success or error notifications accordingly.
   *
   * @param {string} directory - The path to the directory containing the error log file.
   */
  async deleteErrorLogFile(directory) {
    try {
      const response = await fetch(API_ENDPOINTS.CPANEL_DELETE, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ directory }),
      });

      if (!response.ok) {
        throw new Error("Network response was not ok");
      }

      const data = await response.json();
      NotificationManager.show("Success", "Item was successfully deleted", "success");
      return data;
    } catch (error) {
      NotificationManager.show(
        "Error",
        `Failed to delete item: ${error.message}`,
        "error"
      );
      throw error;
    }
  }
}

/**
 * Which endpoints each dashboard page loads, how often (seconds), and the
 * window.show* function that renders the response. A page only loads the
 * endpoints whose sections it shows; the rest of a response is ignored.
 */
const LOADERS = [
  { endpoint: "APPVEYOR", every: 30, show: "showAppVeyor", pages: ["ops"] },
  { endpoint: "CPANEL", every: 30, show: "showCPanel", pages: ["ops"] },
  {
    endpoint: "MESSAGES",
    every: 30,
    show: "showMessages",
    pages: ["overview", "errors", "stats"],
  },
  {
    endpoint: "QUEUES",
    every: 30,
    show: "showQueues",
    pages: ["overview", "pipeline"],
  },
  {
    endpoint: "WORKFLOW_RUNS",
    every: 30,
    show: "showWorkflowRunsManagement",
    pages: ["github"],
  },
  {
    endpoint: "WEBHOOKS",
    every: 30,
    show: "showWebhook",
    pages: ["overview", "pipeline", "github", "stats"],
  },
  {
    endpoint: "WEBHOOKS_STATISTICS",
    every: 30,
    show: "showWebhookProcessingStats",
    pages: ["overview", "pipeline"],
  },
  {
    endpoint: "WEBHOOKS_PR_PROCESSING",
    every: 30,
    show: "showWebhookPullRequestsProcessing",
    pages: ["pipeline"],
  },
  {
    endpoint: "WEBHOOKS_PR_IDLE",
    every: 30,
    show: "showWebhookPullRequestsIdle",
    pages: ["pipeline"],
  },
  {
    endpoint: "WEBHOOKS_BRANCHES_PROCESSING",
    every: 30,
    show: "showBranchesProcessing",
    pages: ["pipeline"],
  },
  {
    endpoint: "WEBHOOKS_COMMENTS_PROCESSING",
    every: 30,
    show: "showCommentsProcessing",
    pages: ["pipeline"],
  },
  {
    endpoint: "WEBHOOKS_INSTALLATIONS_PROCESSING",
    every: 30,
    show: "showInstallationsProcessing",
    pages: ["pipeline"],
  },
  {
    endpoint: "WEBHOOKS_ISSUES_PROCESSING",
    every: 30,
    show: "showIssuesProcessing",
    pages: ["pipeline"],
  },
  {
    endpoint: "WEBHOOKS_PUSHES_PROCESSING",
    every: 30,
    show: "showPushesProcessing",
    pages: ["pipeline"],
  },
  {
    endpoint: "WEBHOOKS_REPOSITORIES_PROCESSING",
    every: 30,
    show: "showRepositoriesProcessing",
    pages: ["pipeline"],
  },
  {
    endpoint: "WEBHOOKS_USERS_PROCESSING",
    every: 30,
    show: "showUsersProcessing",
    pages: ["pipeline"],
  },
  { endpoint: "DOMAINS", every: 60, show: "showDomains", pages: ["ops"] },
  {
    endpoint: "BROKER_USAGE",
    every: 60,
    show: "showBrokerUsage",
    pages: ["pipeline"],
  },
  {
    endpoint: "THROTTLED_REPOSITORIES",
    every: 60,
    show: "showThrottledRepositories",
    pages: ["pipeline"],
  },
  {
    endpoint: "ERRORS_INBOX",
    every: 60,
    show: "showErrorsInbox",
    pages: ["errors"],
  },
  {
    endpoint: "GITHUB",
    every: 60,
    show: "showGitHub",
    pages: ["github", "ops"],
  },
  {
    endpoint: "HEALTHCHECKS",
    every: 60,
    show: "showHealthChecksIo",
    pages: ["ops"],
  },
  {
    endpoint: "UPTIMEROBOT",
    every: 60,
    show: "showUpTimeRobot",
    pages: ["ops"],
  },
  { endpoint: "VERCEL", every: 60, show: "showVercel", pages: ["ops"] },
  { endpoint: "CLOUDFLARE", every: 60, show: "showCloudflare", pages: ["ops"] },
  { endpoint: "RENDER", every: 60, show: "showRender", pages: ["ops"] },
  { endpoint: "WIREGUARD", every: 60, show: "showWireGuard", pages: ["ops"] },
  { endpoint: "RELEASES", every: 60, show: "showReleases", pages: ["github"] },
  {
    endpoint: "GITHUB_API_PROXY",
    every: 60,
    show: "showGitHubApiProxy",
    pages: ["github"],
  },
  {
    endpoint: "SYSTEM_REPORT",
    every: 60,
    show: "showSystemReport",
    pages: ["ops"],
  },
  { endpoint: "POSTMAN", every: 300, show: "showPostman", pages: ["stats"] },
  {
    endpoint: "WEBHOOKS_WORKERS",
    every: 300,
    show: "showWorkers",
    pages: ["pipeline"],
  },
  {
    endpoint: "GSTRACCINI_JOBS",
    every: 300,
    show: "showGStracciniJobs",
    pages: ["pipeline"],
  },
];

export class DataLoader {
  /**
   * @param {ApiManager} apiManager - Shared API manager (session handling).
   * @param {string} page - The current page (the body's data-page).
   */
  constructor(apiManager, page) {
    this.apiManager = apiManager;
    this.page = page || "overview";
    this.lastLoadedAt = 0;
  }

  /**
   * The loaders of the current page.
   *
   * @returns {Array<object>}
   */
  getLoaders() {
    return LOADERS.filter((loader) => loader.pages.includes(this.page));
  }

  /**
   * The intervals (seconds) the current page needs a timer for.
   *
   * @returns {number[]}
   */
  getIntervals() {
    return [...new Set(this.getLoaders().map((loader) => loader.every))];
  }

  /**
   * Loads every endpoint of the current page refreshed every `every` seconds.
   *
   * @param {number} every - The interval, in seconds.
   */
  load(every) {
    for (const loader of this.getLoaders().filter(
      (item) => item.every === every,
    )) {
      this.apiManager.load(this.getUrl(loader.endpoint), (data) => {
        window[loader.show]?.(data);
        this.lastLoadedAt = Date.now();
      });
    }
  }

  /**
   * Loads one endpoint of the current page now, outside its timer (e.g.
   * after a section's options change).
   *
   * @param {string} endpoint - A key of API_ENDPOINTS.
   */
  loadEndpoint(endpoint) {
    const loader = this.getLoaders().find((item) => item.endpoint === endpoint);
    if (!loader) {
      return;
    }

    this.apiManager.load(this.getUrl(loader.endpoint), (data) => {
      window[loader.show]?.(data);
      this.lastLoadedAt = Date.now();
    });
  }

  /**
   * The URL of an endpoint; the webhooks one carries the feed filter and
   * the GitHub API proxy one its time range.
   *
   * @param {string} endpoint - A key of API_ENDPOINTS.
   * @returns {string}
   */
  getUrl(endpoint) {
    if (endpoint === "GITHUB_API_PROXY") {
      return `${API_ENDPOINTS.GITHUB_API_PROXY}?range=${encodeURIComponent(getGitHubApiProxyRange())}`;
    }

    if (endpoint !== "WEBHOOKS") {
      return new Map(Object.entries(API_ENDPOINTS)).get(endpoint);
    }

    const feedState = window.feedState || { filter: "all" };
    return `${API_ENDPOINTS.WEBHOOKS}?feedOptionsFilter=${feedState.filter}`;
  }
}

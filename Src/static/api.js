// api.js
import { API_ENDPOINTS } from './constants.js';
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
   * @param {string} name - Worker identifier (service, cleanup, database-service, maintenance).
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
   * @param {string} job - Job identifier (branches, comments, issues, pullRequests, pushes, repositories, signature).
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

export class DataLoader {
  constructor(apiManager) {
    this.apiManager = apiManager;
  }

  /**
   * Loads data from various endpoints and displays it using corresponding window functions.
   */
  load30Interval() {
    this.apiManager.load(API_ENDPOINTS.APPVEYOR, (data) => window.showAppVeyor?.(data));
    this.apiManager.load(API_ENDPOINTS.CPANEL, (data) => window.showCPanel?.(data));
    this.apiManager.load(API_ENDPOINTS.MESSAGES, (data) => window.showMessages?.(data));
    this.apiManager.load(API_ENDPOINTS.QUEUES, (data) => window.showQueues?.(data));
    
    const feedState = window.feedState || { filter: 'all' };

    this.apiManager.load(
      `${API_ENDPOINTS.WEBHOOKS}?feedOptionsFilter=${feedState.filter}`,
      (data) => window.showWebhook?.(data)
    );
    this.apiManager.load(API_ENDPOINTS.WEBHOOKS_STATISTICS, (data) => window.showWebhookProcessingStats?.(data));
    this.apiManager.load(API_ENDPOINTS.WEBHOOKS_PR_PROCESSING, (data) => window.showWebhookPullRequestsProcessing?.(data));
    this.apiManager.load(API_ENDPOINTS.WEBHOOKS_BRANCHES_PROCESSING, (data) => window.showBranchesProcessing?.(data));
    this.apiManager.load(API_ENDPOINTS.WEBHOOKS_COMMENTS_PROCESSING, (data) => window.showCommentsProcessing?.(data));
    this.apiManager.load(API_ENDPOINTS.WEBHOOKS_INSTALLATIONS_PROCESSING, (data) => window.showInstallationsProcessing?.(data));
    this.apiManager.load(API_ENDPOINTS.WEBHOOKS_ISSUES_PROCESSING, (data) => window.showIssuesProcessing?.(data));
    this.apiManager.load(API_ENDPOINTS.WEBHOOKS_PUSHES_PROCESSING, (data) => window.showPushesProcessing?.(data));
    this.apiManager.load(API_ENDPOINTS.WEBHOOKS_REPOSITORIES_PROCESSING, (data) => window.showRepositoriesProcessing?.(data));
    this.apiManager.load(API_ENDPOINTS.WEBHOOKS_SIGNATURE_PROCESSING, (data) => window.showSignatureProcessing?.(data));
    this.apiManager.load(API_ENDPOINTS.WEBHOOKS_USERS_PROCESSING, (data) => window.showUsersProcessing?.(data));
  }

  /**
   * Loads data from various API endpoints and displays it using corresponding show functions.
   */
  load60Interval() {
    this.apiManager.load(API_ENDPOINTS.DOMAINS, (data) => window.showDomains?.(data));
    this.apiManager.load(API_ENDPOINTS.ERRORS, (data) => window.showDbErrors?.(data));
    this.apiManager.load(API_ENDPOINTS.GITHUB, (data) => window.showGitHub?.(data));
    this.apiManager.load(API_ENDPOINTS.HEALTHCHECKS, (data) => window.showHealthChecksIo?.(data));
    this.apiManager.load(API_ENDPOINTS.UPTIMEROBOT, (data) => window.showUpTimeRobot?.(data));
    this.apiManager.load(API_ENDPOINTS.WIREGUARD, (data) => window.showWireGuard?.(data));
  }

  /**
   * Loads data from POSTMAN endpoint and shows it in the postman window.
   */
  load300Interval() {
    this.apiManager.load(API_ENDPOINTS.POSTMAN, (data) => window.showPostman?.(data));
    this.apiManager.load(API_ENDPOINTS.WEBHOOKS_WORKERS, (data) => window.showWorkers?.(data));
    this.apiManager.load(API_ENDPOINTS.GSTRACCINI_JOBS, (data) => window.showGStracciniJobs?.(data));
  }
}
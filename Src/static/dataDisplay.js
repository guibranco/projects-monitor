// dataDisplay.js
import { CHART_OPTIONS } from './constants.js';
import { ChartManager } from './charts.js';

export class DataDisplayManager {
  constructor() {
    this.chartManager = new ChartManager();
    this.eventAssigned = false;
    this.eventAssignedError = false;
    this.eventAssignedQueues = false;
    this.eventAssignedDbErrors = false;
    this.eventAssignedMsgDetails = false;
  }

  #escHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /**
   * Draws the AppVeyor data table on the chart manager.
   */
  showAppVeyor(response) {
    this.chartManager.drawDataTable(response.projects, "appveyor", CHART_OPTIONS.table);
  }

  /**
   * Processes and displays various charts and tables based on the provided response data.
   */
  showCPanel(response) {
    this.showErrorFiles(response);

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

    this.chartManager.drawGaugeChart(
      "Log errors",
      response.total_error_messages,
      "gauge_chart_log_errors",
      gaugeOptions
    );

    this.chartManager.drawDataTable(
      response.error_log_messages,
      "error_log_messages",
      CHART_OPTIONS.table
    );
    this.chartManager.drawDataTable(response.cronjobs, "cronjobs", CHART_OPTIONS.table);
    this.chartManager.drawGaugeChart("Emails", response.emails, "gauge_chart_emails", gaugeOptions);

    // Process usage data
    const ids = {
      lvecpu: "gauge_chart_cpu",
      lvememphy: "gauge_chart_memory",
      lvenproc: "gauge_chart_process",
    };

    const labels = {
      lvecpu: "CPU",
      lvememphy: "Memory",
      lvenproc: "Processes",
    };

    const usageData = response.usage.map((item) => ({
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
        greenTo,
        yellowFrom: greenTo,
        yellowTo,
        redFrom: yellowTo,
        redTo: item.maximum,
      };
      this.chartManager.drawGaugeChart(item.label, item.value, item.elementId, gaugeOptionsUsage);
    }
  }

  /**
   * Renders error log files in a data table with delete actions.
   *
   * The function checks if there is more than one error log file in the response.
   * If so, it prepares the data to include delete buttons for each file and renders
   * it using the chartManager. It also sets up an event listener to handle delete
   * button clicks, confirming deletion with the user before proceeding.
   */
  showErrorFiles(response) {
    if (response.error_log_files.length <= 1) {
      this.chartManager.drawDataTable([[]], "error_log_files", CHART_OPTIONS.table);
      return;
    }

    if (response.error_log_files.length > 1) {
      const errorFilesTableData = response.error_log_files;
      errorFilesTableData[0].unshift("Actions");
      for (let i = 1; i < errorFilesTableData.length; i++) {
        const directory = errorFilesTableData[i][0];
        errorFilesTableData[i].unshift(
          `<button 
             class="btn btn-danger btn-sm" 
             data-action="delete" 
             data-directory="${directory}"
             aria-label="Delete error_log file from ${directory} directory">Delete</button>`
        );
      }

      this.chartManager.drawDataTable(
        errorFilesTableData,
        "error_log_files",
        CHART_OPTIONS.table
      );

      if (this.eventAssignedError === false) {
        this.eventAssignedError = true;
        document
          .getElementById("error_log_files")
          .addEventListener("click", (e) => {
            const deleteButton = e.target.closest('[data-action="delete"]');
            if (deleteButton) {
              const { directory } = deleteButton.dataset;
              window.confirmDeleteError?.(directory, () => window.deleteErrorLogFile?.(directory));
            }
          });
      }
    }
  }

  /**
   * Draws a data table for domains using the chart manager.
   */
  showDomains(response) {
    this.chartManager.drawDataTable(response.domains, "domains", CHART_OPTIONS.table);
  }

  /**
   * Updates the GitHub dashboard with latest release information and API usage metrics.
   *
   * This function processes a response object containing GitHub data, updating the DOM with the latest release details,
   * and rendering gauge charts for API usage, issues, and pull requests. It also populates various data tables with
   * specific data from the response.
   *
   * @param {Object} response - The response object containing GitHub data, including latest releases, issues, pull requests,
   *                             and API usage statistics.
   */
  showGitHub(response) {
    if (typeof response.latest_release !== "undefined") {
      const r = response.latest_release;
      document.getElementById("latest_release").innerHTML = `
        <div class="release-notes">
          <div class="release-notes-header">
            <div class="release-notes-heading">
              <span class="release-notes-eyebrow">Latest Release</span>
              <a class="release-notes-title" href="${r.release_url}" target="_blank" rel="noopener noreferrer">${this.#escHtml(r.title)}</a>
            </div>
            <div class="release-notes-badges">
              <a href="https://github.com/${r.repository}" target="_blank" rel="noopener noreferrer">
                <img alt="repository" src="https://img.shields.io/badge/${r.repository}-black?style=flat&amp;logo=github">
              </a>
              <a href="https://github.com/${r.author}" target="_blank" rel="noopener noreferrer">
                <img alt="author" src="https://img.shields.io/badge/${r.author}-black?style=social&amp;logo=github">
              </a>
            </div>
          </div>
          <div class="release-notes-meta"><i class="bi bi-clock-history me-1"></i>${this.#escHtml(r.published)}</div>
          <div class="release-notes-body">${r.description}</div>
        </div>`;
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

    const apiUsageCore = { used: 0, limit: 5000 };

    if (
      response.api_usage_core?.limit !== undefined &&
      response.api_usage_core?.used !== undefined
    ) {
      apiUsageCore.used = response.api_usage_core.used;
      apiUsageCore.limit = response.api_usage_core.limit;
    }

    const apiUsageGaugeOptions = {
      width: "100%",
      height: "100%",
      min: 0,
      max: apiUsageCore.limit,
      greenFrom: 0,
      greenTo: apiUsageCore.limit * 0.6,
      yellowFrom: apiUsageCore.limit * 0.6,
      yellowTo: apiUsageCore.limit * 0.8,
      redFrom: apiUsageCore.limit * 0.8,
      redTo: apiUsageCore.limit,
    };

    this.chartManager.drawGaugeChart(
      "GH API Usage",
      apiUsageCore.used,
      "gauge_chart_github_usage",
      apiUsageGaugeOptions
    );

    this.chartManager.drawGaugeChart(
      "GH Issues",
      response.issues.total_count,
      "gauge_chart_issues",
      gaugeOptions
    );
    this.chartManager.drawGaugeChart(
      "GH PRs",
      response.pull_requests.total_count,
      "gauge_chart_pull_requests",
      gaugeOptions
    );

    // Draw all the data tables
    this.chartManager.drawDataTable(
      response.pull_requests.awaiting_triage,
      "pull_requests_triage",
      CHART_OPTIONS.table
    );
    this.chartManager.drawDataTable(
      response.pull_requests.latest,
      "pull_requests_latest",
      CHART_OPTIONS.table
    );
    this.chartManager.drawDataTable(
      response.pull_requests.authored,
      "pull_requests_authored",
      CHART_OPTIONS.table
    );
    this.chartManager.drawDataTable(
      response.pull_requests.blocked,
      "pull_requests_blocked",
      CHART_OPTIONS.table
    );
    this.chartManager.drawDataTable(response.issues.assigned, "assigned", CHART_OPTIONS.table);
    this.chartManager.drawDataTable(response.issues.authored, "issues_authored", CHART_OPTIONS.table);
    this.chartManager.drawDataTable(response.issues.bug, "bug", CHART_OPTIONS.table);
    this.chartManager.drawDataTable(response.issues.blocked, "issues_blocked", CHART_OPTIONS.table);
    this.chartManager.drawDataTable(response.issues.triage, "triage", CHART_OPTIONS.table);
    this.chartManager.drawDataTable(response.issues.wip, "wip", CHART_OPTIONS.table);
    this.chartManager.drawDataTable(response.issues.others, "issues", CHART_OPTIONS.table);
    this.chartManager.drawDataTable(response.accounts_usage, "accounts_usage", CHART_OPTIONS.table);
    this.chartManager.drawDataTable(response.api_usage, "api_usage", CHART_OPTIONS.table);
  }

  /**
   * Draws health check data on a chart using the provided response.
   */
  showHealthChecksIo(response) {
    this.chartManager.drawDataTable(response["checks"], "healthchecksio", CHART_OPTIONS.table);
  }

  /**
   * Renders various charts and tables based on the provided response data.
   *
   * This function processes the response to draw a gauge chart, pie chart, and data table.
   * It also conditionally adds delete buttons to the applications data table and assigns an event listener
   * for handling delete actions. The function ensures that only one set of charts and tables are rendered
   * based on the length of the `response.byApplications` array.
   *
   * @param {Object} response - An object containing grouped messages, total count, and by-applications data.
   */
  showMessages(response) {
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

    // Build grouped table: inject an Actions column using the sample_id (index 5)
    // and raw text values (indices 6-7) that PHP appends after the five display values.
    const rawGrouped = response.grouped;
    let groupedDisplay = [];
    if (rawGrouped.length > 1) {
      groupedDisplay.push(["Actions", ...rawGrouped[0]]);
      for (let i = 1; i < rawGrouped.length; i++) {
        const row = rawGrouped[i];
        const sampleId = row[5] ?? 0;
        const rawApp   = row[6] ?? row[0];
        const rawMsg   = row[7] ?? row[1];
        const btn = `<button class="btn btn-sm btn-info py-0 px-2"
          data-action="view-msg-details"
          data-sample-id="${sampleId}"
          data-app="${this.#escHtml(rawApp)}"
          data-msg="${this.#escHtml(rawMsg)}"
          title="View individual messages">
          <i class="bi bi-eye-fill"></i>
        </button>`;
        groupedDisplay.push([btn, row[0], row[1], row[2], row[3], row[4]]);
      }
    } else {
      groupedDisplay = rawGrouped;
    }
    this.chartManager.drawDataTable(groupedDisplay, "messages_grouped", CHART_OPTIONS.table);

    if (!this.eventAssignedMsgDetails) {
      this.eventAssignedMsgDetails = true;
      document.getElementById("messages_grouped")?.addEventListener("click", (e) => {
        const btn = e.target.closest('[data-action="view-msg-details"]');
        if (btn) {
          window.openMessageDetails?.(
            btn.dataset.sampleId,
            btn.dataset.app,
            btn.dataset.msg
          );
        }
      });
    }

    this.chartManager.drawGaugeChart(
      "PM messages",
      response.total,
      "gauge_chart_pm_messages",
      gaugeOptions
    );
    this.chartManager.drawPieChart(response.byApplications, "pie_chart_2", optionsByApplications);

    const truncateBtn = document.getElementById("btn_truncate_messages");

    if (response.byApplications.length === 1) {
      if (truncateBtn) truncateBtn.style.display = "none";
      this.chartManager.drawDataTable([[]], "messages_by_applications", CHART_OPTIONS.table);
      return;
    }

    if (response.byApplications.length > 1) {
      if (truncateBtn) truncateBtn.style.display = "";
      const byApplicationsTableData = response.byApplications;
      byApplicationsTableData[0].unshift("Actions");
      for (let i = 1; i < byApplicationsTableData.length; i++) {
        const safeAppName = encodeURIComponent(byApplicationsTableData[i][0]);
        byApplicationsTableData[i].unshift(
          `<button 
             class="btn btn-danger btn-sm" 
             data-action="delete" 
             data-application="${safeAppName}"
             aria-label="Delete messages for ${safeAppName}">Delete</button>`
        );
      }

      this.chartManager.drawDataTable(
        byApplicationsTableData,
        "messages_by_applications",
        CHART_OPTIONS.table
      );

      if (this.eventAssigned === false) {
        this.eventAssigned = true;
        document
          .getElementById("messages_by_applications")
          .addEventListener("click", (e) => {
            const deleteButton = e.target.closest('[data-action="delete"]');
            if (deleteButton) {
              const { application } = deleteButton.dataset;
              window.confirmDelete?.(application, () => window.deleteMessageByApplication?.(application));
            }
          });
      }
    }
  }

  /**
   * Renders the DB-backed errors table with per-path delete and a truncate-all button.
   */
  showDbErrors(response) {
    const counterEl  = document.getElementById("counter_db_error_messages");
    const truncateBtn = document.getElementById("btn_truncate_db_errors");
    const container  = document.getElementById("db_error_messages");
    if (!container) return;

    const total = response.total ?? 0;

    if (!response.errors || response.errors.length === 0) {
      if (truncateBtn) truncateBtn.style.display = "none";
      this.chartManager.drawDataTable([[]], "db_error_messages", CHART_OPTIONS.table);
      if (counterEl) counterEl.textContent = total;
      return;
    }

    if (truncateBtn) truncateBtn.style.display = "";

    const header = ["Date", "Log File", "Error", "Location", "Actions"];
    const rows = response.errors.map(err => {
      const basePath       = (err.error_log_path ?? '').split('/').pop();
      const truncatedError = (err.error ?? '').length > 120
        ? err.error.substring(0, 120) + '…'
        : (err.error ?? '');
      const safePath = encodeURIComponent(err.error_log_path ?? '');
      const btn = `<button class="btn btn-danger btn-sm"
                  data-action="delete-path"
                  data-path="${safePath}"
                  title="Delete all errors for this log file"
                  aria-label="Delete all errors for ${this.#escHtml(basePath)}">
            <i class="bi bi-trash2"></i>
          </button>`;
      const logFile = `<span title="${this.#escHtml(err.error_log_path)}">${this.#escHtml(basePath)}</span>`;
      const errorCell = `<span title="${this.#escHtml(err.error)}">${this.#escHtml(truncatedError)}</span>`;
      return [this.#escHtml(err.date), logFile, errorCell, this.#escHtml(`${err.file}:${err.line}`), btn];
    });

    this.chartManager.drawDataTable([header, ...rows], "db_error_messages", CHART_OPTIONS.table);
    if (counterEl) counterEl.textContent = total;

    if (!this.eventAssignedDbErrors) {
      this.eventAssignedDbErrors = true;
      container.addEventListener("click", (e) => {
        const btn = e.target.closest('[data-action="delete-path"]');
        if (!btn) return;
        const decoded = decodeURIComponent(btn.dataset.path ?? '');
        window.confirmDeleteErrorsByPath?.(decoded, () => window.deleteErrorsByPath?.(decoded));
      });
    }
  }

  /**
   * Updates the inner HTML of an element with id "postman" if the response has a defined usage property.
   */
  showPostman(response) {
    if (typeof response.usage === "undefined") {
      return;
    }
    document.getElementById("postman").innerHTML = response.usage;
  }

  /**
   * Draws a data table and a gauge chart based on the response queues data.
   */
  showQueues(response) {
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

    this.chartManager.drawDataTable(response.queues, "queues", CHART_OPTIONS.table);
    this.chartManager.drawGaugeChart("Queues", response.total, "gauge_chart_queues", gaugeOptions);

    if (this.eventAssignedQueues === false) {
      this.eventAssignedQueues = true;
      document
        .getElementById("queues")
        .addEventListener("click", (e) => {
          const purgeButton = e.target.closest('[data-action="purge"]');
          if (purgeButton) {
            const { host, vhost, queue } = purgeButton.dataset;
            const decodedQueue = decodeURIComponent(queue);
            window.confirmPurgeQueue?.(decodedQueue, () => window.purgeQueue?.(host, vhost, queue));
          }
        });
    }
  }

  /**
   * Draws a data table using the provided response.
   */
  showUpTimeRobot(response) {
    this.chartManager.drawDataTable(response.monitors, "uptimerobot", CHART_OPTIONS.table);
  }

  /**
   * Updates charts and displays webhook statistics based on response data.
   */
  showWebhook(response) {
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

    if (typeof response.check_hooks_date !== "undefined") {
      const checkHooksDate = new Date(response.check_hooks_date);
      document.getElementById(
        "hooks_last_check"
      ).innerHTML = `<b>Date: </b> ${checkHooksDate.toString()}`;
    }

    this.chartManager.drawLineChart(response.statistics, "webhooks_statistics", optionsStatistics);
    this.chartManager.drawLineChart(
      response.statistics_github,
      "webhooks_statistics_github",
      optionsStatisticsGitHub
    );
    this.chartManager.drawDataTable(response.senders, "senders", CHART_OPTIONS.table);
    this.chartManager.drawDataTable(response.repositories, "repositories", CHART_OPTIONS.table);
    this.chartManager.drawDataTable(
      response.installation_repositories,
      "installed_repositories",
      CHART_OPTIONS.table
    );
    this.chartManager.drawDataTable(response.branches, "branches", CHART_OPTIONS.table);
    this.chartManager.drawDataTable(response.pull_requests, "pull_requests", CHART_OPTIONS.table);
    this.chartManager.drawDataTable(response.workflow_runs, "workflow_runs", CHART_OPTIONS.table);
    this.chartManager.drawDataTable(response.feed, "feed", CHART_OPTIONS.table, 6);
    this.chartManager.drawPieChart(response.events, "pie_chart_1", optionsEvents);
    this.chartManager.drawGaugeChart(
      "GH WH",
      response.total,
      "gauge_chart_webhooks",
      gaugeOptionsTotal
    );
    this.chartManager.drawGaugeChart(
      "GH WRs",
      response.total_workflow_runs,
      "gauge_chart_workflows_runs",
      gaugeOptions
    );
    this.chartManager.drawGaugeChart(
      "Bot Installs",
      response.installations,
      "gauge_chart_bot_installations",
      gaugeOptions
    );
    this.chartManager.drawGaugeChart(
      "Bot Repos",
      response.installation_repositories_count,
      "gauge_chart_bot_repositories",
      gaugeOptions
    );
  }

  /**
   * Renders pull requests pending processing from the webhooks /pull-requests/processing endpoint.
   */
  showWebhookPullRequestsProcessing(response) {
    const container = document.getElementById("pr_processing");
    const counterEl = document.getElementById("counter_pr_processing");
    if (!container) return;

    const items = Array.isArray(response) ? response : [];

    if (items.length === 0) {
      if (counterEl) counterEl.textContent = 0;
      container.innerHTML = '<p class="text-muted text-center py-3 mb-0">No pull requests pending processing.</p>';
      return;
    }

    const stateBadgeClass = (state) => {
      switch (state) {
        case "NEW":        return "bg-primary";
        case "RE_REQUESTED": return "bg-warning text-dark";
        case "UPDATED":    return "bg-info text-dark";
        case "PROCESSING": return "bg-danger";
        default:           return "bg-secondary";
      }
    };

    const header = ["#Seq", "Repository", "PR", "Sender", "Branch", "State",
      "Processing Date", "Receiver", "Handler", "Bot"];

    const rows = items.map(item => {
      const repo = `${this.#escHtml(item.repositoryOwner)}/${this.#escHtml(item.repositoryName)}`;
      const prLink = `<a href="https://github.com/${this.#escHtml(item.repositoryOwner)}/${this.#escHtml(item.repositoryName)}/pull/${item.number}" target="_blank" rel="noopener noreferrer">#${item.number}</a>`;
      const stateBadge = `<span class="badge ${stateBadgeClass(item.processingState)}">${this.#escHtml(item.processingState)}</span>`;
      const ref = item.ref ? this.#escHtml(item.ref.replace('refs/heads/', '')) : '—';
      const refCell = `<span title="${this.#escHtml(item.ref ?? '')}">${ref}</span>`;
      const sender = item.senderLogin ? this.#escHtml(item.senderLogin) : (item.sender ? this.#escHtml(item.sender) : '—');
      const processingDate = item.processingDate ? this.#escHtml(item.processingDate) : '—';
      const receiverVer = item.webhooksReceiverVersion ? this.#escHtml(item.webhooksReceiverVersion) : '—';
      const handlerVer = item.webhooksHandlerVersion ? this.#escHtml(item.webhooksHandlerVersion) : '—';
      const botVer = item.gstracciniBotVersion ? this.#escHtml(item.gstracciniBotVersion) : '—';
      return [item.sequence, repo, prLink, sender, refCell, stateBadge,
        processingDate, receiverVer, handlerVer, botVer];
    });

    this.chartManager.drawDataTable([header, ...rows], "pr_processing", CHART_OPTIONS.table);
    if (counterEl) counterEl.textContent = items.length;
  }

  /**
   * Draws a wire guard data table using the chart manager.
   */
  showWireGuard(response) {
    this.chartManager.drawDataTable(response.wireguard, "wireguard", CHART_OPTIONS.table);
  }

  /**
   * Renders processing state counts from the webhooks /statistics endpoint as a table.
   * Rows = database tables, columns = processing states (NEW, RE_REQUESTED, UPDATED, PROCESSING, PROCESSED).
   */
  showWebhookProcessingStats(response) {
    const states = ["NEW", "RE_REQUESTED", "UPDATED", "PROCESSING", "PROCESSED"];
    const pendingStates = ["NEW", "RE_REQUESTED", "UPDATED", "PROCESSING"];
    const tables = [
      "github_branches",
      "github_comments",
      "github_installations",
      "github_issues",
      "github_pull_requests",
      "github_pushes",
      "github_repositories",
      "github_signature",
      "github_users",
    ];

    const header = ["Table", ...states];
    const rows = tables.map((table) => {
      const displayName = table.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
      return [displayName, ...states.map((state) => (response[state]?.[table] ?? 0))];
    });

    const data = [header, ...rows];

    const counterEl = document.getElementById("counter_webhook_processing_stats");
    if (counterEl) {
      const total = rows.reduce((sum, row) => {
        return sum + states.reduce((s, _, i) => s + (row[i + 1] || 0), 0);
      }, 0);
      counterEl.innerHTML = total;
    }

    const botQueueTotal = pendingStates.reduce(
      (sum, state) => sum + tables.reduce((s, table) => s + (response[state]?.[table] ?? 0), 0),
      0
    );

    this.chartManager.drawGaugeChart("Bot Queue", botQueueTotal, "gauge_chart_bot_queue", {
      width: "100%",
      height: "100%",
      min: 0,
      max: 1000,
      greenFrom: 0,
      greenTo: 50,
      yellowFrom: 50,
      yellowTo: 200,
      redFrom: 200,
      redTo: 1000,
    });

    this.chartManager.drawChartByType(data, "table", "webhook_processing_stats", CHART_OPTIONS.table);
  }
}

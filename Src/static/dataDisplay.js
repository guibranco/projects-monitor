// dataDisplay.js
import { CHART_OPTIONS } from './constants.js';
import { ChartManager } from './charts.js';

export class DataDisplayManager {
  constructor() {
    this.chartManager = new ChartManager();
    this.eventAssigned = false;
    this.eventAssignedError = false;
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
              if (window.confirmDeleteError && window.confirmDeleteError(directory)) {
                window.deleteErrorLogFile?.(directory);
              }
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
      const latestRelease = response.latest_release;
      document.getElementById("latest_release").innerHTML =
        `<b>Release Notes:</b> ${latestRelease.description}<b>Date:</b> ${latestRelease.published} | ` +
        `<b>Version:</b> <a href='${latestRelease.release_url}'>${latestRelease.title}</a> | ` +
        `<a href='https://github.com/${latestRelease.repository}' target='_blank' rel='noopener noreferrer'>` +
        `<img alt='Static Badge' src='https://img.shields.io/badge/${latestRelease.repository}-black?style=flat&amp;logo=github'></a> | ` +
        `<a href='https://github.com/${latestRelease.author}' target='_blank' rel='noopener noreferrer'>` +
        `<img alt='author' src='https://img.shields.io/badge/${latestRelease.author}-black?style=social&amp;logo=github'></a>`;
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

    this.chartManager.drawDataTable(response.grouped, "messages_grouped", CHART_OPTIONS.table);
    this.chartManager.drawGaugeChart(
      "PM messages",
      response.total,
      "gauge_chart_pm_messages",
      gaugeOptions
    );
    this.chartManager.drawPieChart(response.byApplications, "pie_chart_2", optionsByApplications);

    if (response.byApplications.length === 1) {
      this.chartManager.drawDataTable([[]], "messages_by_applications", CHART_OPTIONS.table);
      return;
    }

    if (response.byApplications.length > 1) {
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
              if (window.confirmDelete && window.confirmDelete(application)) {
                window.deleteMessageByApplication?.(application);
              }
            }
          });
      }
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
  }

  /**
   * Draws a data table using the provided response.
   */
  showUpTimeRobot(response) {
    this.chartManager.drawDataTable(response.monitors, "uptimerobot", CHART_OPTIONS.table);
  }

  /**
   * Updates various charts and displays webhook statistics based on response data.
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
      "WH Failed",
      response.failed,
      "gauge_chart_webhooks_failed",
      gaugeOptions
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
   * Draws a wire guard data table using the chart manager.
   */
  showWireGuard(response) {
    this.chartManager.drawDataTable(response.wireguard, "wireguard", CHART_OPTIONS.table);
  }
}
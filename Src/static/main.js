// main.js
import { OptionsBoxState, FeedState, WorkflowLimiterState } from './storage.js';
import { ApiManager, DataLoader } from './api.js';
import { UIManager, GitHubStatsManager, CookieManager } from './ui.js';
import { DataDisplayManager } from './dataDisplay.js';

class DashboardApp {
  constructor() {
    this.showPreset = true;
    this.apiManager = new ApiManager();
    this.dataLoader = new DataLoader(this.apiManager);
    this.dataDisplayManager = new DataDisplayManager();
    
    // Initialize state objects
    this.feedState = new FeedState();
    this.workflowLimiterState = new WorkflowLimiterState();
    this.uiManager = new UIManager(this.feedState, this.workflowLimiterState);

    // Expose necessary objects to global scope for backwards compatibility
    this.exposeGlobals();
    
    // Bind methods to maintain context
    this.init = this.init.bind(this);
    this.drawChart = this.drawChart.bind(this);
    this.preset = this.preset.bind(this);
  }

  exposeGlobals() {
    // Expose state objects
    window.feedState = this.feedState;
    window.workflowLimiterState = this.workflowLimiterState;
    
    // Expose display functions for API callbacks
    window.showAppVeyor = this.dataDisplayManager.showAppVeyor.bind(this.dataDisplayManager);
    window.showCPanel = this.dataDisplayManager.showCPanel.bind(this.dataDisplayManager);
    window.showDomains = this.dataDisplayManager.showDomains.bind(this.dataDisplayManager);
    window.showGitHub = this.dataDisplayManager.showGitHub.bind(this.dataDisplayManager);
    window.showHealthChecksIo = this.dataDisplayManager.showHealthChecksIo.bind(this.dataDisplayManager);
    window.showMessages = this.dataDisplayManager.showMessages.bind(this.dataDisplayManager);
    window.showPostman = this.dataDisplayManager.showPostman.bind(this.dataDisplayManager);
    window.showQueues = this.dataDisplayManager.showQueues.bind(this.dataDisplayManager);
    window.showUpTimeRobot = this.dataDisplayManager.showUpTimeRobot.bind(this.dataDisplayManager);
    window.showWebhook = this.dataDisplayManager.showWebhook.bind(this.dataDisplayManager);
    window.showWireGuard = this.dataDisplayManager.showWireGuard.bind(this.dataDisplayManager);
    
    // Expose API functions
    window.deleteMessageByApplication = async (application) => {
      const data = await this.apiManager.deleteMessageByApplication(application);
      this.dataDisplayManager.showMessages(data);
    };
    
    window.deleteErrorLogFile = async (directory) => {
      const data = await this.apiManager.deleteErrorLogFile(directory);
      this.dataDisplayManager.showCPanel(data);
    };
    
    // Expose confirmation functions
    window.confirmDelete = this.uiManager.confirmDelete.bind(this.uiManager);
    window.confirmDeleteError = this.uiManager.confirmDeleteError.bind(this.uiManager);
    
    // Expose GitHub stats function
    window.showGitHubStatsAndWakatime = GitHubStatsManager.show;
  }

  /**
   * Initializes preset information if the showPreset flag is true.
   */
  preset() {
    if (!this.showPreset) {
      return;
    }
    this.showPreset = false;
    
    const presetData = {
      cpanel: JSON.parse(
        '{"emails":0,"error_log_files":[],"error_log_messages":[],"total_error_messages":0,"cronjobs":[],"usage":[{"id":"lvecpu","description":"CPU Usage","usage":0,"maximum":100},{"id":"lvememphy","description":"Physical Memory Usage","usage":0,"maximum":512},{"id":"lvenproc","description":"Number of Processes","usage":0,"maximum":100}]}'
      ),
      github: JSON.parse(
        '{"api_usage":[],"issues":{"total_count":0,"others":[],"bug":[],"triage":[],"wip":[],"assigned":[],"authored":[],"blocked":[]},"pull_requests":{"total_count":0,"awaiting_triage":[],"latest":[],"authored":[],"blocked":[]},"accounts_usage":[]}'
      ),
      messages: JSON.parse(
        '{"total":0,"byApplications":[["Applications","Hits"]],"grouped":[]}'
      ),
      queues: JSON.parse('{"queues":[],"total":0}'),
      webhooks: JSON.parse(
        '{"senders":[],"events":[["Event","Hits"]],"failed":0,"feed":[],"repositories":[],"total":0,"statistics":[["Date","Table #1"],["01/01",0]],"statistics_github":[["Date","Table #1"],["01/01",0]],"branches":[],"workflow_runs":[],"total_workflow_runs":0, "installations":0, "installation_repositories": [], "installation_repositories_count": 0}'
      )
    };

    this.dataDisplayManager.showCPanel(presetData.cpanel);
    this.dataDisplayManager.showGitHub(presetData.github);
    this.dataDisplayManager.showMessages(presetData.messages);
    this.dataDisplayManager.showQueues(presetData.queues);
    this.dataDisplayManager.showWebhook(presetData.webhooks);
  }

  /**
   * Initializes the application by setting up timezone, UI components, and logging states.
   */
  init() {
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const offset = new Date().toString().match(/([-\+][0-9]+)\s/)[1];
    CookieManager.set("timezone", timezone, 10);
    CookieManager.set("offset", offset, 10);
    
    OptionsBoxState.handle();
    this.uiManager.initFeedToggle();
    this.uiManager.initWorkflowLimiter();

    console.log("Options box state on load:", OptionsBoxState.load());
    console.log("Feed filter on load:", this.feedState.filter);
    console.log("Workflow limiter enabled:", this.workflowLimiterState.enabled);
    console.log("Workflow limit value:", this.workflowLimiterState.limitValue);
  }

  /**
   * Initializes and starts the chart drawing process with data loading intervals.
   */
  drawChart() {
    this.preset();
    GitHubStatsManager.show();
    
    // Initial data load
    this.dataLoader.load30Interval();
    this.dataLoader.load60Interval();
    this.dataLoader.load300Interval();
    
    // Set up intervals
    setInterval(() => this.dataLoader.load30Interval(), 30 * 1000);
    setInterval(() => this.dataLoader.load60Interval(), 60 * 1000);
    setInterval(() => this.dataLoader.load300Interval(), 300 * 1000);
    setInterval(GitHubStatsManager.show, 60 * 15 * 1000);
  }
}

// Initialize the application
const app = new DashboardApp();

// Set up Google Charts
if (typeof google !== 'undefined') {
  google.charts.load("current", { packages: ["corechart", "table", "gauge"] });
  google.charts.setOnLoadCallback(() => app.drawChart());
}

// Set up window load event
window.addEventListener("load", () => app.init());

// Export the app instance for debugging
window.dashboardApp = app;
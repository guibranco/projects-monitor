// main.js
import { OptionsBoxState, FeedState, WorkflowLimiterState } from './storage.js';
import { ApiManager, DataLoader } from './api.js';
import { UIManager, GitHubStatsManager, CookieManager } from './ui.js';
import { DataDisplayManager } from './dataDisplay.js';
import { CollapsibleSectionsManager } from './collapsibleSections.js'; // Add this import

class DashboardApp {
  constructor() {
    this.showPreset = true;
    this.apiManager = new ApiManager();
    this.dataLoader = new DataLoader(this.apiManager);
    this.dataDisplayManager = new DataDisplayManager();
    this.collapsibleSectionsManager = new CollapsibleSectionsManager(); // Add this line

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

  /**
   * Exposes global state objects and functions for API callbacks, confirmations, and GitHub stats.
   */
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

    // Expose collapsible sections functions
    window.toggleSection = this.collapsibleSectionsManager.toggleSectionById.bind(this.collapsibleSectionsManager);
    window.collapseAllSections = this.collapsibleSectionsManager.collapseAll.bind(this.collapsibleSectionsManager);
    window.expandAllSections = this.collapsibleSectionsManager.expandAll.bind(this.collapsibleSectionsManager);
    window.getSectionStates = this.collapsibleSectionsManager.getSectionStates.bind(this.collapsibleSectionsManager);
    window.getSectionStats = this.collapsibleSectionsManager.getSectionStats.bind(this.collapsibleSectionsManager);
    window.reinitializeCollapsibleSections = this.collapsibleSectionsManager.reinitialize.bind(this.collapsibleSectionsManager);
  }

  /**
   * Initializes and displays preset data if showPreset is true.
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
        '{"senders":[],"events":[["Event","Hits"]],"failed":0,"feed":[],"repositories":[],"total":0,"statistics":[["Date","Table #1"],["01/01",0]],"statistics_github":[["Date","Table #1"],["01/01",0]],"branches":[], "pull_requests":[], "workflow_runs":[],"total_workflow_runs":0, "installations":0, "installation_repositories": [], "installation_repositories_count": 0}'
      )
    };

    this.dataDisplayManager.showCPanel(presetData.cpanel);
    this.dataDisplayManager.showGitHub(presetData.github);
    this.dataDisplayManager.showMessages(presetData.messages);
    this.dataDisplayManager.showQueues(presetData.queues);
    this.dataDisplayManager.showWebhook(presetData.webhooks);

    // Reinitialize collapsible sections after preset data is loaded
    setTimeout(() => {
      this.collapsibleSectionsManager.reinitialize();
    }, 500);
  }

  /**
   * Initializes application settings and UI components.
   */
  init() {
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const offset = new Date().toString().match(/([-\+][0-9]+)\s/)[1];
    CookieManager.set("timezone", timezone, 10);
    CookieManager.set("offset", offset, 10);

    OptionsBoxState.handle();
    this.uiManager.initFeedToggle();
    this.uiManager.initWorkflowLimiter();

    // Initialize collapsible sections
    this.collapsibleSectionsManager.init();

    // Set up event listeners for collapsible sections
    this.setupCollapsibleSectionEventListeners();

    console.log("Options box state on load:", OptionsBoxState.load());
    console.log("Feed filter on load:", this.feedState.filter);
    console.log("Workflow limiter enabled:", this.workflowLimiterState.enabled);
    console.log("Workflow limit value:", this.workflowLimiterState.limitValue);
  }

  /**
   * Set up event listeners for collapsible sections integration
   */
  setupCollapsibleSectionEventListeners() {
    // Listen for section toggle events
    document.addEventListener('sectionToggled', (e) => {
      const { sectionId, collapsed } = e.detail;
      console.log(`Dashboard: Section ${sectionId} ${collapsed ? 'collapsed' : 'expanded'}`);
      
      // You can add custom logic here when sections are toggled
      // For example, pause/resume chart updates, save analytics, etc.
    });

    // Add keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      // Ctrl/Cmd + Shift + C to collapse all
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'C') {
        e.preventDefault();
        this.collapsibleSectionsManager.collapseAll();
      }
      
      // Ctrl/Cmd + Shift + E to expand all
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'E') {
        e.preventDefault();
        this.collapsibleSectionsManager.expandAll();
      }
    });

    // Log section stats on load
    setTimeout(() => {
      const stats = this.collapsibleSectionsManager.getSectionStats();
      console.log('Collapsible sections stats:', stats);
    }, 1000);
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

    // Reinitialize collapsible sections after initial data load
    setTimeout(() => {
      this.collapsibleSectionsManager.reinitialize();
    }, 2000);
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
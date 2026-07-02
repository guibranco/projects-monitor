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
    window.showWebhookProcessingStats = this.dataDisplayManager.showWebhookProcessingStats.bind(this.dataDisplayManager);
    window.showWebhookPullRequestsProcessing = this.dataDisplayManager.showWebhookPullRequestsProcessing.bind(this.dataDisplayManager);
    window.showWireGuard = this.dataDisplayManager.showWireGuard.bind(this.dataDisplayManager);

    // Expose API functions
    window.deleteMessageByApplication = async (application) => {
      const data = await this.apiManager.deleteMessageByApplication(application);
      this.dataDisplayManager.showMessages(data);
    };

    window.truncateMessages = async () => {
      const data = await this.apiManager.truncateMessages();
      this.dataDisplayManager.showMessages(data);
    };

    window.purgeQueue = async (host, vhost, queue) => {
      const data = await this.apiManager.purgeQueue(host, vhost, queue);
      this.dataDisplayManager.showQueues(data);
    };

    window.deleteErrorLogFile = async (directory) => {
      const data = await this.apiManager.deleteErrorLogFile(directory);
      this.dataDisplayManager.showCPanel(data);
    };

    window.showDbErrors = this.dataDisplayManager.showDbErrors.bind(this.dataDisplayManager);

    window.truncateDbErrors = async () => {
      const data = await this.apiManager.truncateDbErrors();
      this.dataDisplayManager.showDbErrors(data);
    };

    window.deleteErrorsByPath = async (path) => {
      const data = await this.apiManager.deleteErrorsByPath(path);
      this.dataDisplayManager.showDbErrors(data);
    };

    // Expose confirmation functions
    window.confirmDelete = this.uiManager.confirmDelete.bind(this.uiManager);
    window.confirmTruncateMessages = this.uiManager.confirmTruncateMessages.bind(this.uiManager);
    window.confirmPurgeQueue = this.uiManager.confirmPurgeQueue.bind(this.uiManager);
    window.confirmDeleteError = this.uiManager.confirmDeleteError.bind(this.uiManager);
    window.confirmTruncateDbErrors = this.uiManager.confirmTruncateDbErrors.bind(this.uiManager);
    window.confirmDeleteErrorsByPath = this.uiManager.confirmDeleteErrorsByPath.bind(this.uiManager);
    window.confirmDeleteMessage = this.uiManager.confirmDeleteMessage.bind(this.uiManager);
    window.confirmDeleteMessageGroup = this.uiManager.confirmDeleteMessageGroup.bind(this.uiManager);

    // ── Message detail modal ────────────────────────────────────────────────
    const _apiMgr  = this.apiManager;
    const _dispMgr = this.dataDisplayManager;
    let   _msgDetailGroup = null;
    let   _msgDetailGrid = null;
    let   _msgDetailMsgs = [];
    const _expandedMsgIds = new Set();

    const _esc = (s) =>
      String(s ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const MSG_DETAIL_COLUMNS = ['#ID', 'Class', 'Function', 'File : Line',
      'Type', 'Correlation ID', 'Created At', 'Actions'];

    // Detail (expanded) rows are represented by giving every cell the same
    // marker object; column 0's formatter renders the real content spanning
    // the full row width (via a colSpan attribute), the rest render empty
    // and are hidden — Grid.js has no native "extra sibling row" concept.
    const _detailMarker = (html) => ({ __detail: true, html });

    const _msgDetailRow = (m) => [
      m.id,
      _esc(m.class),
      _esc(m.function),
      `${_esc(m.file)}<span class="text-muted">:${_esc(m.line)}</span>`,
      _esc(m.type),
      _esc(m.correlation_id),
      _esc(m.created_at),
      `<button class="btn btn-sm btn-outline-secondary py-0 me-1"
         data-action="toggle-msg-detail" data-id="${m.id}"
         title="View object / args / details">
         <i class="bi bi-chevron-${_expandedMsgIds.has(m.id) ? 'up' : 'down'}"></i>
       </button>
       <button class="btn btn-sm btn-danger py-0"
         data-action="delete-single-msg" data-id="${m.id}"
         title="Delete this message">
         <i class="bi bi-trash"></i>
       </button>`,
    ];

    const _msgDetailExtraRow = (m) => {
      const html = `<dl class="row mb-0 small">
          <dt class="col-sm-2 text-warning">Object</dt>
          <dd class="col-sm-10"><code class="text-break">${_esc(m.object)}</code></dd>
          <dt class="col-sm-2 text-warning">Args</dt>
          <dd class="col-sm-10"><code class="text-break">${_esc(m.args)}</code></dd>
          <dt class="col-sm-2 text-warning">Details</dt>
          <dd class="col-sm-10"><code class="text-break">${_esc(m.details)}</code></dd>
        </dl>`;
      const marker = _detailMarker(html);
      return MSG_DETAIL_COLUMNS.map(() => marker);
    };

    const _buildMsgDetailGridData = () => {
      const data = [];
      for (const m of _msgDetailMsgs) {
        data.push(_msgDetailRow(m));
        if (_expandedMsgIds.has(m.id)) {
          data.push(_msgDetailExtraRow(m));
        }
      }
      return data;
    };

    const _renderMsgDetailGrid = () => {
      const body = document.getElementById('msgDetailBody');
      if (!body) return;

      const columns = MSG_DETAIL_COLUMNS.map((name, colIndex) => ({
        name,
        formatter: (cell) => {
          if (cell && typeof cell === 'object' && cell.__detail) {
            return colIndex === 0 ? gridjs.html(cell.html) : '';
          }
          return typeof cell === 'string' ? gridjs.html(cell) : cell;
        },
        attributes: (cell) => {
          if (cell && typeof cell === 'object' && cell.__detail) {
            return colIndex === 0
              ? { colSpan: MSG_DETAIL_COLUMNS.length, class: 'msg-detail-row' }
              : { style: 'display:none' };
          }
          return {};
        },
      }));

      if (_msgDetailGrid) {
        _msgDetailGrid.updateConfig({ columns, data: _buildMsgDetailGridData() }).forceRender();
        return;
      }

      body.innerHTML = '';
      _msgDetailGrid = new gridjs.Grid({
        columns,
        data: _buildMsgDetailGridData(),
        sort: false,
        pagination: false,
        search: false,
        resizable: false,
        className: { table: 'gridjs-table-dark' },
      });
      _msgDetailGrid.render(body);
    };

    const _loadMsgDetails = async (sampleId) => {
      const body = document.getElementById('msgDetailBody');
      if (!body) return;
      _msgDetailGrid = null;
      _expandedMsgIds.clear();
      body.innerHTML = `<div class="text-center py-4">
        <div class="spinner-border text-light" role="status">
          <span class="visually-hidden">Loading…</span>
        </div></div>`;

      try {
        const data = await _apiMgr.getMessageDetails(sampleId);
        _msgDetailMsgs = data.messages ?? [];

        if (_msgDetailMsgs.length === 0) {
          body.innerHTML = '<p class="text-center text-muted py-3">No messages found in this group.</p>';
          return;
        }

        _renderMsgDetailGrid();
      } catch (_) {
        body.innerHTML = '<div class="alert alert-danger m-3">Failed to load message details.</div>';
      }
    };

    // Delegated click handler on the modal body (set up once; body element persists)
    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('msgDetailBody')?.addEventListener('click', async (e) => {
        const toggleBtn = e.target.closest('[data-action="toggle-msg-detail"]');
        if (toggleBtn) {
          const id = parseInt(toggleBtn.dataset.id, 10);
          if (_expandedMsgIds.has(id)) {
            _expandedMsgIds.delete(id);
          } else {
            _expandedMsgIds.add(id);
          }
          _renderMsgDetailGrid();
          return;
        }

        const deleteBtn = e.target.closest('[data-action="delete-single-msg"]');
        if (deleteBtn) {
          const id = parseInt(deleteBtn.dataset.id, 10);
          window.confirmDeleteMessage?.(id, async () => {
            await _apiMgr.deleteMessageById(id);
            if (_msgDetailGroup) {
              _loadMsgDetails(_msgDetailGroup.sampleId);
            }
          });
        }
      });

      document.getElementById('btn_delete_message_group')?.addEventListener('click', () => {
        window.deleteMessageGroup?.();
      });
    });

    window.openMessageDetails = (sampleId, rawApp, rawMsg) => {
      _msgDetailGroup = { sampleId, rawApp, rawMsg };
      const subtitle = document.getElementById('msgDetailSubtitle');
      if (subtitle) {
        subtitle.textContent = `${rawApp} — ${rawMsg.length > 90 ? rawMsg.substring(0, 90) + '…' : rawMsg}`;
      }
      const modalEl = document.getElementById('messageDetailsModal');
      if (!modalEl) return;
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      _loadMsgDetails(sampleId);
    };

    window.deleteMessageGroup = () => {
      if (!_msgDetailGroup) return;
      const { sampleId, rawApp } = _msgDetailGroup;
      window.confirmDeleteMessageGroup?.(rawApp, async () => {
        const data = await _apiMgr.deleteMessageGroup(sampleId);
        const modalEl = document.getElementById('messageDetailsModal');
        bootstrap.Modal.getInstance(modalEl)?.hide();
        _msgDetailGroup = null;
        _dispMgr.showMessages(data);
      });
    };
    // ── End message detail modal ────────────────────────────────────────────

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
        '{"senders":[],"events":[["Event","Hits"]],"feed":[],"repositories":[],"total":0,"statistics":[["Date","Table #1"],["01/01",0]],"statistics_github":[["Date","Table #1"],["01/01",0]],"branches":[], "pull_requests":[], "workflow_runs":[],"total_workflow_runs":0, "installations":0, "installation_repositories": [], "installation_repositories_count": 0}'
      ),
      errors_db: { errors: [], total: 0 },
      webhooks_statistics: { NEW: {}, RE_REQUESTED: {}, UPDATED: {}, PROCESSING: {}, PROCESSED: {} }
    };

    this.dataDisplayManager.showCPanel(presetData.cpanel);
    this.dataDisplayManager.showGitHub(presetData.github);
    this.dataDisplayManager.showMessages(presetData.messages);
    this.dataDisplayManager.showQueues(presetData.queues);
    this.dataDisplayManager.showWebhook(presetData.webhooks);
    this.dataDisplayManager.showDbErrors(presetData.errors_db);
    this.dataDisplayManager.showWebhookProcessingStats(presetData.webhooks_statistics);

    // Reinitialize collapsible sections after preset data is loaded
    setTimeout(() => {
      this.collapsibleSectionsManager.reinitialize();
    }, 500);
  }

  /**
   * Initializes application settings, UI components, and event listeners.
   */
  init() {
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const offset = new Date().toString().match(/([-\+][0-9]+)\s/)[1];
    CookieManager.set("timezone", timezone, 10);
    CookieManager.set("offset", offset, 10);

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
   * Set up event listeners for collapsible section integration.
   *
   * This function sets up event listeners to handle various interactions with collapsible sections.
   * It includes:
   * - Logging when a section is toggled (collapsed or expanded).
   * - Adding keyboard shortcuts to collapse or expand all sections.
   * - Logging the statistics of collapsible sections after a delay on page load.
   *
   * @param {Event} e - The event object passed by the browser.
   */
  setupCollapsibleSectionEventListeners() {
    // Listen for section toggle events
    document.addEventListener('sectionToggled', (e) => {
      const { sectionId, collapsed } = e.detail;
      console.log(`Dashboard: Section ${sectionId} ${collapsed ? 'collapsed' : 'expanded'}`);

      // ECharts measures its container's pixel size at draw time, so a chart
      // drawn while its section was collapsed (max-height: 0) renders 0x0.
      // Resize every known chart instance whenever a section expands.
      if (!collapsed) {
        this.dataDisplayManager.chartManager.resizeAll();
      }
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
   * Initializes chart, loads initial data, and sets up data loading intervals.
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

// ECharts and Grid.js are loaded via plain synchronous <script> tags in
// index.php, before this module executes, so window.echarts/window.gridjs
// are already defined by the time DashboardApp's constructor runs.
window.addEventListener("load", () => {
  app.init();
  app.drawChart();
});

// Export the app instance for debugging
window.dashboardApp = app;

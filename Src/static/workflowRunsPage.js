// workflowRunsPage.js
//
// The dedicated Workflow runs page (workflow-runs.php). Loads every tracked
// run once per refresh (GET api/v1/workflow-runs) and pages, filters and
// searches them in the browser, so moving between pages never refetches
// and a refresh keeps the page you are on. The refresh runs on its own
// timer: 30 sec to 10 min (1 min by default, remembered), with Pause /
// Resume and Refresh now. The countdown stops while the tab is hidden.

import { API_ENDPOINTS } from "./constants.js";
import {
  cellText,
  filterRows,
  getRepoFilter,
  onRepoFilterChange,
} from "./repoFilter.js";

export const REFRESH_INTERVALS = [30, 60, 120, 300, 600];
export const DEFAULT_REFRESH_INTERVAL = 60;
export const PAGE_SIZES = [10, 25, 50, 100, "all"];
export const DEFAULT_PAGE_SIZE = 25;

const STORAGE_INTERVAL = "workflowRunsRefreshInterval";
const STORAGE_PAGE_SIZE = "workflowRunsPageSize";

// Outcome chips, in display order; keys match WorkflowRunsPresenter::STATE_*.
export const STATES = [
  { key: "", label: "All" },
  { key: "failed", label: "Failed" },
  { key: "running", label: "Running" },
  { key: "cancelled", label: "Cancelled" },
  { key: "success", label: "Success" },
  { key: "other", label: "Other" },
];

const ACTIONS_COLUMN = /^actions$/i;

/**
 * A stored refresh interval, or the default when missing or not allowed.
 *
 * @param {*} value - The stored value.
 * @returns {number} Seconds.
 */
export function normalizeInterval(value) {
  const seconds = Number.parseInt(value, 10);
  return REFRESH_INTERVALS.includes(seconds)
    ? seconds
    : DEFAULT_REFRESH_INTERVAL;
}

/**
 * A stored page size, or the default when missing or not allowed.
 *
 * @param {*} value - The stored value.
 * @returns {number|string} Rows per page, or "all".
 */
export function normalizePageSize(value) {
  if (value === "all") return "all";
  const size = Number.parseInt(value, 10);
  return PAGE_SIZES.includes(size) ? size : DEFAULT_PAGE_SIZE;
}

/**
 * Whether a row's search text holds every word of the query.
 *
 * @param {string} text - The row's lower-case search text.
 * @param {string} query - What was typed.
 * @returns {boolean}
 */
export function matchesSearch(text, query) {
  const words = String(query ?? "")
    .toLowerCase()
    .split(/\s+/)
    .filter(Boolean);
  return words.every((word) => text.includes(word));
}

/**
 * The page numbers to offer around the current one, with null for a gap:
 * always the first and last, and two either side of the current.
 *
 * @param {number} current - Zero-based current page.
 * @param {number} count - Number of pages.
 * @returns {Array<number|null>}
 */
export function pageWindow(current, count) {
  const pages = [];
  let previous = -1;
  for (let page = 0; page < count; page++) {
    if (page === 0 || page === count - 1 || Math.abs(page - current) <= 2) {
      if (previous !== -1 && page - previous > 1) pages.push(null);
      pages.push(page);
      previous = page;
    }
  }
  return pages;
}

/**
 * "1m 30s" / "45s" for the countdown.
 *
 * @param {number} seconds - Seconds left.
 * @returns {string}
 */
export function formatCountdown(seconds) {
  const total = Math.max(0, Math.ceil(seconds));
  const minutes = Math.floor(total / 60);
  const rest = total % 60;
  if (minutes === 0) return `${rest}s`;
  return rest === 0 ? `${minutes}m` : `${minutes}m ${rest}s`;
}

const readStorage = (key) => {
  try {
    return window.localStorage.getItem(key);
  } catch {
    return null;
  }
};

const writeStorage = (key, value) => {
  try {
    window.localStorage.setItem(key, String(value));
  } catch {
    // Storage blocked: the choice just isn't remembered.
  }
};

export class WorkflowRunsPage {
  /**
   * @param {import('./api.js').ApiManager} apiManager - Session handling (login modal).
   * @param {import('./charts.js').ChartManager} chartManager - Draws the table.
   * @param {() => void} [onLoaded] - Called after every successful load.
   */
  constructor(apiManager, chartManager, onLoaded = () => {}) {
    this.apiManager = apiManager;
    this.chartManager = chartManager;
    this.onLoaded = onLoaded;

    this.interval = normalizeInterval(readStorage(STORAGE_INTERVAL));
    this.pageSize = normalizePageSize(readStorage(STORAGE_PAGE_SIZE));
    this.paused = false;
    this.remaining = 0;
    this.loading = false;
    this.failed = false;
    this.ticker = null;

    this.header = [];
    this.rows = []; // { cells, state, text }
    this.total = 0;
    this.state = "";
    this.query = "";
    this.page = 0;
  }

  /** Wires the controls, loads the first time and starts the countdown. */
  init() {
    this.elements = {
      pause: document.getElementById("wr_pause"),
      refreshNow: document.getElementById("wr_refresh_now"),
      interval: document.getElementById("wr_interval"),
      countdown: document.getElementById("wr_countdown"),
      states: document.getElementById("wr_states"),
      search: document.getElementById("wr_search"),
      note: document.getElementById("wr_note"),
      table: document.getElementById("wr_table"),
      summary: document.getElementById("wr_summary"),
      pages: document.getElementById("wr_pages"),
      pageSize: document.getElementById("wr_page_size"),
      counter: document.getElementById("wr_counter"),
    };

    const {
      pause,
      refreshNow,
      interval,
      states,
      search,
      pages,
      pageSize,
      table,
    } = this.elements;

    interval.value = String(this.interval);
    pageSize.value = String(this.pageSize);

    pause.addEventListener("click", () => this.setPaused(!this.paused));
    refreshNow.addEventListener("click", () => this.refresh());
    interval.addEventListener("change", () => {
      this.interval = normalizeInterval(interval.value);
      writeStorage(STORAGE_INTERVAL, this.interval);
      // A shorter interval applies now; a longer one restarts the wait.
      this.remaining = Math.min(this.remaining, this.interval);
      if (this.remaining <= 0) this.remaining = this.interval;
      this.renderCountdown();
    });

    states.addEventListener("click", (event) => {
      const chip = event.target.closest("[data-state]");
      if (!chip) return;
      this.state = chip.dataset.state;
      this.page = 0;
      this.render();
    });

    let searchTimer = null;
    search.addEventListener("input", () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        this.query = search.value;
        this.page = 0;
        this.render();
      }, 200);
    });

    pageSize.addEventListener("change", () => {
      this.pageSize = normalizePageSize(pageSize.value);
      writeStorage(STORAGE_PAGE_SIZE, this.pageSize);
      this.page = 0;
      this.render();
    });

    pages.addEventListener("click", (event) => {
      const button = event.target.closest("[data-page]");
      if (!button || button.disabled) return;
      this.page = Number.parseInt(button.dataset.page, 10) || 0;
      this.render();
      this.elements.table.scrollIntoView?.({
        block: "nearest",
        behavior: "smooth",
      });
    });

    table.addEventListener("click", (event) => {
      const retry = event.target.closest('[data-action="retry-workflow-run"]');
      if (retry) {
        const { workflowRunId } = retry.dataset;
        window.confirmRetryWorkflowRun?.(workflowRunId, () =>
          window.retryWorkflowRun?.(workflowRunId),
        );
        return;
      }

      const remove = event.target.closest(
        '[data-action="delete-workflow-run"]',
      );
      if (remove) {
        const { workflowRunId } = remove.dataset;
        window.confirmDeleteWorkflowRun?.(workflowRunId, () =>
          window.deleteWorkflowRun?.(workflowRunId),
        );
      }
    });

    onRepoFilterChange(() => {
      this.page = 0;
      this.render();
    });

    // Coming back to a hidden tab whose refresh came due refreshes at once.
    document.addEventListener("visibilitychange", () => {
      if (!document.hidden && !this.paused && this.remaining <= 0) {
        this.refresh();
      }
    });

    this.ticker = setInterval(() => this.tick(), 1000);
    this.refresh();
  }

  /** One second of the countdown; refreshes when it reaches zero. */
  tick() {
    if (this.paused || this.loading) return;
    if (document.hidden) {
      // Keep counting down, but wait for the tab to come back to load.
      this.remaining = Math.max(0, this.remaining - 1);
      this.renderCountdown();
      return;
    }

    this.remaining -= 1;
    if (this.remaining <= 0) {
      this.refresh();
      return;
    }

    this.renderCountdown();
  }

  /**
   * Pauses or resumes the automatic refresh. Resuming keeps the time left,
   * so pausing for a moment doesn't trigger a load.
   *
   * @param {boolean} paused - Whether to pause.
   */
  setPaused(paused) {
    this.paused = paused;
    const { pause } = this.elements;
    pause.setAttribute("aria-pressed", String(paused));
    pause.classList.toggle("active", paused);
    pause.title = paused
      ? "Resume the automatic refresh"
      : "Pause the automatic refresh";
    pause.innerHTML = paused
      ? '<i class="bi bi-play-fill"></i><span class="ms-1">Resume</span>'
      : '<i class="bi bi-pause-fill"></i><span class="ms-1">Pause</span>';
    if (!paused && this.remaining <= 0) {
      this.refresh();
      return;
    }
    this.renderCountdown();
  }

  /** Loads every run now and restarts the countdown. */
  async refresh() {
    if (this.loading || this.apiManager.isSessionInvalid) return;
    this.loading = true;
    this.renderCountdown();

    try {
      // eslint-disable-next-line compat/compat
      const response = await fetch(API_ENDPOINTS.WORKFLOW_RUNS, {
        credentials: "same-origin",
        cache: "no-store",
        headers: { Accept: "application/json" },
      });
      if (response.status === 401 || response.status === 403) {
        this.apiManager.isSessionInvalid = true;
        this.apiManager.showLoginModal();
        this.setPaused(true);
        return;
      }
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      this.update(await response.json());
      this.failed = false;
      this.onLoaded();
    } catch (error) {
      console.error("Failed to load workflow runs:", error);
      this.failed = true;
      if (this.header.length === 0) this.render();
    } finally {
      this.loading = false;
      this.remaining = this.interval;
      this.renderCountdown();
    }
  }

  /**
   * Keeps a response (header + rows, and each row's outcome) and redraws,
   * staying on the current page when it still exists.
   *
   * @param {object} data - The workflow-runs response.
   */
  update(data) {
    const table = Array.isArray(data?.workflow_runs) ? data.workflow_runs : [];
    const states = Array.isArray(data?.states) ? data.states : [];
    this.header = table[0] ?? [];
    const actionsColumn = this.header.findIndex((name) =>
      ACTIONS_COLUMN.test(String(name)),
    );
    this.rows = table.slice(1).map((cells, index) => ({
      cells,
      state: String(states.at(index) ?? "other"),
      text: cells
        .filter((_cell, position) => position !== actionsColumn)
        .map(cellText)
        .join(" "),
    }));
    this.total = Number(data?.total ?? this.rows.length) || 0;
    this.render();
  }

  /** The rows left after the repo filter and the search (before the outcome chip). */
  searchedRows() {
    const repoFilter = getRepoFilter();
    const filtered = filterRows(
      this.header,
      this.rows.map((row) => row.cells),
      repoFilter,
    );
    const kept = new Set(filtered.rows);
    const rows = this.rows.filter(
      (row) => kept.has(row.cells) && matchesSearch(row.text, this.query),
    );
    return { rows, hiddenByRepo: filtered.hidden, repoFilter };
  }

  render() {
    if (!this.elements) return;
    const { rows: searched, hiddenByRepo, repoFilter } = this.searchedRows();

    this.renderStates(searched);
    if (this.elements.counter)
      this.elements.counter.textContent = String(this.total);

    const { note } = this.elements;
    note.hidden = hiddenByRepo === 0;
    note.textContent = hiddenByRepo
      ? `${hiddenByRepo} of ${this.rows.length} runs hidden by the repo filter "${repoFilter}"`
      : "";

    const visible = this.state
      ? searched.filter((row) => row.state === this.state)
      : searched;

    const size =
      this.pageSize === "all" ? Math.max(visible.length, 1) : this.pageSize;
    const pageCount = Math.max(1, Math.ceil(visible.length / size));
    this.page = Math.min(Math.max(0, this.page), pageCount - 1);
    const start = this.page * size;
    const slice = visible.slice(start, start + size);

    this.renderTable(slice, start);
    this.renderPager(start, slice.length, visible.length, pageCount);
  }

  /**
   * The outcome chips with their counts (built with DOM APIs, text only).
   *
   * @param {Array<object>} rows - The rows the counts cover.
   */
  renderStates(rows) {
    const counts = new Map();
    for (const row of rows)
      counts.set(row.state, (counts.get(row.state) ?? 0) + 1);

    this.elements.states.replaceChildren(
      ...STATES.filter(
        ({ key }) => key === "" || counts.has(key) || key === this.state,
      ).map(({ key, label }) => {
        const active = this.state === key;
        const button = document.createElement("button");
        button.type = "button";
        button.className = `entity-chip wr-state-${key || "all"}${active ? " active" : ""}`;
        button.dataset.state = key;
        button.setAttribute("aria-pressed", String(active));
        const number = document.createElement("span");
        number.textContent = String(
          key === "" ? rows.length : (counts.get(key) ?? 0),
        );
        button.append(`${label} `, number);
        return button;
      }),
    );
  }

  /**
   * Draws one page of rows, numbered from their position in the whole list.
   *
   * @param {Array<object>} slice - The page's rows.
   * @param {number} start - Index of its first row.
   */
  renderTable(slice, start) {
    const { table } = this.elements;
    if (this.header.length === 0) {
      table.innerHTML = this.failed
        ? '<p class="text-muted text-center py-3 mb-0">Could not load the workflow runs.</p>'
        : '<p class="text-muted text-center py-3 mb-0">No workflow runs.</p>';
      return;
    }

    if (slice.length === 0) {
      table.innerHTML =
        '<p class="text-muted text-center py-3 mb-0">No workflow runs match the filters.</p>';
      return;
    }

    this.chartManager.drawDataTable(
      [
        ["#", ...this.header],
        ...slice.map((row, i) => [start + i + 1, ...row.cells]),
      ],
      "wr_table",
      {
        allowHtml: true,
        showRowNumber: false,
        pagination: false,
        repoFilter: false,
      },
    );
  }

  /**
   * "Showing 26–50 of 312" and the page buttons.
   *
   * @param {number} start - Index of the page's first row.
   * @param {number} shown - Rows on the page.
   * @param {number} total - Rows across every page.
   * @param {number} pageCount - Number of pages.
   */
  renderPager(start, shown, total, pageCount) {
    const { summary, pages } = this.elements;
    summary.textContent =
      total === 0
        ? "No runs"
        : `Showing ${start + 1}–${start + shown} of ${total}${total === this.rows.length ? "" : ` (${this.rows.length} loaded)`}`;

    if (pageCount <= 1) {
      pages.replaceChildren();
      return;
    }

    const button = (page, content, label, disabled = false, active = false) => {
      const element = document.createElement("button");
      element.type = "button";
      element.className = `btn btn-sm wr-page${active ? " active" : ""}`;
      element.dataset.page = String(page);
      element.disabled = disabled;
      element.setAttribute("aria-label", label);
      if (active) element.setAttribute("aria-current", "page");
      element.innerHTML = content;
      return element;
    };

    const last = pageCount - 1;
    const items = [
      button(
        0,
        '<i class="bi bi-chevron-double-left"></i>',
        "First page",
        this.page === 0,
      ),
      button(
        this.page - 1,
        '<i class="bi bi-chevron-left"></i>',
        "Previous page",
        this.page === 0,
      ),
      ...pageWindow(this.page, pageCount).map((page) => {
        if (page === null) {
          const gap = document.createElement("span");
          gap.className = "wr-page-gap";
          gap.textContent = "…";
          return gap;
        }
        return button(
          page,
          String(page + 1),
          `Page ${page + 1}`,
          false,
          page === this.page,
        );
      }),
      button(
        this.page + 1,
        '<i class="bi bi-chevron-right"></i>',
        "Next page",
        this.page === last,
      ),
      button(
        last,
        '<i class="bi bi-chevron-double-right"></i>',
        "Last page",
        this.page === last,
      ),
    ];
    pages.replaceChildren(...items);
  }

  renderCountdown() {
    const { countdown } = this.elements ?? {};
    if (!countdown) return;
    countdown.classList.toggle("text-warning", this.failed);
    if (this.loading) {
      countdown.textContent = "Refreshing…";
    } else if (this.paused) {
      countdown.textContent = "Paused";
    } else if (this.failed) {
      countdown.textContent = `Refresh failed, retrying in ${formatCountdown(this.remaining)}`;
    } else if (document.hidden && this.remaining <= 0) {
      countdown.textContent = "Refreshes when you come back";
    } else {
      countdown.textContent = `Next refresh in ${formatCountdown(this.remaining)}`;
    }
  }
}

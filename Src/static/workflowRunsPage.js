// workflowRunsPage.js
//
// The dedicated Workflow runs page (workflow-runs.php). Loads every tracked
// run once per refresh (GET api/v1/workflow-runs) and pages, filters and
// searches them in the browser, so moving between pages never refetches
// and a refresh keeps the page you are on. The refresh runs on its own
// timer: 30 sec to 10 min (1 min by default, remembered), with Pause /
// Resume and Refresh now. The countdown stops while the tab is hidden.
// Runs can be narrowed by outcome, by account (the repository owner), by
// sender (the run's actor) and by a free-text search.

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
const STORAGE_SORT = "workflowRunsSort";

// Orderings offered by the "Sort by" picker, in display order.
export const SORTS = [
  { key: "newest", label: "Newest first" },
  { key: "oldest", label: "Oldest first" },
  { key: "repository", label: "Repository (A–Z)" },
  { key: "repository_desc", label: "Repository (Z–A)" },
  { key: "workflow", label: "Workflow name (A–Z)" },
  { key: "workflow_desc", label: "Workflow name (Z–A)" },
];
export const DEFAULT_SORT = "newest";

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
 * A stored ordering, or the default when missing or not offered.
 *
 * @param {*} value - The stored value.
 * @returns {string}
 */
export function normalizeSort(value) {
  return SORTS.some((sort) => sort.key === value) ? value : DEFAULT_SORT;
}

const textOrder = new Intl.Collator(undefined, {
  sensitivity: "base",
  numeric: true,
});

/**
 * Compares two "YYYY-MM-DD HH:MM:SS" times, newest first; a run without
 * a time goes last.
 *
 * @param {string} first - One time.
 * @param {string} second - The other.
 * @returns {number}
 */
function newestFirst(first, second) {
  if (first === second) return 0;
  if (first === "") return 1;
  if (second === "") return -1;
  return first < second ? 1 : -1;
}

/**
 * Orders two rows by their sort keys ({ repository, workflow, updatedAt }).
 * Ties fall back to the newest run first, so a repository's or workflow's
 * runs read from the latest down.
 *
 * @param {object} first - One row's sort keys.
 * @param {object} second - The other's.
 * @param {string} sort - One of SORTS' keys.
 * @returns {number}
 */
export function compareRuns(first, second, sort) {
  const newest = newestFirst(first.updatedAt, second.updatedAt);
  const repository = textOrder.compare(first.repository, second.repository);
  const workflow = textOrder.compare(first.workflow, second.workflow);

  switch (sort) {
    case "oldest":
      if (first.updatedAt === "" || second.updatedAt === "") return newest;
      return -newest || repository;
    case "repository":
      return repository || workflow || newest;
    case "repository_desc":
      return -repository || workflow || newest;
    case "workflow":
      return workflow || repository || newest;
    case "workflow_desc":
      return -workflow || repository || newest;
    default:
      return newest || repository;
  }
}

/**
 * The values offered by the Account or Sender picker: every distinct
 * non-empty value of one sort key among the rows, with how many rows carry
 * it, in name order. The selected value stays offered (with 0) when no row
 * carries it any more, so a refresh never silently drops the choice.
 *
 * @param {Array<object>} rows - Rows with their sort keys under `keys`.
 * @param {(keys: object) => string} pick - Reads the value from a row's keys.
 * @param {string} [selected] - The value currently picked ("" for all).
 * @returns {Array<{value: string, count: number}>}
 */
export function filterOptions(rows, pick, selected = "") {
  const counts = new Map();
  for (const row of rows) {
    const value = String((row?.keys ? pick(row.keys) : "") ?? "");
    if (value !== "") counts.set(value, (counts.get(value) ?? 0) + 1);
  }
  if (selected !== "" && !counts.has(selected)) counts.set(selected, 0);
  return [...counts.entries()]
    .map(([value, count]) => ({ value, count }))
    .sort((first, second) => textOrder.compare(first.value, second.value));
}

/**
 * Whether a row belongs to the picked account and sender ("" for any).
 *
 * @param {object} keys - The row's sort keys.
 * @param {string} account - The picked account.
 * @param {string} sender - The picked sender.
 * @returns {boolean}
 */
export function matchesPeople(keys, account, sender) {
  return (
    (account === "" || keys.owner === account) &&
    (sender === "" || keys.actor === sender)
  );
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

/**
 * One row's sort keys from the API (plain strings, never undefined).
 *
 * @param {*} keys - The row's sort_keys entry.
 * @returns {{repository: string, workflow: string, updatedAt: string, owner: string, actor: string}}
 */
function toSortKeys(keys) {
  const result = {
    repository: String(keys?.repository ?? ""),
    workflow: String(keys?.workflow ?? ""),
    updatedAt: String(keys?.updated_at ?? ""),
    owner: String(keys?.owner ?? ""),
    actor: String(keys?.actor ?? ""),
  };
  return result;
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
    this.sort = normalizeSort(readStorage(STORAGE_SORT));
    this.paused = false;
    this.remaining = 0;
    this.loading = false;
    this.failed = false;
    this.ticker = null;

    this.header = [];
    this.rows = []; // { cells, state, text, keys }
    this.total = 0;
    this.state = "";
    this.account = "";
    this.sender = "";
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
      account: document.getElementById("wr_account"),
      sender: document.getElementById("wr_sender"),
      search: document.getElementById("wr_search"),
      note: document.getElementById("wr_note"),
      table: document.getElementById("wr_table"),
      summary: document.getElementById("wr_summary"),
      pages: document.getElementById("wr_pages"),
      pageSize: document.getElementById("wr_page_size"),
      sort: document.getElementById("wr_sort"),
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

    const sortPicker = this.elements.sort;
    sortPicker.value = this.sort;
    sortPicker.addEventListener("change", () => {
      this.sort = normalizeSort(sortPicker.value);
      writeStorage(STORAGE_SORT, this.sort);
      this.page = 0;
      this.render();
    });

    pause.addEventListener("click", () => this.setPaused(!this.paused));
    refreshNow.addEventListener("click", () => {
      void this.refresh();
    });
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

    this.elements.account?.addEventListener("change", (event) => {
      this.account = event.target.value;
      this.page = 0;
      this.render();
    });
    this.elements.sender?.addEventListener("change", (event) => {
      this.sender = event.target.value;
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
        const workflowRunId = retry.dataset.workflowRunId;
        window.confirmRetryWorkflowRun?.(workflowRunId, () =>
          window.retryWorkflowRun?.(workflowRunId),
        );
        return;
      }

      const remove = event.target.closest(
        '[data-action="delete-workflow-run"]',
      );
      if (remove) {
        const workflowRunId = remove.dataset.workflowRunId;
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
        void this.refresh();
      }
    });

    this.ticker = setInterval(() => this.tick(), 1000);
    void this.refresh();
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
      void this.refresh();
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
    const pause = this.elements.pause;
    pause.setAttribute("aria-pressed", String(paused));
    pause.classList.toggle("active", paused);
    pause.title = paused
      ? "Resume the automatic refresh"
      : "Pause the automatic refresh";
    pause.innerHTML = paused
      ? '<i class="bi bi-play-fill"></i><span class="ms-1">Resume</span>'
      : '<i class="bi bi-pause-fill"></i><span class="ms-1">Pause</span>';
    if (!paused && this.remaining <= 0) {
      void this.refresh();
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
    const sortKeys = Array.isArray(data?.sort_keys) ? data.sort_keys : [];
    this.header = table[0] ?? [];
    const actionsColumn = this.header.findIndex((name) =>
      ACTIONS_COLUMN.test(String(name)),
    );
    this.rows = table.slice(1).map((cells, index) => ({
      cells,
      state: String(states.at(index) ?? "other"),
      keys: toSortKeys(sortKeys.at(index)),
      text: cells
        .filter((_cell, position) => position !== actionsColumn)
        .map(cellText)
        .join(" "),
    }));
    this.total = Number(data?.total ?? this.rows.length) || 0;
    this.render();
  }

  /**
   * The rows left after the repo filter, the search, and the account and
   * sender pickers (before the outcome chip). Each picker's options are
   * counted with every filter but its own applied, so they show how many
   * runs choosing them would leave.
   */
  searchedRows() {
    const repoFilter = getRepoFilter();
    const filtered = filterRows(
      this.header,
      this.rows.map((row) => row.cells),
      repoFilter,
    );
    const kept = new Set(filtered.rows);
    const base = this.rows.filter(
      (row) => kept.has(row.cells) && matchesSearch(row.text, this.query),
    );
    const rows = base.filter((row) =>
      matchesPeople(row.keys, this.account, this.sender),
    );
    const result = {
      rows: rows,
      accountOptions: filterOptions(
        base.filter((row) => matchesPeople(row.keys, "", this.sender)),
        (keys) => keys.owner,
        this.account,
      ),
      senderOptions: filterOptions(
        base.filter((row) => matchesPeople(row.keys, this.account, "")),
        (keys) => keys.actor,
        this.sender,
      ),
      hiddenByRepo: filtered.hidden,
      repoFilter: repoFilter,
    };
    return result;
  }

  render() {
    if (!this.elements) return;
    const result = this.searchedRows();
    const searched = result.rows;
    const hiddenByRepo = result.hiddenByRepo;
    const repoFilter = result.repoFilter;

    this.renderStates(searched);
    this.renderPicker(
      this.elements.account,
      result.accountOptions,
      this.account,
      "All accounts",
    );
    this.renderPicker(
      this.elements.sender,
      result.senderOptions,
      this.sender,
      "All senders",
    );
    if (this.elements.counter)
      this.elements.counter.textContent = String(this.total);

    const note = this.elements.note;
    note.hidden = hiddenByRepo === 0;
    note.textContent = hiddenByRepo
      ? `${hiddenByRepo} of ${this.rows.length} runs hidden by the repo filter "${repoFilter}"`
      : "";

    const visible = searched
      .filter((row) => this.state === "" || row.state === this.state)
      .sort((first, second) => compareRuns(first.keys, second.keys, this.sort));

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
        (entry) =>
          entry.key === "" || counts.has(entry.key) || entry.key === this.state,
      ).map((entry) => {
        const key = entry.key;
        const label = entry.label;
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
   * Rebuilds the Account or Sender picker's options (DOM APIs, text only),
   * keeping the current choice selected. Left alone while it has focus, so
   * an automatic refresh doesn't close a list being picked from.
   *
   * @param {HTMLSelectElement|null} select - The picker.
   * @param {Array<{value: string, count: number}>} options - From filterOptions.
   * @param {string} selected - The value picked ("" for all).
   * @param {string} allLabel - The "no filter" option's text.
   */
  renderPicker(select, options, selected, allLabel) {
    if (!select || document.activeElement === select) return;
    const all = document.createElement("option");
    all.value = "";
    all.textContent = allLabel;
    select.replaceChildren(
      all,
      ...options.map((entry) => {
        const option = document.createElement("option");
        option.value = entry.value;
        option.textContent = `${entry.value} (${entry.count})`;
        return option;
      }),
    );
    select.value = selected;
    select.classList.toggle("active", selected !== "");
  }

  /**
   * Draws one page of rows, numbered from their position in the whole list.
   *
   * @param {Array<object>} slice - The page's rows.
   * @param {number} start - Index of its first row.
   */
  renderTable(slice, start) {
    const table = this.elements.table;
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
    const summary = this.elements.summary;
    const pages = this.elements.pages;
    let text = "No runs";
    if (total > 0) {
      text = `Showing ${start + 1}–${start + shown} of ${total}`;
      if (total !== this.rows.length) {
        text += ` (${this.rows.length} loaded)`;
      }
    }
    summary.textContent = text;

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
    const countdown = this.elements?.countdown;
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

// gitHubApiProxyCallers.js
//
// The "GitHub API callers" table with chips that filter it by caller type
// (user, bot, app installation, …), each chip showing its count. The chosen
// type survives the 60-second refresh; a type that disappears from the data
// falls back to "All".

import { CHART_OPTIONS } from "./constants.js";

// Caller types in chip order, as sent in the payload's callerTypes.
const TYPES = new Map(
  Object.entries({
    user: "Users",
    bot: "Bots",
    installation: "App installations",
    app: "GitHub Apps",
    token: "Resolving",
    invalid: "Invalid tokens",
    anonymous: "Anonymous",
    unknown: "Unknown",
  }),
);

export class GitHubApiProxyCallersTable {
  /**
   * @param {import('./charts.js').ChartManager} chartManager - Draws the table.
   * @param {string} elementId - The section's container.
   */
  constructor(chartManager, elementId) {
    this.chartManager = chartManager;
    this.elementId = elementId;
    this.header = [];
    this.callers = [];
    this.filter = null;
  }

  /**
   * Stores the callers rows (header first) and their types, then redraws.
   *
   * @param {Array<Array<string>>} table - Header row followed by one row per caller.
   * @param {Array<string>} types - Each caller row's type, header excluded.
   */
  update(table, types) {
    const [header = [], ...rows] = Array.isArray(table) ? table : [];
    const typeList = Array.isArray(types) ? types : [];
    this.header = header;
    this.callers = rows.map((row, index) => ({
      row,
      type: typeList.at(index) ?? "unknown",
    }));
    this.render();
  }

  /**
   * Number of callers per type, in chip order.
   *
   * @returns {Array<[string, number]>}
   */
  counts() {
    const counts = new Map();
    for (const { type } of this.callers) {
      counts.set(type, (counts.get(type) ?? 0) + 1);
    }

    const known = [...TYPES.keys()].filter((type) => counts.has(type));
    const other = [...counts.keys()].filter((type) => !TYPES.has(type));
    return [...known, ...other].map((type) => [type, counts.get(type)]);
  }

  /**
   * Creates the chip bar and the table holder once, wiring the chip clicks.
   *
   * @param {HTMLElement} container - The section's container.
   */
  ensureLayout(container) {
    if (container.querySelector(".entity-chips")) {
      return;
    }

    container.innerHTML = `<div class="entity-chips" role="toolbar" aria-label="Filter callers by type"></div><div id="${this.elementId}_table"></div>`;
    container
      .querySelector(".entity-chips")
      .addEventListener("click", (event) => {
        const chip = event.target.closest("[data-type]");
        if (!chip) {
          return;
        }

        this.filter = chip.dataset.type === "" ? null : chip.dataset.type;
        this.render();
      });
  }

  render() {
    const container = document.getElementById(this.elementId);
    if (!container) {
      return;
    }

    this.ensureLayout(container);

    const counts = this.counts();
    if (
      this.filter !== null &&
      !counts.some(([type]) => type === this.filter)
    ) {
      this.filter = null;
    }

    // Rebuilding the chips drops keyboard focus; put it back on the same chip.
    const focused = document.activeElement?.closest?.(
      ".entity-chips [data-type]",
    );
    const focusedType =
      focused && container.contains(focused) ? focused.dataset.type : null;

    const chip = (type, label, count) => {
      const active = (this.filter ?? "") === type;
      return `<button type="button" class="entity-chip${active ? " active" : ""}" data-type="${type}" aria-pressed="${active}">${label} <span>${count}</span></button>`;
    };
    container.querySelector(".entity-chips").innerHTML =
      chip("", "All", this.callers.length) +
      counts
        .map(([type, count]) => chip(type, TYPES.get(type) ?? type, count))
        .join("");
    if (focusedType !== null) {
      // The focused type may have disappeared on refresh: fall back to All.
      const chips = container.querySelector(".entity-chips");
      const replacement = chips.querySelector(
        `[data-type="${CSS.escape(focusedType)}"]`,
      );
      (replacement ?? chips.querySelector('[data-type=""]'))?.focus();
    }

    const rows = this.callers
      .filter(({ type }) => this.filter === null || type === this.filter)
      .map(({ row }) => row);
    this.chartManager.drawDataTable(
      [this.header, ...rows],
      `${this.elementId}_table`,
      CHART_OPTIONS.table,
    );

    const counter = document.getElementById(`counter_${this.elementId}`);
    if (counter) {
      counter.textContent =
        this.filter === null
          ? String(this.callers.length)
          : `${rows.length}/${this.callers.length}`;
    }
  }
}

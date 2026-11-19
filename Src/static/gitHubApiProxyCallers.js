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

    // Built with DOM APIs, so no value is ever parsed as HTML.
    const chips = document.createElement("div");
    chips.className = "entity-chips";
    chips.setAttribute("role", "toolbar");
    chips.setAttribute("aria-label", "Filter callers by type");
    const table = document.createElement("div");
    table.id = `${this.elementId}_table`;
    container.replaceChildren(chips, table);

    chips.addEventListener("click", (event) => {
      const chip = event.target.closest("[data-type]");
      if (!chip) {
        return;
      }

      this.filter = chip.dataset.type === "" ? null : chip.dataset.type;
      this.render();
    });
  }

  /**
   * One filter chip: the label as text plus the count in a badge.
   *
   * @param {string} type - Caller type ("" for All).
   * @param {string} label - Text shown on the chip.
   * @param {number} count - Callers of that type.
   * @returns {HTMLButtonElement}
   */
  chipButton(type, label, count) {
    const active = (this.filter ?? "") === type;
    const button = document.createElement("button");
    button.type = "button";
    button.className = active ? "entity-chip active" : "entity-chip";
    button.dataset.type = type;
    button.setAttribute("aria-pressed", String(active));

    const badge = document.createElement("span");
    badge.textContent = String(count);
    button.append(`${label} `, badge);
    return button;
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

    const buttons = [
      this.chipButton("", "All", this.callers.length),
      ...counts.map(([type, count]) =>
        this.chipButton(type, TYPES.get(type) ?? type, count),
      ),
    ];
    container.querySelector(".entity-chips").replaceChildren(...buttons);
    if (focusedType !== null) {
      // The focused type may have disappeared on refresh: fall back to All.
      const replacement = buttons.find(
        (button) => button.dataset.type === focusedType,
      );
      (replacement ?? buttons[0]).focus();
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

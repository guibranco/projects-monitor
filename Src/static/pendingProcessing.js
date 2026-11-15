// pendingProcessing.js
//
// One "Pending processing" table for the eight webhook entities (branches,
// comments, installations, issues, pull requests, pushes, repositories,
// users) instead of eight sections. Each endpoint's rows are kept per
// entity; chips filter the table by entity and show each one's count.

const ENTITIES = new Map(
  Object.entries({
    branches_processing: "Branches",
    comments_processing: "Comments",
    installations_processing: "Installations",
    issues_processing: "Issues",
    pr_processing: "Pull requests",
    pushes_processing: "Pushes",
    repositories_processing: "Repositories",
    users_processing: "Users",
  }),
);

// Columns every entity shares; the rest are folded into "Details".
const COMMON = new Set([
  "#Seq",
  "Sender",
  "State",
  "Processing Date",
  "Receiver",
  "Handler",
  "Bot",
]);

const HEADER = [
  "Entity",
  "#Seq",
  "Details",
  "Sender",
  "State",
  "Processing Date",
  "Versions",
];

export class PendingProcessingTable {
  /**
   * @param {import('./charts.js').ChartManager} chartManager - Draws the table.
   * @param {string} elementId - The section's container.
   */
  constructor(chartManager, elementId) {
    this.chartManager = chartManager;
    this.elementId = elementId;
    this.rowsByEntity = new Map();
    this.filter = null;
  }

  /**
   * Stores one endpoint's rows and redraws.
   *
   * @param {string} sourceId - The old section id (e.g. "pr_processing").
   * @param {Array<object>} items - The endpoint's rows.
   * @param {Array<{label:string, render:Function}>} columns - How to render them.
   */
  update(sourceId, items, columns) {
    const entity = ENTITIES.get(sourceId) ?? sourceId;
    const rows = (Array.isArray(items) ? items : []).map((item) => {
      const cells = new Map(
        columns.map((column) => [column.label, column.render(item)]),
      );
      const details = columns
        .filter((column) => !COMMON.has(column.label))
        .map(
          (column) =>
            `<span class="pp-detail"><small>${column.label}</small> ${cells.get(column.label)}</span>`,
        )
        .join(" ");
      const versions = ["Receiver", "Handler", "Bot"]
        .map(
          (label) => `<span title="${label}">${cells.get(label) ?? "—"}</span>`,
        )
        .join(" / ");
      const row = [
        entity,
        cells.get("#Seq"),
        details,
        cells.get("Sender") ?? "—",
        cells.get("State"),
        cells.get("Processing Date"),
        versions,
      ];
      return row;
    });

    this.rowsByEntity.set(entity, rows);
    this.render();
  }

  render() {
    const container = document.getElementById(this.elementId);
    if (!container) {
      return;
    }

    if (!container.querySelector(".entity-chips")) {
      container.innerHTML = `<div class="entity-chips" role="toolbar" aria-label="Filter by entity"></div><div id="${this.elementId}_table"></div>`;
      container
        .querySelector(".entity-chips")
        .addEventListener("click", (event) => {
          const chip = event.target.closest("[data-entity]");
          if (!chip) return;
          this.filter = chip.dataset.entity === "" ? null : chip.dataset.entity;
          this.render();
        });
    }

    const counts = [...this.rowsByEntity].map(([entity, rows]) => [
      entity,
      rows.length,
    ]);
    const total = counts.reduce((sum, [, count]) => sum + count, 0);
    const chip = (entity, label, count) => {
      const active = (this.filter ?? "") === entity ? " active" : "";
      return `<button type="button" class="entity-chip${active}" data-entity="${entity}" aria-pressed="${active !== ""}">${label} <span>${count}</span></button>`;
    };
    container.querySelector(".entity-chips").innerHTML =
      chip("", "All", total) +
      counts.map(([entity, count]) => chip(entity, entity, count)).join("");

    const rows = [...this.rowsByEntity]
      .filter(([entity]) => this.filter === null || entity === this.filter)
      .flatMap(([, entityRows]) => entityRows);

    const counter = document.getElementById(`counter_${this.elementId}`);
    if (counter) counter.textContent = total;

    const tableId = `${this.elementId}_table`;
    if (rows.length === 0) {
      const table = document.getElementById(tableId);
      if (table)
        table.innerHTML =
          '<p class="text-muted text-center py-3 mb-0">Nothing pending processing.</p>';
      return;
    }

    this.chartManager.drawDataTable([HEADER, ...rows], tableId, {
      allowHtml: true,
      showRowNumber: false,
      pagination: true,
    });
  }
}

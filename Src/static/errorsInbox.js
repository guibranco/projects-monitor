// errorsInbox.js
//
// The Errors page's single list (GET api/v1/errors-inbox): application
// messages, PHP errors stored in the database and error_log entries not
// ingested yet, grouped by fingerprint on the server. Chips filter by
// source; each row offers the actions of the sources it came from.

const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");

const SHORT_LABELS = new Map(
  Object.entries({ app: "App", php: "PHP", error_log: "error_log" }),
);

const HEADER = ["Actions", "Source", "Where", "Message", "Count", "Last seen"];

const MESSAGE_PREVIEW = 160;

/**
 * Whether a group came (at least partly) from a source.
 *
 * @param {object} group - A group of the response.
 * @param {string|null} source - The source, null for any.
 * @returns {boolean}
 */
export function groupHasSource(group, source) {
  return source === null || Object.hasOwn(group.sources || {}, source);
}

/**
 * The action buttons of a group.
 *
 * @param {object} group - A group of the response.
 * @returns {string}
 */
export function groupActionsHtml(group) {
  const actions = group.actions || {};
  const buttons = [];
  const sampleIds = actions.sampleIds || [];
  if (sampleIds.length > 0) {
    const more =
      sampleIds.length > 1 ? ` (1 of ${sampleIds.length} variants)` : "";
    buttons.push(`<button type="button" class="btn btn-sm btn-view-details py-0 px-2"
      data-action="view-msg-details" data-sample-id="${Number(sampleIds[0])}"
      data-app="${escapeHtml(group.origin)}" data-msg="${escapeHtml(group.message)}"
      title="View individual messages${more}" aria-label="View individual messages${more}">
      <i class="bi bi-eye-fill"></i></button>`);
  }

  for (const path of actions.paths || []) {
    const name = String(path).split("/").slice(-2).join("/");
    buttons.push(`<button type="button" class="btn btn-danger btn-sm py-0 px-2"
      data-action="delete-path" data-path="${encodeURIComponent(path)}"
      title="Delete every stored error of ${escapeHtml(path)}"
      aria-label="Delete every stored error of ${escapeHtml(name)}"><i class="bi bi-trash2"></i></button>`);
  }

  for (const directory of actions.directories || []) {
    buttons.push(`<button type="button" class="btn btn-outline-danger btn-sm py-0 px-2"
      data-action="delete-file" data-directory="${escapeHtml(directory)}"
      title="Delete the error_log file in ${escapeHtml(directory)}"
      aria-label="Delete the error_log file in ${escapeHtml(directory)}"><i class="bi bi-file-earmark-x"></i></button>`);
  }

  return `<span class="errors-inbox-actions">${buttons.join("")}</span>`;
}

/**
 * The table row of a group.
 *
 * @param {object} group - A group of the response.
 * @returns {Array<string|number>}
 */
export function groupRow(group) {
  const sources = Object.entries(group.sources || {})
    .map(
      ([source, count]) =>
        `<span class="errors-inbox-source" title="${count} from ${escapeHtml(source)}">${escapeHtml(SHORT_LABELS.get(source) ?? source)}</span>`,
    )
    .join(" ");
  const message = String(group.message ?? "");
  const preview =
    message.length > MESSAGE_PREVIEW
      ? `${message.substring(0, MESSAGE_PREVIEW)}…`
      : message;

  return [
    groupActionsHtml(group),
    sources,
    `<code class="errors-inbox-origin">${escapeHtml(group.origin)}</code>`,
    `<span title="${escapeHtml(message)}">${escapeHtml(preview)}</span>`,
    Number(group.count) || 0,
    group.lastSeen ?? "—",
  ];
}

export class ErrorsInboxTable {
  /**
   * @param {import('./charts.js').ChartManager} chartManager - Draws the table.
   * @param {string} elementId - The section's container.
   */
  constructor(chartManager, elementId) {
    this.chartManager = chartManager;
    this.elementId = elementId;
    this.filter = null;
    this.response = null;
  }

  /**
   * Keeps the response and redraws.
   *
   * @param {object} response - The errors-inbox response.
   */
  update(response) {
    this.response = response || { groups: [], sources: {}, total: 0 };
    this.render();
  }

  render() {
    const container = document.getElementById(this.elementId);
    if (!container || !this.response) {
      return;
    }

    if (!container.querySelector(".entity-chips")) {
      container.innerHTML = `<div class="entity-chips" role="toolbar" aria-label="Filter by source"></div><div id="${this.elementId}_table"></div>`;
      container.addEventListener("click", (event) => this.onClick(event));
    }

    const { groups = [], sources = {}, total = 0 } = this.response;
    const chip = (source, label, count, error) => {
      const active = (this.filter ?? "") === source ? " active" : "";
      const warning = error
        ? ` <i class="bi bi-exclamation-triangle-fill text-warning" title="Could not read: ${escapeHtml(error)}"></i>`
        : "";
      return `<button type="button" class="entity-chip${active}" data-source="${escapeHtml(source)}" aria-pressed="${active !== ""}">${escapeHtml(label)} <span>${count}</span>${warning}</button>`;
    };
    container.querySelector(".entity-chips").innerHTML =
      chip("", "All", total, null) +
      Object.entries(sources)
        .map(([source, entry]) =>
          chip(source, entry.label, entry.groups, entry.error),
        )
        .join("");

    const counter = document.getElementById(`counter_${this.elementId}`);
    if (counter) counter.textContent = total;

    const rows = groups
      .filter((group) => groupHasSource(group, this.filter))
      .map(groupRow);
    const tableId = `${this.elementId}_table`;
    if (rows.length === 0) {
      const table = document.getElementById(tableId);
      if (table)
        table.innerHTML =
          '<p class="text-muted text-center py-3 mb-0">No errors from this source.</p>';
      return;
    }

    this.chartManager.drawDataTable([HEADER, ...rows], tableId, {
      allowHtml: true,
      showRowNumber: false,
      pagination: true,
    });
  }

  /**
   * Chips filter; row buttons call the existing confirm-then-act hooks.
   *
   * @param {MouseEvent} event - The click.
   */
  onClick(event) {
    const chip = event.target.closest("[data-source]");
    if (chip) {
      this.filter = chip.dataset.source === "" ? null : chip.dataset.source;
      this.render();
      return;
    }

    const button = event.target.closest("[data-action]");
    if (!button) return;
    const { action } = button.dataset;
    if (action === "view-msg-details") {
      window.openMessageDetails?.(
        button.dataset.sampleId,
        button.dataset.app,
        button.dataset.msg,
      );
    } else if (action === "delete-path") {
      const path = decodeURIComponent(button.dataset.path ?? "");
      window.confirmDeleteErrorsByPath?.(path, () =>
        window.deleteErrorsByPath?.(path),
      );
    } else if (action === "delete-file") {
      const { directory } = button.dataset;
      window.confirmDeleteError?.(directory, () =>
        window.deleteErrorLogFile?.(directory),
      );
    }
  }
}

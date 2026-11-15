// gridManager.js
import { TablePageSizeState } from './storage.js';
import { relativeTimeHtml } from './timeFormat.js';

// Tables show 5 rows unless the viewer picked another size or "View all".
const PAGE_SIZE_OPTIONS = [5, 10, 25, 50, 100];
const DEFAULT_PAGE_SIZE = 5;

export class GridManager {
  constructor() {
    this.gridjs = window.gridjs;
    this.grids = new Map(); // elementId -> live Grid instance
    this.pageSizeState = new TablePageSizeState(); // elementId -> persisted page size (localStorage-backed)
  }

  /**
   * Renders (or fully re-renders) a Grid.js table into elementId from a
   * Google-style [header, ...rows] array. Mirrors the previous
   * google.visualization.Table semantics: allowHtml, showRowNumber,
   * hideColumn, and the "[[]]" empty sentinel are all preserved.
   */
  draw(data, elementId, options = {}, hideColumn = -1) {
    const el = document.getElementById(elementId);
    if (!el) {
      // Expected: each page only holds its own sections.
      return null;
    }

    const headerRow = Array.isArray(data) && data.length > 0 ? data[0] : [];
    const bodyRows = Array.isArray(data) && data.length > 1 ? data.slice(1) : [];

    // Always fully recreate, matching the old "new google.visualization.Table()
    // fresh on every redraw" semantics, instead of diffing/updateConfig.
    el.innerHTML = '';
    this.grids.delete(elementId);

    if (!headerRow || headerRow.length === 0) {
      // "[[]]" empty-state sentinel: render nothing, matching the old
      // arrayToDataTable([[]]) behavior of an effectively blank table.
      return null;
    }

    const showRowNumber = options.showRowNumber !== false;
    const allowHtml = options.allowHtml === true;
    const paginated = options.pagination !== false;

    let columns = headerRow.map((h, i) => ({
      name: String(h ?? ''),
      hidden: i === hideColumn,
    }));
    if (showRowNumber) {
      columns = [{ name: '#' }, ...columns];
    }

    // Dates show as "5m ago" with the exact value on hover.
    const wrapCell = (cell) => {
      const relative = typeof cell === 'string' ? relativeTimeHtml(cell) : null;
      if (relative) {
        return this.gridjs.html(relative);
      }

      return allowHtml && typeof cell === 'string' ? this.gridjs.html(cell) : cell;
    };

    const gridData = bodyRows.map((row, i) => {
      const cells = showRowNumber ? [i + 1, ...row] : [...row];
      return cells.map(wrapCell);
    });

    if (paginated) {
      el.appendChild(this.#buildPageSizeToolbar(elementId, gridData.length));
    }

    const gridContainer = document.createElement('div');
    el.appendChild(gridContainer);

    const grid = new this.gridjs.Grid({
      columns,
      data: gridData,
      sort: false,
      pagination: paginated ? { limit: this.#resolveLimit(elementId, gridData.length), summary: true } : false,
      search: false,
      resizable: false,
      className: { table: 'gridjs-table-dark' },
    });

    grid.render(gridContainer);
    this.grids.set(elementId, grid);
    return grid;
  }

  /** Resolves the persisted page-size preference into a concrete Grid.js
   *  pagination "limit". 'all' maps to the current row count so every row
   *  renders on a single page (never 0, in case the table is empty). */
  #resolveLimit(elementId, totalRows) {
    const selected = this.pageSizeState.getPageSize(elementId, DEFAULT_PAGE_SIZE);
    return selected === 'all' ? Math.max(totalRows, 1) : selected;
  }

  #buildPageSizeToolbar(elementId, totalRows) {
    const currentSize = this.pageSizeState.getPageSize(elementId, DEFAULT_PAGE_SIZE);

    const optionsHtml = PAGE_SIZE_OPTIONS
      .map((n) => `<option value="${n}"${n === currentSize ? ' selected' : ''}>${n}</option>`)
      .join('') + `<option value="all"${currentSize === 'all' ? ' selected' : ''}>All</option>`;

    const toolbar = document.createElement('div');
    toolbar.className = 'gridjs-page-size-toolbar';
    const viewAll = totalRows > DEFAULT_PAGE_SIZE && currentSize !== 'all'
      ? `<button type="button" class="btn btn-link btn-sm gridjs-view-all">View all ${totalRows}</button>`
      : '';
    toolbar.innerHTML = `
      ${viewAll}
      <label>
        Rows per page:
        <select class="gridjs-page-size-select" aria-label="Rows per page">
          ${optionsHtml}
        </select>
      </label>`;

    const select = toolbar.querySelector('select');
    const applySize = (newSize) => {
      this.pageSizeState.setPageSize(elementId, newSize);
      const grid = this.grids.get(elementId);
      const newLimit = newSize === 'all' ? Math.max(totalRows, 1) : newSize;
      grid?.updateConfig({ pagination: { limit: newLimit, summary: true } }).forceRender();
      select.value = String(newSize);
      toolbar.querySelector('.gridjs-view-all')?.remove();
    };

    select.addEventListener('change', (e) => {
      const raw = e.target.value;
      applySize(raw === 'all' ? 'all' : parseInt(raw, 10));
    });
    toolbar.querySelector('.gridjs-view-all')?.addEventListener('click', () => applySize('all'));

    return toolbar;
  }
}

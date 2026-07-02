// gridManager.js
import { TablePageSizeState } from './storage.js';

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];
const DEFAULT_PAGE_SIZE = 10;

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
      console.error(`Element with id ${elementId} not found`);
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

    const wrapCell = (cell) => (allowHtml && typeof cell === 'string'
      ? this.gridjs.html(cell)
      : cell);

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
    toolbar.innerHTML = `
      <label>
        Rows per page:
        <select class="gridjs-page-size-select" aria-label="Rows per page">
          ${optionsHtml}
        </select>
      </label>`;

    toolbar.querySelector('select').addEventListener('change', (e) => {
      const raw = e.target.value;
      const newSize = raw === 'all' ? 'all' : parseInt(raw, 10);
      this.pageSizeState.setPageSize(elementId, newSize);
      const grid = this.grids.get(elementId);
      const newLimit = newSize === 'all' ? Math.max(totalRows, 1) : newSize;
      grid?.updateConfig({ pagination: { limit: newLimit, summary: true } }).forceRender();
    });

    return toolbar;
  }
}

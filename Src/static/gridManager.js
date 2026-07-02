// gridManager.js
const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];
const DEFAULT_PAGE_SIZE = 10;

export class GridManager {
  constructor() {
    this.gridjs = window.gridjs;
    this.grids = new Map();     // elementId -> live Grid instance
    this.pageSizes = new Map(); // elementId -> user-selected page size, persists across redraws/polls
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
      el.appendChild(this.#buildPageSizeToolbar(elementId));
    }

    const gridContainer = document.createElement('div');
    el.appendChild(gridContainer);

    const pageSize = this.pageSizes.get(elementId) ?? DEFAULT_PAGE_SIZE;

    const grid = new this.gridjs.Grid({
      columns,
      data: gridData,
      sort: false,
      pagination: paginated ? { limit: pageSize, summary: true } : false,
      search: false,
      resizable: false,
      className: { table: 'gridjs-table-dark' },
    });

    grid.render(gridContainer);
    this.grids.set(elementId, grid);
    return grid;
  }

  #buildPageSizeToolbar(elementId) {
    const currentSize = this.pageSizes.get(elementId) ?? DEFAULT_PAGE_SIZE;

    const toolbar = document.createElement('div');
    toolbar.className = 'gridjs-page-size-toolbar';
    toolbar.innerHTML = `
      <label>
        Rows per page:
        <select class="gridjs-page-size-select" aria-label="Rows per page">
          ${PAGE_SIZE_OPTIONS.map((n) => `<option value="${n}"${n === currentSize ? ' selected' : ''}>${n}</option>`).join('')}
        </select>
      </label>`;

    toolbar.querySelector('select').addEventListener('change', (e) => {
      const newSize = parseInt(e.target.value, 10);
      this.pageSizes.set(elementId, newSize);
      const grid = this.grids.get(elementId);
      grid?.updateConfig({ pagination: { limit: newSize, summary: true } }).forceRender();
    });

    return toolbar;
  }
}

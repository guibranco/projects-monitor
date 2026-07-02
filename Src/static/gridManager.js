// gridManager.js
export class GridManager {
  constructor() {
    this.gridjs = window.gridjs;
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

    if (!headerRow || headerRow.length === 0) {
      // "[[]]" empty-state sentinel: render nothing, matching the old
      // arrayToDataTable([[]]) behavior of an effectively blank table.
      return null;
    }

    const showRowNumber = options.showRowNumber !== false;
    const allowHtml = options.allowHtml === true;

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

    const grid = new this.gridjs.Grid({
      columns,
      data: gridData,
      sort: false,
      pagination: false,
      search: false,
      resizable: false,
      className: { table: 'gridjs-table-dark' },
    });

    grid.render(el);
    return grid;
  }
}

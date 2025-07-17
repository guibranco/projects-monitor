// charts.js
export class ChartManager {
  constructor() {
    this.google = window.google;
  }

  /**
   * Recursively merges two objects, deeply combining their properties.
   *
   * This function takes a target object and a source object. It iterates over
   * each property in the source object. If a property is an object (and not an array),
   * it recursively merges that property with the corresponding property in the target
   * object. If the property is not an object, it directly assigns the value from
   * the source to the result object.
   *
   * @param {Object} target - The target object to merge into.
   * @param {Object} source - The source object containing properties to merge.
   */
  deepMerge(target, source) {
    const result = { ...target };
    
    for (const key in source) {
      if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
        result[key] = this.deepMerge(result[key] || {}, source[key]);
      } else {
        result[key] = source[key];
      }
    }
    
    return result;
  }

  /**
   * Draws a Google Visualization chart of the specified type.
   *
   * This function initializes a chart based on the provided data, chart type,
   * element ID, and custom options. It handles various chart types including table,
   * line, pie, and gauge. The function also manages hiding a specific column if
   * requested and adds an event listener to log selections made in the chart.
   *
   * @param {Array<Array<any>>} data - The dataset for the chart.
   * @param {string} chartType - The type of chart to draw (e.g., 'table', 'line', 'pie', 'gauge').
   * @param {string} elementId - The ID of the HTML element where the chart will be rendered.
   * @param {Object} customOptions - Custom options for the chart.
   * @param {number} [hideColumn=-1] - Index of the column to hide in the chart (default is -1, indicating no column hidden).
   * @returns {Object|null} An object containing chart details or null if an error occurs.
   */
  drawChartByType(data, chartType, elementId, customOptions, hideColumn = -1) {
    if (!this.google.visualization) {
      console.error("Google Visualization API not loaded");
      return null;
    }

    if (!data || !elementId || !customOptions) {
      console.error("Invalid parameters passed to drawChartByType");
      return null;
    }

    const element = document.getElementById(elementId);
    if (!element) {
      console.error(`Element with id ${elementId} not found`);
      return null;
    }

    const result = {
      chartType,
      elementId,
    };

    switch (chartType) {
      case "table":
        result.chart = new this.google.visualization.Table(element);
        break;
      case "line":
        result.chart = new this.google.visualization.LineChart(element);
        break;
      case "pie":
        result.chart = new this.google.visualization.PieChart(element);
        break;
      case "gauge":
        result.chart = new this.google.visualization.Gauge(element);
        break;
      default:
        console.error(`Invalid chart type: ${chartType}`);
        return null;
    }

    result.dataTable = this.google.visualization.arrayToDataTable(data);

    const defaultOptions = {
      backgroundColor: 'transparent',
      titleTextStyle: { color: '#ffffff' },
      hAxis: { textStyle: { color: '#ffffff' } },
      vAxis: { textStyle: { color: '#ffffff' } },
      legend: { textStyle: { color: '#ffffff' } }
    };

    const options = this.deepMerge(defaultOptions, customOptions);

    if (hideColumn >= 0 && data.length > 0) {
      result.view = new this.google.visualization.DataView(result.dataTable);
      if (data[0].length > hideColumn) {
        result.view.hideColumns([hideColumn]);
      }

      result.chart.draw(result.view, options);

      this.google.visualization.events.addListener(result.chart, "select", () => {
        const selection = result.chart.getSelection();
        if (selection.length > 0) {
          const { row } = selection[0];
          const item = result.dataTable.getValue(row, 0);
          const hiddenInfo = result.dataTable.getValue(
            row,
            data[0].length > hideColumn ? hideColumn : 0
          );
          console.log(`You clicked on ${item}\nHidden Info: ${hiddenInfo}`);
        }
      });
    } else {
      result.chart.draw(result.dataTable, options);
    }

    return result;
  }

  /**
   * Draws a data table chart and updates a counter element with the data length minus one.
   */
  drawDataTable(data, elementId, options, hideColumn = -1) {
    const counterElementId = `counter_${elementId}`;
    const counterElement = document.getElementById(counterElementId);

    if (counterElement) {
      counterElement.innerHTML = Math.max(0, data.length - 1);
    }
    return this.drawChartByType(data, "table", elementId, options, hideColumn);
  }

  /**
   * Draws a line chart using the provided data and options.
   */
  drawLineChart(data, elementId, options) {
    return this.drawChartByType(data, "line", elementId, options);
  }

  /**
   * Draws a pie chart using the provided data and options.
   */
  drawPieChart(data, elementId, options) {
    return this.drawChartByType(data, "pie", elementId, options);
  }

  /**
   * Draws a gauge chart with the given label, value, element ID, and options.
   */
  drawGaugeChart(label, value, elementId, options) {
    return this.drawChartByType(
      [
        ["", ""],
        [label, value],
      ],
      "gauge",
      elementId,
      options
    );
  }
}
// charts.js
export class ChartManager {
  constructor() {
    this.google = window.google;
  }

  /**
   * Recursively merges two objects, deeply combining their properties.
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
   * Draws a data table chart in the specified HTML element.
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
   * Draws a gauge chart with the specified label, value, and options.
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
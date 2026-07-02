// charts.js
import { GridManager } from './gridManager.js';

export class ChartManager {
  constructor() {
    this.echarts = window.echarts;
    this.gridManager = new GridManager();
    this._echartsIds = new Set();
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

  #getOrInitChart(elementId) {
    const el = document.getElementById(elementId);
    if (!el) {
      console.error(`Element with id ${elementId} not found`);
      return null;
    }
    let instance = this.echarts.getInstanceByDom(el);
    if (!instance) {
      instance = this.echarts.init(el);
      this._echartsIds.add(elementId);
    }
    return instance;
  }

  /**
   * Converts Google-style {min,max,greenTo,yellowTo,redTo} zone thresholds
   * into ECharts axisLine.lineStyle.color stop-array format.
   */
  #zonesToColorStops(options) {
    const min = options.min ?? 0;
    const max = options.max ?? 100;
    const range = (max - min) || 1;
    const frac = (v) => Math.min(1, Math.max(0, (v - min) / range));
    return [
      [frac(options.greenTo ?? max), '#2ecc71'],
      [frac(options.yellowTo ?? max), '#f1c40f'],
      [frac(options.redTo ?? max), '#e74c3c'],
    ];
  }

  #baseText() {
    return {
      backgroundColor: 'transparent',
      textStyle: { color: '#ffffff' },
      title: { textStyle: { color: '#ffffff' } },
    };
  }

  #legendOff(options) {
    return options.legend === 'none' || options.legend?.position === 'none';
  }

  /**
   * Draws a chart of the specified type (table, line, pie, gauge) into elementId.
   *
   * @param {Array<Array<any>>} data - The dataset for the chart.
   * @param {string} chartType - The type of chart to draw (e.g., 'table', 'line', 'pie', 'gauge').
   * @param {string} elementId - The ID of the HTML element where the chart will be rendered.
   * @param {Object} customOptions - Custom options for the chart.
   * @param {number} [hideColumn=-1] - Index of the column to hide (table charts only).
   * @returns {Object|null} The underlying chart/grid instance, or null on error.
   */
  drawChartByType(data, chartType, elementId, customOptions, hideColumn = -1) {
    if (!data || !elementId || !customOptions) {
      console.error("Invalid parameters passed to drawChartByType");
      return null;
    }

    switch (chartType) {
      case "table":
        return this.gridManager.draw(data, elementId, customOptions, hideColumn);
      case "line":
        return this.#drawLine(data, elementId, customOptions);
      case "pie":
        return this.#drawPie(data, elementId, customOptions);
      case "gauge":
        return this.#drawGauge(data, elementId, customOptions);
      default:
        console.error(`Invalid chart type: ${chartType}`);
        return null;
    }
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

  /**
   * Resizes every ECharts instance this manager has ever initialized. Used
   * to fix charts drawn while their container was hidden (e.g. inside a
   * collapsed section), since ECharts measures pixel dimensions at draw time.
   */
  resizeAll() {
    for (const id of this._echartsIds) {
      const el = document.getElementById(id);
      if (el) {
        this.echarts.getInstanceByDom(el)?.resize();
      }
    }
  }

  #drawGauge(data, elementId, options) {
    const chart = this.#getOrInitChart(elementId);
    if (!chart) return null;

    const [, [label, value]] = data;
    const option = this.deepMerge(this.#baseText(), {
      series: [{
        type: 'gauge',
        min: options.min ?? 0,
        max: options.max ?? 100,
        startAngle: 200,
        endAngle: -20,
        progress: { show: false },
        axisLine: { lineStyle: { width: 14, color: this.#zonesToColorStops(options) } },
        axisTick: { show: false },
        splitLine: { length: 10, lineStyle: { color: '#ffffff', width: 2 } },
        axisLabel: { color: '#ffffff', fontSize: 10, distance: 18 },
        pointer: { itemStyle: { color: '#ffffff' } },
        title: { fontSize: 13, offsetCenter: [0, '70%'], color: '#ffffff' },
        detail: { fontSize: 18, offsetCenter: [0, '40%'], color: '#ffffff', formatter: '{value}' },
        data: [{ value, name: label }],
      }],
    });

    chart.setOption(option, true);
    chart.resize();
    return chart;
  }

  #drawLine(data, elementId, options) {
    const chart = this.#getOrInitChart(elementId);
    if (!chart) return null;

    const [header, ...rows] = data;
    const categories = rows.map((r) => r[0]);
    const seriesNames = header.slice(1);
    const legendOff = this.#legendOff(options);
    const legendRight = options.legend?.position === 'right';

    const series = seriesNames.map((name, i) => ({
      name,
      type: 'line',
      symbolSize: options.pointSize ?? 4,
      data: rows.map((r) => r[i + 1]),
      ...(options.series?.[i]?.color ? {
        itemStyle: { color: options.series[i].color },
        lineStyle: { color: options.series[i].color },
      } : {}),
    }));

    const option = this.deepMerge(this.#baseText(), {
      title: options.title ? { text: options.title, textStyle: { fontSize: 14 } } : undefined,
      tooltip: { trigger: 'axis' },
      legend: legendOff
        ? { show: false }
        : { show: true, textStyle: { color: '#fff' }, ...(legendRight ? { orient: 'vertical', right: 0, top: 'middle' } : {}) },
      grid: { left: 45, right: legendRight ? 110 : 20, bottom: 55, top: options.title ? 45 : 25, containLabel: true },
      xAxis: {
        type: 'category',
        data: categories,
        name: options.hAxis?.title,
        nameLocation: 'middle',
        nameGap: 30,
        axisLabel: { color: '#fff', fontSize: options.hAxis?.textStyle?.fontSize ?? 11, rotate: 30 },
        axisLine: { lineStyle: { color: '#fff' } },
      },
      yAxis: {
        type: 'value',
        axisLabel: { color: '#fff' },
        splitLine: { lineStyle: { color: 'rgba(255,255,255,.15)' } },
      },
      series,
    });

    chart.setOption(option, true);
    chart.resize();
    return chart;
  }

  #drawPie(data, elementId, options) {
    const chart = this.#getOrInitChart(elementId);
    if (!chart) return null;

    const [, ...rows] = data;
    const seriesData = rows.map((r) => ({ name: r[0], value: r[1] }));
    const legendOff = this.#legendOff(options);
    const legendRight = options.legend?.position === 'right';

    const option = this.deepMerge(this.#baseText(), {
      title: options.title ? { text: options.title, left: 'center', textStyle: { fontSize: 14 } } : undefined,
      tooltip: { trigger: 'item' },
      legend: legendOff
        ? { show: false }
        : {
          show: true,
          textStyle: { color: '#fff' },
          orient: legendRight ? 'vertical' : 'horizontal',
          ...(legendRight ? { right: 0, top: 'middle' } : { bottom: 0 }),
        },
      series: [{
        type: 'pie',
        radius: legendRight ? ['0%', '65%'] : ['0%', '70%'],
        center: legendRight ? ['40%', '50%'] : ['50%', '50%'],
        data: seriesData,
        label: { color: '#fff' },
        labelLine: { lineStyle: { color: '#fff' } },
      }],
    });

    chart.setOption(option, true);
    chart.resize();
    return chart;
  }
}

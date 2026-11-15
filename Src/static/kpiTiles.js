// kpiTiles.js
//
// KPI tiles in place of the old gauges: the value, coloured only by its
// status against the gauge's thresholds (amber from yellowFrom, red from
// redFrom), the thresholds themselves, and a 24h sparkline with the change
// since the oldest sample. Samples are kept in this browser's localStorage,
// so the trend covers the time this browser had the dashboard open.

const HISTORY_KEY = "kpiHistory";
const HISTORY_WINDOW_MS = 24 * 60 * 60 * 1000;
const MIN_SAMPLE_GAP_MS = 60 * 1000;
const MAX_SAMPLES = 288;

const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");

/**
 * The status of a value against gauge-style thresholds.
 *
 * @param {number} value - The value.
 * @param {object} options - yellowFrom and redFrom (either may be missing).
 * @returns {"ok"|"warning"|"critical"}
 */
export function kpiStatus(value, options) {
  if (options.redFrom !== undefined && value >= options.redFrom) {
    return "critical";
  }

  if (options.yellowFrom !== undefined && value >= options.yellowFrom) {
    return "warning";
  }

  return "ok";
}

/**
 * An SVG polyline of the samples, scaled to a 100x24 box.
 *
 * @param {Array<{t:number,v:number}>} samples - The samples, oldest first.
 * @returns {string}
 */
export function sparklineSvg(samples) {
  if (samples.length < 2) {
    return "";
  }

  const values = samples.map((sample) => sample.v);
  const min = Math.min(...values);
  const range = Math.max(...values) - min || 1;
  const first = samples[0].t;
  const span = samples[samples.length - 1].t - first || 1;
  const points = samples
    .map((sample) => {
      const x = ((sample.t - first) / span) * 100;
      const y = 22 - ((sample.v - min) / range) * 20;
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    })
    .join(" ");

  return `<svg class="kpi-sparkline" viewBox="0 0 100 24" preserveAspectRatio="none" aria-hidden="true"><polyline points="${points}" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke"/></svg>`;
}

export class KpiTileRenderer {
  constructor() {
    this.history = this.loadHistory();
  }

  loadHistory() {
    try {
      return JSON.parse(localStorage.getItem(HISTORY_KEY) || "{}") || {};
    } catch {
      return {};
    }
  }

  saveHistory() {
    try {
      localStorage.setItem(HISTORY_KEY, JSON.stringify(this.history));
    } catch {
      // Storage full or unavailable: the tiles still render, without a trend.
    }
  }

  /**
   * Records a sample (at most one a minute) and returns the last 24h.
   *
   * @param {string} key - The tile's element id.
   * @param {number} value - The value.
   * @param {number} now - The current time, in milliseconds.
   * @returns {Array<{t:number,v:number}>}
   */
  record(key, value, now) {
    const samples = (this.history[key] || []).filter(
      (sample) => now - sample.t <= HISTORY_WINDOW_MS,
    );
    const last = samples[samples.length - 1];
    if (!last || now - last.t >= MIN_SAMPLE_GAP_MS) {
      samples.push({ t: now, v: value });
    } else {
      last.v = value;
    }

    this.history[key] = samples.slice(-MAX_SAMPLES);
    this.saveHistory();
    return this.history[key];
  }

  /**
   * Renders a tile into its element.
   *
   * @param {string} elementId - The tile's element id.
   * @param {string} label - What the number is.
   * @param {number|string} rawValue - The value.
   * @param {object} options - The old gauge options (min, max, yellowFrom, redFrom).
   * @returns {HTMLElement|null}
   */
  render(elementId, label, rawValue, options) {
    const element = document.getElementById(elementId);
    if (!element) {
      return null;
    }

    const value = Number(rawValue) || 0;
    const status = kpiStatus(value, options);
    const samples = this.record(elementId, value, Date.now());
    const delta = samples.length > 1 ? value - samples[0].v : 0;
    let trend = "";
    if (delta !== 0) {
      const arrow = delta > 0 ? "↑" : "↓";
      trend = `<span class="kpi-delta" title="Change over the samples kept in this browser (last 24h)">${arrow} ${Math.abs(Math.round(delta * 10) / 10)}</span>`;
    }

    const thresholds = [];
    if (options.yellowFrom !== undefined)
      thresholds.push(`warn ≥ ${options.yellowFrom}`);
    if (options.redFrom !== undefined)
      thresholds.push(`crit ≥ ${options.redFrom}`);

    element.className = `kpi-tile status-${status}`;
    element.innerHTML = `
      <span class="kpi-label"><span class="status-dot status-${status}" aria-hidden="true"></span>${escapeHtml(label)}</span>
      <span class="kpi-value">${escapeHtml(Math.round(value * 10) / 10)}${trend}</span>
      ${sparklineSvg(samples)}
      <span class="kpi-thresholds">${escapeHtml(thresholds.join(" · "))}</span>`;
    return element;
  }
}

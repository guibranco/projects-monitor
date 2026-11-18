// kpiTiles.js
//
// KPI tiles in place of the old gauges: the value, coloured only by its
// status against the gauge's thresholds (amber from yellowFrom, red from
// redFrom), the thresholds themselves, and a 24h sparkline with the change
// since the oldest sample.
//
// The history lives on the server (api/v1/kpi-history): every open
// dashboard sends the values it shows (batched, at most once a minute) and
// reads the stored samples once per page load, so the trend is the same in
// every browser. This browser's localStorage keeps a copy, which is what the
// sparkline falls back to when the server cannot be reached.

import { API_ENDPOINTS } from "./constants.js";

const HISTORY_KEY = "kpiHistory";
const HISTORY_WINDOW_MS = 24 * 60 * 60 * 1000;
const MIN_SAMPLE_GAP_MS = 60 * 1000;
const MAX_SAMPLES = 1440;
const HISTORY_FETCH_DELAY_MS = 500;
const UPLOAD_INTERVAL_MS = 60 * 1000;

/**
 * Merges the server's samples with this browser's, oldest first, one per
 * minute (a server sample wins over a local one of the same minute), within
 * the 24h window.
 *
 * @param {Array<{t:number,v:number}>} server - The server's samples.
 * @param {Array<{t:number,v:number}>} local - This browser's samples.
 * @param {number} now - The current time, in milliseconds.
 * @returns {Array<{t:number,v:number}>}
 */
export function mergeSamples(server, local, now) {
  const byMinute = new Map();
  for (const sample of [...(local || []), ...(server || [])]) {
    if (
      typeof sample?.t === "number" &&
      typeof sample?.v === "number" &&
      now - sample.t <= HISTORY_WINDOW_MS
    ) {
      byMinute.set(Math.floor(sample.t / MIN_SAMPLE_GAP_MS), sample);
    }
  }

  return [...byMinute.values()]
    .sort((left, right) => left.t - right.t)
    .slice(-MAX_SAMPLES);
}

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
    this.history = new Map();
    this.lastRender = new Map(); // elementId -> [label, value, options]
    this.requested = new Set(); // metrics whose server history was asked for
    this.toRequest = new Set();
    this.pending = new Map(); // metric -> value waiting to be uploaded
    this.lastUploadAt = 0;
    this.uploadTimer = null;
    this.fetchTimer = null;
    this.loadHistory();
  }

  /**
   * Asks the server for a tile's history, batching the tiles of one page
   * load into one request; the tile is redrawn with it.
   *
   * @param {string} key - The tile's element id.
   */
  requestServerHistory(key) {
    if (this.requested.has(key)) return;
    this.requested.add(key);
    this.toRequest.add(key);
    if (this.fetchTimer) return;
    this.fetchTimer = setTimeout(() => {
      this.fetchTimer = null;
      const metrics = [...this.toRequest];
      this.toRequest.clear();
      fetch(
        `${API_ENDPOINTS.KPI_HISTORY}?metrics=${encodeURIComponent(metrics.join(","))}`,
      )
        .then((response) => (response.ok ? response.json() : {}))
        .then((series) => {
          const now = Date.now();
          for (const [metric, samples] of Object.entries(series || {})) {
            if (!Array.isArray(samples) || samples.length === 0) continue;
            this.history.set(
              metric,
              mergeSamples(samples, this.history.get(metric), now),
            );
            const args = this.lastRender.get(metric);
            if (args) this.draw(metric, ...args);
          }
          this.saveHistory();
        })
        .catch(() => undefined);
    }, HISTORY_FETCH_DELAY_MS);
  }

  /**
   * Queues a value for the server; the queue is sent at most once a minute.
   *
   * @param {string} key - The tile's element id.
   * @param {number} value - The value.
   */
  queueUpload(key, value) {
    this.pending.set(key, value);
    if (this.uploadTimer) return;
    const wait = Math.max(
      0,
      this.lastUploadAt + UPLOAD_INTERVAL_MS - Date.now(),
    );
    this.uploadTimer = setTimeout(() => this.upload(), wait);
  }

  upload() {
    this.uploadTimer = null;
    if (this.pending.size === 0) return;
    const samples = Object.fromEntries(this.pending);
    this.pending.clear();
    this.lastUploadAt = Date.now();
    fetch(API_ENDPOINTS.KPI_HISTORY, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ samples }),
    }).catch(() => undefined);
  }

  /**
   * Reads the kept samples. Asynchronous so that unreadable or corrupt
   * storage only means starting without a trend.
   */
  loadHistory() {
    // eslint-disable-next-line compat/compat
    Promise.resolve()
      .then(() => JSON.parse(window.localStorage.getItem(HISTORY_KEY) || "{}"))
      .then((stored) => {
        for (const [key, samples] of Object.entries(stored || {})) {
          if (Array.isArray(samples) && !this.history.has(key)) {
            this.history.set(key, samples);
          }
        }
      })
      .catch(() => this.history.clear());
  }

  /**
   * Keeps the samples. A full or unavailable storage only loses the trend.
   */
  saveHistory() {
    // eslint-disable-next-line compat/compat
    Promise.resolve()
      .then(() =>
        window.localStorage.setItem(
          HISTORY_KEY,
          JSON.stringify(Object.fromEntries(this.history)),
        ),
      )
      .catch(() => undefined);
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
    const samples = (this.history.get(key) || []).filter(
      (sample) => now - sample.t <= HISTORY_WINDOW_MS,
    );
    const last = samples[samples.length - 1];
    if (!last || now - last.t >= MIN_SAMPLE_GAP_MS) {
      samples.push({ t: now, v: value });
    } else {
      last.v = value;
    }

    const kept = samples.slice(-MAX_SAMPLES);
    this.history.set(key, kept);
    this.saveHistory();
    return kept;
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
    this.record(elementId, value, Date.now());
    this.lastRender.set(elementId, [label, value, options]);
    this.queueUpload(elementId, value);
    this.requestServerHistory(elementId);
    return this.draw(elementId, label, value, options);
  }

  /**
   * Draws a tile from its current value and the kept history.
   *
   * @param {string} elementId - The tile's element id.
   * @param {string} label - What the number is.
   * @param {number} value - The value.
   * @param {object} options - The thresholds.
   * @returns {HTMLElement|null}
   */
  draw(elementId, label, value, options) {
    const element = document.getElementById(elementId);
    if (!element) {
      return null;
    }

    const status = kpiStatus(value, options);
    const samples = this.history.get(elementId) || [];
    const delta = samples.length > 1 ? value - samples[0].v : 0;
    let trend = "";
    if (delta !== 0) {
      const arrow = delta > 0 ? "↑" : "↓";
      trend = `<span class="kpi-delta" title="Change over the last 24h">${arrow} ${Math.abs(Math.round(delta * 10) / 10)}</span>`;
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

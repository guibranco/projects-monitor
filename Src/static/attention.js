// attention.js
//
// The "manage by exception" block at the top of the dashboard: the status
// strip (one tile per domain, coloured by its worst active item), the
// "Needs attention" list (active items, critical first then oldest first,
// acknowledged ones dimmed at the end), the collapsed "all clear" line and
// the data freshness indicator in the navbar. Everything comes from
// GET api/v1/attention; the judgement of what is wrong lives in the backend.
import { API_ENDPOINTS } from './constants.js';
import { NotificationManager } from './ui.js';

const SNOOZE_OPTIONS = [
  { label: '1 hour', minutes: 60 },
  { label: '8 hours', minutes: 8 * 60 },
  { label: '1 day', minutes: 24 * 60 },
  { label: '1 week', minutes: 7 * 24 * 60 },
];

const DOMAIN_ICONS = {
  ops: 'bi-hdd-rack',
  pipeline: 'bi-diagram-3',
  errors: 'bi-bug',
  ci: 'bi-gear',
  github: 'bi-github',
  domains: 'bi-globe',
};

const escapeHtml = (value) =>
  String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

/**
 * "40s", "5m", "3h", "2d": how long ago a time was, for compact rows.
 *
 * @param {number} seconds - Elapsed seconds.
 * @returns {string}
 */
export function formatDuration(seconds) {
  const value = Math.max(0, Math.floor(seconds));
  if (value < 60) return `${value}s`;
  if (value < 3600) return `${Math.floor(value / 60)}m`;
  if (value < 86400) return `${Math.floor(value / 3600)}h`;
  return `${Math.floor(value / 86400)}d`;
}

export class AttentionPanel {
  /**
   * @param {import('./api.js').ApiManager} apiManager - Shared API manager (session handling).
   */
  constructor(apiManager) {
    this.apiManager = apiManager;
    this.data = null;
    this.domainFilter = null;
    this.loadedAt = 0;
  }

  /**
   * Wires the click handlers (once) and starts the refresh and clock timers.
   */
  init() {
    const panel = document.getElementById('attention_panel');
    if (!panel) return;

    panel.addEventListener('click', (event) => this.onClick(event));
    document.getElementById('data_freshness')?.addEventListener('click', () => this.load(true));
    this.load(false);
    setInterval(() => this.load(false), 30 * 1000);
    setInterval(() => this.renderFreshness(), 10 * 1000);
  }

  /**
   * Fetches the attention feed.
   *
   * @param {boolean} refresh - Ask the backend to re-evaluate every source now.
   */
  load(refresh) {
    const url = refresh ? `${API_ENDPOINTS.ATTENTION}?refresh=1` : API_ENDPOINTS.ATTENTION;
    this.apiManager.load(url, (data) => {
      this.data = data;
      this.loadedAt = Date.now();
      this.render();
    });
  }

  render() {
    if (!this.data) return;
    this.renderStrip();
    this.renderList();
    this.renderFreshness();
  }

  renderStrip() {
    const strip = document.getElementById('attention_strip');
    if (!strip) return;

    strip.innerHTML = (this.data.domains || []).map((tile) => {
      const active = this.domainFilter === tile.domain ? ' is-filtered' : '';
      const count = tile.count > 0 ? tile.count : '';
      const status = tile.count > 0 ? `${tile.count} need${tile.count === 1 ? 's' : ''} attention` : 'All clear';
      return `
        <button type="button" class="status-tile status-${escapeHtml(tile.severity)}${active}"
          data-action="filter-domain" data-domain="${escapeHtml(tile.domain)}"
          title="${escapeHtml(tile.label)}: ${status}" aria-pressed="${this.domainFilter === tile.domain}">
          <span class="status-tile-label">
            <i class="bi ${DOMAIN_ICONS[tile.domain] || 'bi-circle'} me-1"></i>${escapeHtml(tile.label)}
          </span>
          <span class="status-tile-count">${count}</span>
          <span class="status-dot" aria-hidden="true"></span>
        </button>`;
    }).join('');
  }

  renderList() {
    const list = document.getElementById('attention_list');
    const clear = document.getElementById('attention_all_clear');
    const counter = document.getElementById('counter_attention');
    if (!list || !clear) return;

    const items = (this.data.items || []).filter(
      (item) => this.domainFilter === null || item.domain === this.domainFilter
    );
    const summary = this.data.summary || {};

    if (counter) {
      counter.textContent = summary.active || '';
      counter.className = `badge rounded-pill attention-counter status-${summary.critical > 0 ? 'critical' : (summary.active > 0 ? 'warning' : 'ok')}`;
    }

    list.innerHTML = items.length === 0
      ? `<div class="attention-empty"><i class="bi bi-check2-circle me-2"></i>${this.domainFilter ? 'Nothing needs attention here.' : 'Nothing needs attention right now.'}</div>`
      : items.map((item) => this.renderItem(item)).join('');

    const parts = [`${summary.healthy ?? 0} other checks healthy`];
    if (summary.snoozed) parts.push(`${summary.snoozed} snoozed`);
    if (summary.acknowledged) parts.push(`${summary.acknowledged} acknowledged`);
    const failing = (this.data.sources || []).filter((source) => source.status === 'error').length;
    if (failing) parts.push(`${failing} source${failing === 1 ? '' : 's'} unreachable`);
    const filter = this.domainFilter
      ? ` · <button type="button" class="btn btn-link btn-sm p-0 attention-link" data-action="filter-domain" data-domain="${escapeHtml(this.domainFilter)}">show all domains</button>`
      : '';
    clear.innerHTML = `<span class="status-dot status-ok" aria-hidden="true"></span>${parts.join(' · ')}${filter}`;
  }

  renderItem(item) {
    const since = item.since ? new Date(item.since) : null;
    const age = since && !Number.isNaN(since.getTime())
      ? `<span class="attention-age" title="${escapeHtml(since.toLocaleString())}">${formatDuration((Date.now() - since.getTime()) / 1000)}</span>`
      : '<span class="attention-age text-white-50" title="The source does not report since when">—</span>';
    const acknowledged = item.state === 'acknowledged';
    const fingerprint = escapeHtml(item.fingerprint);
    const actions = item.actions || [];

    const rerun = actions.includes('rerun') && item.meta?.workflowRunId
      ? `<button type="button" class="btn btn-sm btn-attention" data-action="rerun" data-run="${escapeHtml(item.meta.workflowRunId)}" title="Re-run workflow"><i class="bi bi-arrow-repeat"></i><span class="d-none d-xl-inline ms-1">Re-run</span></button>`
      : '';
    const acknowledge = acknowledged
      ? `<button type="button" class="btn btn-sm btn-attention" data-action="clear" data-fingerprint="${fingerprint}" title="Undo acknowledge"><i class="bi bi-arrow-counterclockwise"></i></button>`
      : `<button type="button" class="btn btn-sm btn-attention" data-action="acknowledge" data-fingerprint="${fingerprint}" data-since="${escapeHtml(item.since ?? '')}" title="Acknowledge: keep it listed but stop counting it until it changes"><i class="bi bi-check2"></i></button>`;
    const snooze = `
      <div class="btn-group">
        <button type="button" class="btn btn-sm btn-attention dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Snooze">
          <i class="bi bi-bell-slash"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
          ${SNOOZE_OPTIONS.map((option) => `<li><button type="button" class="dropdown-item" data-action="snooze" data-fingerprint="${fingerprint}" data-minutes="${option.minutes}">${option.label}</button></li>`).join('')}
        </ul>
      </div>`;

    return `
      <div class="attention-item severity-${escapeHtml(item.severity)}${acknowledged ? ' is-acknowledged' : ''}">
        <span class="severity-badge severity-${escapeHtml(item.severity)}">${escapeHtml(item.severity)}</span>
        <span class="attention-title">
          ${escapeHtml(item.title)}
          <span class="attention-source">${escapeHtml(item.source)}${acknowledged ? ' · acknowledged' : ''}</span>
        </span>
        ${age}
        <span class="attention-actions">
          <a class="btn btn-sm btn-attention" href="${escapeHtml(item.link)}" title="Open"><i class="bi bi-box-arrow-up-right"></i><span class="d-none d-xl-inline ms-1">Open</span></a>
          ${rerun}
          ${snooze}
          ${acknowledge}
        </span>
      </div>`;
  }

  /**
   * "Data 40s old · cache OK", amber when the cache script (the public stats
   * worker) stopped writing its file.
   */
  renderFreshness() {
    const element = document.getElementById('data_freshness');
    if (!element || !this.data) return;

    const freshness = this.data.freshness || {};
    const elapsed = (Date.now() - this.loadedAt) / 1000;
    const age = formatDuration((freshness.ageSeconds ?? 0) + elapsed);
    const stale = freshness.cacheStale === true;
    const cache = freshness.cacheAgeSeconds === null || freshness.cacheAgeSeconds === undefined
      ? 'cache missing'
      : (stale ? `cache ${formatDuration(freshness.cacheAgeSeconds + elapsed)} old` : 'cache OK');

    element.className = `freshness-indicator d-none d-md-inline-flex ${stale ? 'is-stale' : ''}`;
    element.title = `Attention data generated ${this.data.generatedAt ?? 'never'}; the cache script last wrote ${freshness.cacheUpdatedAt ?? 'never'}. Click to re-check every source now.`;
    element.innerHTML = `<span class="status-dot ${stale ? 'status-warning' : 'status-ok'}" aria-hidden="true"></span>Data ${age} old · ${cache}`;
  }

  onClick(event) {
    const target = event.target.closest('[data-action]');
    if (!target) return;

    const { action, fingerprint } = target.dataset;
    switch (action) {
      case 'filter-domain':
        this.domainFilter = this.domainFilter === target.dataset.domain ? null : target.dataset.domain;
        this.render();
        break;
      case 'snooze':
        this.saveState({ fingerprint, action, minutes: Number(target.dataset.minutes) }, 'Snoozed');
        break;
      case 'acknowledge':
        this.saveState({ fingerprint, action, since: target.dataset.since || null }, 'Acknowledged');
        break;
      case 'clear':
        this.saveState({ fingerprint, action }, 'Restored');
        break;
      case 'rerun':
        window.confirmRetryWorkflowRun?.(target.dataset.run, () => window.retryWorkflowRun?.(Number(target.dataset.run)));
        break;
      default:
        break;
    }
  }

  async saveState(body, verb) {
    try {
      const response = await fetch(API_ENDPOINTS.ATTENTION_STATE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(data?.error || `HTTP ${response.status}`);
      }

      NotificationManager.show('Success', `${verb}.`, 'success');
      this.load(false);
    } catch (error) {
      NotificationManager.show('Error', `Failed to update the item: ${error.message}`, 'error');
    }
  }
}

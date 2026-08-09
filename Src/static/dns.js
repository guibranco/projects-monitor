// dns.js — dedicated DNS Records page (filterable, sortable Grid.js table)
import { API_ENDPOINTS } from './constants.js';
import { ApiManager } from './api.js';
import { NotificationManager } from './ui.js';

const TYPE_BADGE_CLASS = {
  A: 'dns-badge-a',
  AAAA: 'dns-badge-a',
  CNAME: 'dns-badge-cname',
  MX: 'dns-badge-mx',
  TXT: 'dns-badge-txt',
  NS: 'dns-badge-ns',
  SOA: 'dns-badge-soa',
  PTR: 'dns-badge-ptr',
  SRV: 'dns-badge-srv',
  CAA: 'dns-badge-caa',
};

class DnsRecordsPage {
  constructor() {
    this.apiManager = new ApiManager();
    this.gridjs = window.gridjs;
    this.records = [];
    this.activeDomains = new Set();
    this.activeTypes = new Set();
    this.grid = null;
  }

  start() {
    this.apiManager.load(API_ENDPOINTS.DNS_RECORDS, (data) => this.onData(data));
  }

  onData(data) {
    if (data && data.error) {
      NotificationManager.show('Error', data.error, 'error');
    }

    this.records = Array.isArray(data && data.records) ? data.records : [];

    // Preserve the user's current filter selection across reloads; only
    // default to "everything selected" the very first time data arrives.
    const isFirstLoad = this.activeDomains.size === 0 && this.activeTypes.size === 0;
    if (isFirstLoad) {
      this.distinctValues('domain').forEach((v) => this.activeDomains.add(v));
      this.distinctValues('type').forEach((v) => this.activeTypes.add(v));
    }

    this.renderFilters();
    this.renderTable();
  }

  distinctValues(field) {
    return [...new Set(this.records.map((r) => r[field]).filter((v) => v !== undefined && v !== null && v !== ''))].sort();
  }

  filteredRecords() {
    return this.records.filter(
      (r) => this.activeDomains.has(r.domain) && this.activeTypes.has(r.type)
    );
  }

  renderFilters() {
    this.renderFilterGroup('dns_filter_domains', 'domain', this.activeDomains);
    this.renderFilterGroup('dns_filter_types', 'type', this.activeTypes);

    document.getElementById('dns_domains_all')?.addEventListener('click', () => this.setAll('domain', this.activeDomains, true));
    document.getElementById('dns_domains_none')?.addEventListener('click', () => this.setAll('domain', this.activeDomains, false));
    document.getElementById('dns_types_all')?.addEventListener('click', () => this.setAll('type', this.activeTypes, true));
    document.getElementById('dns_types_none')?.addEventListener('click', () => this.setAll('type', this.activeTypes, false));
  }

  renderFilterGroup(containerId, field, activeSet) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const values = this.distinctValues(field);

    if (values.length === 0) {
      container.innerHTML = '<div class="text-muted small px-2">No records found.</div>';
      return;
    }

    container.innerHTML = values
      .map((value, i) => {
        const id = `${containerId}_${i}`;
        const checked = activeSet.has(value) ? 'checked' : '';
        return `
          <div class="form-check dns-filter-item">
            <input class="form-check-input" type="checkbox" id="${id}" value="${value}" ${checked}>
            <label class="form-check-label" for="${id}">${value}</label>
          </div>`;
      })
      .join('');

    container.querySelectorAll('input[type="checkbox"]').forEach((input) => {
      input.addEventListener('change', () => {
        if (input.checked) {
          activeSet.add(input.value);
        } else {
          activeSet.delete(input.value);
        }
        this.renderTable();
      });
    });
  }

  setAll(field, activeSet, selected) {
    activeSet.clear();
    if (selected) {
      this.distinctValues(field).forEach((v) => activeSet.add(v));
    }
    this.renderFilters();
    this.renderTable();
  }

  typeBadge(type) {
    const cls = TYPE_BADGE_CLASS[type] || 'dns-badge-default';
    return this.gridjs.html(`<span class="dns-type-badge ${cls}">${type}</span>`);
  }

  renderTable() {
    const el = document.getElementById('dns_records_table');
    if (!el) return;

    const filtered = this.filteredRecords();
    const counter = document.getElementById('counter_dns_records');
    if (counter) {
      counter.textContent = `${filtered.length} / ${this.records.length}`;
    }

    const data = filtered.map((r) => [r.domain, r.name, r.type, r.destination, r.ttl]);

    const columns = [
      { name: 'Source Domain', sort: true },
      { name: 'Name', sort: true },
      {
        name: 'Type',
        sort: true,
        formatter: (cell) => this.typeBadge(cell),
      },
      { name: 'Destination', sort: true },
      { name: 'TTL', sort: true },
    ];

    if (this.grid) {
      this.grid.updateConfig({ columns, data }).forceRender();
      return;
    }

    this.grid = new this.gridjs.Grid({
      columns,
      data,
      sort: true,
      pagination: { limit: 25, summary: true },
      search: true,
      resizable: false,
      className: { table: 'gridjs-table-dark' },
    });
    this.grid.render(el);
  }
}

const page = new DnsRecordsPage();
window.addEventListener('load', () => page.start());

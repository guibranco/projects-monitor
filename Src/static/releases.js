// releases.js — dedicated Releases page (full table of every tracked repository's latest release)
import { API_ENDPOINTS, CHART_OPTIONS } from './constants.js';
import { ApiManager } from './api.js';
import { ChartManager } from './charts.js';

class ReleasesPage {
  constructor() {
    this.apiManager = new ApiManager();
    this.chartManager = new ChartManager();
  }

  start() {
    this.apiManager.load(API_ENDPOINTS.RELEASES, (data) => this.onData(data));
  }

  onData(data) {
    const rows = Array.isArray(data && data.releases) ? data.releases : [];
    this.chartManager.drawDataTable(rows, "releases_table", CHART_OPTIONS.table);
  }
}

const page = new ReleasesPage();
window.addEventListener('load', () => page.start());

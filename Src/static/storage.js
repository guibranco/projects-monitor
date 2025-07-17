// storage.js
import { STORAGE_KEYS, VALID_STATES, FEED_FILTERS } from './constants.js';

export class OptionsBoxState {
  static save(state) {
    if (!Object.values(VALID_STATES).includes(state)) {
      console.error(
        `Invalid state: ${state}. Must be one of ${Object.values(VALID_STATES)}`
      );
      return;
    }
    try {
      localStorage.setItem(STORAGE_KEYS.OPTIONS_BOX_STATE, state);
    } catch (e) {
      console.error("Failed to save options box state:", e);
    }
  }

  static load() {
    try {
      return localStorage.getItem(STORAGE_KEYS.OPTIONS_BOX_STATE) || VALID_STATES.OPEN;
    } catch (e) {
      console.error("Failed to load options box state:", e);
      return VALID_STATES.OPEN;
    }
  }

  static handle() {
    const optionsBoxState = this.load();
    const optionsBox = document.getElementById("userMenu");

    if (!optionsBox) {
      console.error("Options box element not found");
      return;
    }

    if (optionsBoxState === VALID_STATES.COLLAPSED) {
      optionsBox.classList.remove("show");
    } else {
      optionsBox.classList.add("show");
    }

    optionsBox.addEventListener("shown.bs.collapse", () =>
      this.save(VALID_STATES.OPEN)
    );
    optionsBox.addEventListener("hidden.bs.collapse", () =>
      this.save(VALID_STATES.COLLAPSED)
    );
  }
}

export class FeedState {
  constructor() {
    this._filter = this.loadFeedFilter();
  }

  get filter() {
    return this._filter;
  }

  set filter(value) {
    if (!Object.values(FEED_FILTERS).includes(value)) {
      throw new Error(`Invalid filter: ${value}`);
    }
    this._filter = value;
    this.saveFeedFilter(value);
  }

  loadFeedFilter() {
    try {
      const storedFilter = localStorage.getItem(STORAGE_KEYS.FEED_FILTER);
      return Object.values(FEED_FILTERS).includes(storedFilter)
        ? storedFilter
        : FEED_FILTERS.ALL;
    } catch (e) {
      console.error("Failed to load feed filter:", e);
      return FEED_FILTERS.ALL;
    }
  }

  saveFeedFilter(value) {
    try {
      localStorage.setItem(STORAGE_KEYS.FEED_FILTER, value);
    } catch (e) {
      console.error("Failed to save feed filter:", e);
    }
  }
}

export class WorkflowLimiterState {
  constructor() {
    this._enabled = this.loadLimiterState();
    this._limitValue = this.loadLimiterValue();
  }

  get enabled() {
    return this._enabled;
  }

  set enabled(value) {
    this._enabled = Boolean(value);
    this.saveLimiterState(this._enabled);
  }

  get limitValue() {
    return this._limitValue;
  }

  set limitValue(value) {
    const limit = parseInt(value, 10);
    if (Number.isNaN(limit) || limit < 1) {
      throw new Error(
        "Invalid workflow limit value. Must be a number greater than 0."
      );
    }
    this._limitValue = limit;
    this.saveLimiterValue(limit);
  }

  loadLimiterState() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_KEYS.WORKFLOW_LIMITER)) || false;
    } catch (e) {
      console.error("Failed to load workflow limiter state:", e);
      return false;
    }
  }

  saveLimiterState(state) {
    try {
      localStorage.setItem(STORAGE_KEYS.WORKFLOW_LIMITER, JSON.stringify(state));
    } catch (e) {
      console.error("Failed to save workflow limiter state:", e);
    }
  }

  loadLimiterValue() {
    try {
      return parseInt(localStorage.getItem(STORAGE_KEYS.WORKFLOW_LIMIT_VALUE), 10) || 10;
    } catch (e) {
      console.error("Failed to load workflow limit value:", e);
      return 10;
    }
  }

  saveLimiterValue(value) {
    try {
      localStorage.setItem(STORAGE_KEYS.WORKFLOW_LIMIT_VALUE, value);
    } catch (e) {
      console.error("Failed to save workflow limit value:", e);
    }
  }
}
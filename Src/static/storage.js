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

// CollapsibleSectionsState class
export class CollapsibleSectionsState {
  constructor() {
    this._collapsedSections = this.loadCollapsedSections();
  }

  /**
   * Get the collapsed state of all sections
   * @returns {Object} Object with section IDs as keys and boolean collapsed state as values
   */
  get collapsedSections() {
    return { ...this._collapsedSections };
  }

  /**
   * Check if a specific section is collapsed
   * @param {string} sectionId - The ID of the section to check
   * @returns {boolean} True if collapsed, false if expanded
   */
  isSectionCollapsed(sectionId) {
    if (!sectionId) {
      console.warn("CollapsibleSectionsState: No section ID provided to isSectionCollapsed");
      return false;
    }
    return Boolean(this._collapsedSections[sectionId]);
  }

  /**
   * Set the collapsed state of a specific section
   * @param {string} sectionId - The ID of the section
   * @param {boolean} isCollapsed - Whether the section should be collapsed
   */
  setSectionCollapsed(sectionId, isCollapsed) {
    if (!sectionId) {
      console.warn("CollapsibleSectionsState: No section ID provided to setSectionCollapsed");
      return;
    }

    const collapsed = Boolean(isCollapsed);

    if (collapsed) {
      this._collapsedSections[sectionId] = true;
    } else {
      // Remove from object when expanded (saves storage space)
      delete this._collapsedSections[sectionId];
    }

    this.saveCollapsedSections();
  }

  /**
   * Toggle the collapsed state of a specific section
   * @param {string} sectionId - The ID of the section to toggle
   * @returns {boolean} The new collapsed state
   */
  toggleSection(sectionId) {
    if (!sectionId) {
      console.warn("CollapsibleSectionsState: No section ID provided to toggleSection");
      return false;
    }

    const wasCollapsed = this.isSectionCollapsed(sectionId);
    const newState = !wasCollapsed;
    this.setSectionCollapsed(sectionId, newState);
    return newState;
  }

  /**
   * Collapse all sections
   * @param {string[]} sectionIds - Array of section IDs to collapse
   */
  collapseAll(sectionIds = []) {
    if (!Array.isArray(sectionIds) || sectionIds.length === 0) {
      console.warn("CollapsibleSectionsState: No section IDs provided to collapseAll");
      return;
    }

    sectionIds.forEach(sectionId => {
      this._collapsedSections[sectionId] = true;
    });

    this.saveCollapsedSections();
    console.log(`CollapsibleSectionsState: Collapsed ${sectionIds.length} sections`);
  }

  /**
   * Expand all sections
   */
  expandAll() {
    const previousCount = Object.keys(this._collapsedSections).length;
    this._collapsedSections = {};
    this.saveCollapsedSections();
    console.log(`CollapsibleSectionsState: Expanded ${previousCount} sections`);
  }

  /**
   * Get statistics about collapsed/expanded sections
   * @param {string[]} allSectionIds - Array of all available section IDs
   * @returns {Object} Statistics object
   */
  getStats(allSectionIds = []) {
    const totalSections = allSectionIds.length;
    const collapsedCount = allSectionIds.filter(id => this.isSectionCollapsed(id)).length;
    const expandedCount = totalSections - collapsedCount;

    return {
      total: totalSections,
      collapsed: collapsedCount,
      expanded: expandedCount,
      collapsedSections: Object.keys(this._collapsedSections),
      expandedSections: allSectionIds.filter(id => !this.isSectionCollapsed(id))
    };
  }

  /**
   * Load collapsed sections state from localStorage
   * @returns {Object} Collapsed sections object
   */
  loadCollapsedSections() {
    try {
      const stored = localStorage.getItem(STORAGE_KEYS.COLLAPSIBLE_SECTIONS);
      if (!stored) {
        return {};
      }

      const parsed = JSON.parse(stored);

      // Validate that it's an object
      if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
        console.warn("CollapsibleSectionsState: Invalid stored data format, resetting to empty state");
        return {};
      }

      // Validate that all values are booleans (and only keep true values for storage efficiency)
      const validated = {};
      Object.entries(parsed).forEach(([key, value]) => {
        if (typeof key === 'string' && key.length > 0 && Boolean(value) === true) {
          validated[key] = true;
        }
      });

      return validated;
    } catch (e) {
      console.error("CollapsibleSectionsState: Failed to load collapsed sections state:", e);
      return {};
    }
  }

  /**
   * Save collapsed sections state to localStorage
   */
  saveCollapsedSections() {
    try {
      localStorage.setItem(STORAGE_KEYS.COLLAPSIBLE_SECTIONS, JSON.stringify(this._collapsedSections));
    } catch (e) {
      console.error("CollapsibleSectionsState: Failed to save collapsed sections state:", e);
    }
  }

  /**
   * Clear all stored collapse states (reset to all expanded)
   */
  clearAll() {
    this._collapsedSections = {};
    this.saveCollapsedSections();
    console.log("CollapsibleSectionsState: Cleared all section states");
  }

  /**
   * Export current state for debugging or backup
   * @returns {Object} Current state object
   */
  exportState() {
    return {
      collapsedSections: { ...this._collapsedSections },
      timestamp: Date.now(),
      version: '1.0.0'
    };
  }

  /**
   * Import state from a backup (for debugging or state restoration)
   * @param {Object} stateData - State data to import
   * @returns {boolean} Success status
   */
  importState(stateData) {
    try {
      if (!stateData || typeof stateData !== 'object') {
        throw new Error("Invalid state data provided");
      }

      if (stateData.collapsedSections && typeof stateData.collapsedSections === 'object') {
        this._collapsedSections = { ...stateData.collapsedSections };
        this.saveCollapsedSections();
        console.log("CollapsibleSectionsState: Successfully imported state");
        return true;
      } else {
        throw new Error("No valid collapsedSections data found");
      }
    } catch (e) {
      console.error("CollapsibleSectionsState: Failed to import state:", e);
      return false;
    }
  }
}
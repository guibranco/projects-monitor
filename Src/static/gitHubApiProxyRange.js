// gitHubApiProxyRange.js
//
// The time range of the GitHub API proxy section (chart, totals and
// callers): chips above the chart, remembered per browser. The keys must
// match GitHubApiProxyRange on the PHP side; the API falls back to 24h for
// anything else.

const STORAGE_KEY = "projects-monitor.github-api-proxy.range";

export const GITHUB_API_PROXY_RANGES = new Map(
  Object.entries({
    "1h": "1 hour",
    "24h": "24 hours",
    "7d": "7 days",
    "30d": "30 days",
    "90d": "90 days",
  }),
);

export const DEFAULT_GITHUB_API_PROXY_RANGE = "24h";

/**
 * The range saved by an earlier visit, or the default when none (or an
 * unknown one) is saved or storage is unavailable.
 *
 * @returns {string}
 */
function loadGitHubApiProxyRange() {
  let saved = null;
  try {
    saved = localStorage.getItem(STORAGE_KEY);
  } catch (error) {
    console.warn("Failed to load the GitHub API proxy range:", error);
  }

  return GITHUB_API_PROXY_RANGES.has(saved)
    ? saved
    : DEFAULT_GITHUB_API_PROXY_RANGE;
}

// The page's selected range: read from storage once, then changed only by
// the picker, so a failed save or another tab cannot make the requests and
// the response guard disagree with the chip shown.
let activeRange = loadGitHubApiProxyRange();

/**
 * The selected range.
 *
 * @returns {string}
 */
export function getGitHubApiProxyRange() {
  return activeRange;
}

/**
 * Selects a range for this page and saves it for later visits; the
 * selection holds even when saving fails.
 *
 * @param {string} range - A key of GITHUB_API_PROXY_RANGES.
 * @returns {boolean} False when the range is unknown.
 */
export function setGitHubApiProxyRange(range) {
  if (!GITHUB_API_PROXY_RANGES.has(range)) {
    return false;
  }

  activeRange = range;
  try {
    localStorage.setItem(STORAGE_KEY, range);
  } catch (error) {
    console.warn("Failed to save the GitHub API proxy range:", error);
  }

  return true;
}

export class GitHubApiProxyRangePicker {
  /**
   * @param {string} elementId - Container of the chips.
   * @param {function(string): void} onChange - Called with the new range key.
   */
  constructor(elementId, onChange) {
    this.elementId = elementId;
    this.onChange = onChange;
    this.range = getGitHubApiProxyRange();
  }

  /**
   * Draws the chips; does nothing on pages without the section.
   */
  init() {
    const container = document.getElementById(this.elementId);
    if (!container) {
      return;
    }

    container.classList.add("entity-chips");
    container.setAttribute("role", "toolbar");
    container.setAttribute("aria-label", "GitHub API proxy time range");
    container.addEventListener("click", (event) => {
      const chip = event.target.closest("[data-range]");
      if (!chip || chip.dataset.range === this.range) {
        return;
      }

      if (!setGitHubApiProxyRange(chip.dataset.range)) {
        return;
      }

      this.range = getGitHubApiProxyRange();
      this.render(container);
      this.onChange(this.range);
    });
    this.render(container);
  }

  render(container) {
    const buttons = [...GITHUB_API_PROXY_RANGES].map(([range, label]) => {
      const active = range === this.range;
      const button = document.createElement("button");
      button.type = "button";
      button.className = active ? "entity-chip active" : "entity-chip";
      button.dataset.range = range;
      button.setAttribute("aria-pressed", String(active));
      button.textContent = label;
      return button;
    });
    container.replaceChildren(...buttons);
  }
}

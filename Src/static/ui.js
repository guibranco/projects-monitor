// ui.js
import { FEED_FILTERS, GITHUB_URLS } from './constants.js';

export class NotificationManager {
  static show(title, message, type) {
    const toastContainer = document.getElementById("toast-container");
    if (!toastContainer) {
      console.error("Toast container not found");
      return;
    }

    const validTypes = ["success", "error", "warning", "info"];
    if (!validTypes.includes(type)) {
      console.warn(`Invalid notification type: ${type}. Defaulting to 'info'`);
      type = "info";
    }

    const typeClass = {
      success: "bg-success text-white",
      error: "bg-danger text-white",
      warning: "bg-warning text-dark",
      info: "bg-info text-dark",
    };

    if (typeof bootstrap === "undefined") {
      console.error("Bootstrap is not loaded");
      window.alert(`${title}: ${message}`);
      return;
    }

    const toast = document.createElement("div");
    toast.className = `toast ${typeClass[type]}`;
    toast.setAttribute("role", "alert");
    toast.setAttribute("aria-live", "assertive");
    toast.setAttribute("aria-atomic", "true");
    toast.innerHTML = `
      <div class="toast-header">
        <strong class="me-auto">${title}</strong>
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body">${message}</div>
    `;

    toastContainer.appendChild(toast);

    const bootstrapToast = new bootstrap.Toast(toast, { delay: 3000 });
    bootstrapToast.show();

    toast.addEventListener("hidden.bs.toast", () => {
      toast.remove();
    });
  }
}

export class UIManager {
  constructor(feedState, workflowLimiterState) {
    this.feedState = feedState;
    this.workflowLimiterState = workflowLimiterState;
    this.eventAssigned = false;
    this.eventAssignedError = false;
  }

  updateFeedPreference(toggle) {
    if (!toggle || typeof toggle.checked !== "boolean") {
      console.error("Invalid toggle parameter");
      return;
    }
    this.feedState.filter = toggle.checked ? FEED_FILTERS.MINE : FEED_FILTERS.ALL;
  }

  initWorkflowLimiter() {
    const workflowToggle = document.getElementById("workflowToggle");
    const workflowLimitContainer = document.getElementById("workflowLimitContainer");
    const workflowLimitInput = document.getElementById("workflowLimitInput");

    if (!workflowToggle || !workflowLimitContainer || !workflowLimitInput) {
      console.error("Workflow limiter elements not found");
      return;
    }

    workflowToggle.checked = this.workflowLimiterState.enabled;
    workflowLimitContainer.style.display = this.workflowLimiterState.enabled
      ? "block"
      : "none";

    workflowLimitInput.value = this.workflowLimiterState.limitValue;

    workflowToggle.addEventListener("change", () => {
      this.workflowLimiterState.enabled = workflowToggle.checked;
      workflowLimitContainer.style.display = this.workflowLimiterState.enabled
        ? "block"
        : "none";
    });

    workflowLimitInput.addEventListener("input", () => {
      try {
        this.workflowLimiterState.limitValue = workflowLimitInput.value;
      } catch (e) {
        console.error(e.message);
      }
    });
  }

  initFeedToggle() {
    const toggle = document.getElementById("feedToggle");

    if (!toggle) {
      console.error("Feed toggle element not found");
      return;
    }

    toggle.checked = this.feedState.filter === FEED_FILTERS.MINE;
    toggle.addEventListener("change", () => this.updateFeedPreference(toggle));
  }

  confirmDelete(application) {
    const message = `Are you sure you want to delete messages for ${decodeURIComponent(application)}?`;
    return window.confirm(message);
  }

  confirmDeleteError(directory) {
    const message = `Are you sure you want to delete error_log file located at ${directory}?`;
    return window.confirm(message);
  }
}

export class GitHubStatsManager {
  static show() {
    const refresh = Math.floor(Math.random() * 100000);

    const statsUrl = `${GITHUB_URLS.STATS}&refresh=${refresh}`;
    const streakUrl = `${GITHUB_URLS.STREAK}&refresh=${refresh}`;
    const wakatimeUrl = `${GITHUB_URLS.WAKATIME}&refresh=${refresh}`;

    const statsImg = document.getElementById("gh_stats");
    const streakImg = document.getElementById("gh_streak");
    const wakatimeImg = document.getElementById("wakatime");

    if (!statsImg || !streakImg || !wakatimeImg) {
      console.error("GitHub/Wakatime stats image elements not found in the DOM");
      return;
    }

    this.loadImage(statsImg, statsUrl);
    this.loadImage(streakImg, streakUrl);
    this.loadImage(wakatimeImg, wakatimeUrl);
  }

  static loadImage(imgElement, url, options = {}) {
    const { maxRetries = 10, retryDelay = 2000, timeout = 30000 } = options;
    const startTime = Date.now();

    function cleanup() {
      imgElement.onload = null;
      imgElement.onerror = null;
    }

    imgElement.onload = () => {
      console.log(`${imgElement.id} loaded successfully.`);
      cleanup();
    };

    imgElement.onerror = () => {
      if (maxRetries > 0 && Date.now() - startTime < timeout) {
        console.warn(
          `${imgElement.id} failed to load. Retrying... (${maxRetries} retries left)`
        );
        setTimeout(
          () =>
            this.loadImage(imgElement, url, {
              ...options,
              maxRetries: maxRetries - 1,
            }),
          retryDelay
        );
      } else {
        console.error(
          `${imgElement.id} failed to load after ${maxRetries === 0 ? "maximum retries" : "timeout"}.`
        );
        cleanup();
      }
    };

    imgElement.src = url;
  }
}

export class CookieManager {
  static set(name, value, expireDays) {
    const date = new Date();
    date.setTime(date.getTime() + expireDays * 24 * 60 * 60 * 1000);
    document.cookie = `${name}=${value};expires=${date.toUTCString()};`;
  }
}
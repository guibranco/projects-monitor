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
      if (typeof $ !== "undefined" && typeof $.alert === "function") {
        $.alert({ title, content: message, theme: "dark" });
      } else {
        window.alert(`${title}: ${message}`);
      }
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

/**
 * Shows a themed confirmation dialog via jQuery.confirm, invoking onConfirm
 * only if the user confirms. Falls back to a native window.confirm if
 * jQuery/jquery-confirm isn't loaded (content is stripped of markup first,
 * since it's authored as HTML for the jQuery.confirm path).
 */
function showConfirm({ title, content, onConfirm }) {
  if (typeof $ === "undefined" || typeof $.confirm !== "function") {
    console.error("jQuery.confirm is not loaded");
    if (window.confirm(`${title}\n\n${content.replace(/<[^>]+>/g, "")}`)) {
      onConfirm?.();
    }
    return;
  }

  $.confirm({
    title,
    content,
    theme: "dark",
    type: "red",
    buttons: {
      confirm: {
        text: "Yes",
        btnClass: "btn-danger",
        action: () => onConfirm?.(),
      },
      cancel: {
        text: "Cancel",
      },
    },
  });
}

export class UIManager {
  constructor(feedState) {
    this.feedState = feedState;
    this.eventAssigned = false;
    this.eventAssignedError = false;
  }

  #esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  updateFeedPreference(toggle) {
    if (!toggle || typeof toggle.checked !== "boolean") {
      console.error("Invalid toggle parameter");
      return;
    }
    this.feedState.filter = toggle.checked ? FEED_FILTERS.MINE : FEED_FILTERS.ALL;
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

  confirmDelete(application, onConfirm) {
    const message = `Are you sure you want to delete messages for ${this.#esc(decodeURIComponent(application))}?`;
    showConfirm({ title: "Delete Messages", content: message, onConfirm });
  }

  confirmTruncateMessages(onConfirm) {
    showConfirm({
      title: "Truncate All Messages",
      content: "Are you sure you want to truncate all messages? This action cannot be undone.",
      onConfirm,
    });
  }

  confirmPurgeQueue(queueName, onConfirm) {
    showConfirm({
      title: "Purge Queue",
      content: `Are you sure you want to purge all messages from queue "${this.#esc(queueName)}"? This action cannot be undone.`,
      onConfirm,
    });
  }

  confirmDeleteError(directory, onConfirm) {
    const message = `Are you sure you want to delete error_log file located at ${this.#esc(directory)}?`;
    showConfirm({ title: "Delete Error Log", content: message, onConfirm });
  }

  confirmTruncateDbErrors(onConfirm) {
    showConfirm({
      title: "Truncate DB Errors",
      content: "Are you sure you want to truncate all DB error records? This action cannot be undone.",
      onConfirm,
    });
  }

  confirmDeleteErrorsByPath(path, onConfirm) {
    showConfirm({
      title: "Delete DB Error Records",
      content: `Delete all DB error records for:<br><code>${this.#esc(path)}</code><br><br>This action cannot be undone.`,
      onConfirm,
    });
  }

  confirmDeleteMessage(id, onConfirm) {
    showConfirm({ title: "Delete Message", content: `Delete message #${this.#esc(id)}?`, onConfirm });
  }

  confirmDeleteMessageGroup(rawApp, onConfirm) {
    showConfirm({
      title: "Delete Message Group",
      content: `Are you sure you want to delete all "${this.#esc(rawApp)}" messages in this group?`,
      onConfirm,
    });
  }

  confirmRunWorker(name, onConfirm) {
    showConfirm({
      title: "Run Worker",
      content: `Are you sure you want to trigger a run of the "${this.#esc(name)}" worker now?`,
      onConfirm,
    });
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
/* global bootstrap */
// applications.js — Applications page: create, rename, and rotate the key/token
// of the applications that send messages to the API.
import { API_ENDPOINTS } from "./constants.js";
import { ApiManager } from "./api.js";
import { NotificationManager } from "./ui.js";
import { relativeTimeHtml } from "./timeFormat.js";

const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");

/**
 * The key built from a name, as ApplicationKey::build() does on the server
 * (pmk-{acronym}-key). The server appends a number when the key is taken.
 *
 * @param {string} name - The application's name.
 * @returns {string}
 */
export function previewKey(name) {
  const ascii = String(name ?? "")
    .normalize("NFD")
    .replace(/\p{M}/gu, "")
    .replace(/[^\x20-\x7E]/g, "");
  const words = ascii.match(/[A-Z]+(?![a-z])|[A-Z]?[a-z]+|\d+/g) || [];
  const acronym = words.map((word) => word[0].toLowerCase()).join("") || "app";
  return `pmk-${acronym}-key`;
}

class ApplicationsPage {
  constructor() {
    this.apiManager = new ApiManager();
    this.applications = [];
    this.editingId = null;
    this.pendingRotation = null;

    this.table = document.getElementById("applications_table");
    this.counter = document.getElementById("counter_applications");
    this.formModal = new bootstrap.Modal(
      document.getElementById("app_form_modal"),
    );
    this.confirmModal = new bootstrap.Modal(
      document.getElementById("app_confirm_modal"),
    );
    this.secretModal = new bootstrap.Modal(
      document.getElementById("app_secret_modal"),
    );
    this.form = document.getElementById("app_form");
    this.nameInput = document.getElementById("app_form_name");
    this.preview = document.getElementById("app_form_preview");
  }

  start() {
    document
      .getElementById("app_new")
      .addEventListener("click", () => this.openForm(null));
    this.form.addEventListener("submit", (event) => {
      event.preventDefault();
      void this.submitForm();
    });
    this.nameInput.addEventListener("input", () => this.updatePreview());
    document
      .getElementById("app_form_modal")
      .addEventListener("shown.bs.modal", () => this.nameInput.focus());
    document
      .getElementById("app_confirm_ok")
      .addEventListener("click", () => void this.confirmRotation());
    document
      .getElementById("app_secret_modal")
      .addEventListener("hidden.bs.modal", () => {
        // Never leave a token in the DOM once the dialog is closed.
        document.getElementById("app_secret_token").value = "";
      });
    document
      .querySelectorAll("[data-copy]")
      .forEach((button) =>
        button.addEventListener(
          "click",
          () => void this.copy(button.dataset.copy),
        ),
      );
    this.table.addEventListener("click", (event) => this.onTableClick(event));

    this.load();
  }

  load() {
    this.apiManager.load(API_ENDPOINTS.APPLICATIONS, (data) => {
      this.applications = Array.isArray(data?.applications)
        ? data.applications
        : [];
      this.render();
    });
  }

  render() {
    this.counter.textContent = String(this.applications.length);

    if (this.applications.length === 0) {
      this.table.innerHTML =
        '<tr><td colspan="7" class="text-center apps-empty">No applications yet.</td></tr>';
      return;
    }

    const when = (value, empty) =>
      value
        ? (relativeTimeHtml(value) ?? escapeHtml(value))
        : `<span class="apps-muted">${empty}</span>`;

    this.table.innerHTML = this.applications
      .map((app) => {
        const keyWarning = app.keyFollowsPattern
          ? ""
          : ` <i class="bi bi-exclamation-circle text-warning" title="This key does not follow pmk-${escapeHtml(previewKey(app.name).slice(4, -4))}-key. Rotate the key to fix it (the application must then use the new key)."></i>`;
        return `<tr data-id="${app.id}">
          <td class="fw-semibold">${escapeHtml(app.name)}</td>
          <td><code class="apps-code">${escapeHtml(app.key)}</code>${keyWarning}</td>
          <td><code class="apps-code apps-muted">${escapeHtml(app.tokenMasked)}</code></td>
          <td>${when(app.lastSeenAt, "never")}</td>
          <td>${when(app.updatedAt, "—")}</td>
          <td>${when(app.createdAt, "—")}</td>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-light" data-action="rename" title="Rename"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-sm btn-outline-warning" data-action="rotate-key" title="Rotate key"><i class="bi bi-key"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-action="rotate-token" title="Rotate token"><i class="bi bi-arrow-repeat"></i></button>
          </td>
        </tr>`;
      })
      .join("");
  }

  onTableClick(event) {
    const button = event.target.closest("button[data-action]");
    if (!button) {
      return;
    }

    const id = Number(button.closest("tr").dataset.id);
    const app = this.applications.find((item) => item.id === id);
    if (!app) {
      return;
    }

    if (button.dataset.action === "rename") {
      this.openForm(app);
    } else if (button.dataset.action === "rotate-key") {
      this.askRotation(app, "key");
    } else if (button.dataset.action === "rotate-token") {
      this.askRotation(app, "token");
    }
  }

  openForm(app) {
    this.editingId = app ? app.id : null;
    document.getElementById("app_form_title").textContent = app
      ? "Rename application"
      : "New application";
    document.getElementById("app_form_submit").textContent = app
      ? "Save"
      : "Create";
    this.nameInput.value = app ? app.name : "";
    this.updatePreview();
    this.formModal.show();
  }

  updatePreview() {
    const name = this.nameInput.value.trim();
    if (this.editingId !== null) {
      this.preview.textContent =
        "The key is kept. Rotate it afterwards to follow the new name.";
      return;
    }

    this.preview.innerHTML = name
      ? `Key: <code>${escapeHtml(previewKey(name))}</code> (a number is added when it is taken)`
      : "";
  }

  async submitForm() {
    const name = this.nameInput.value.trim();
    if (!name) {
      NotificationManager.show("Error", "The name is required", "error");
      return;
    }

    const creating = this.editingId === null;
    const data = creating
      ? await this.post(API_ENDPOINTS.APPLICATIONS, { name })
      : await this.post(API_ENDPOINTS.APPLICATIONS_UPDATE, {
          id: this.editingId,
          name,
        });
    if (!data) {
      return;
    }

    this.formModal.hide();
    this.load();

    if (creating) {
      this.showSecret(
        data.application,
        `Application ${data.application.name} created`,
      );
    } else {
      NotificationManager.show("Success", "Application renamed", "success");
    }
  }

  askRotation(app, target) {
    this.pendingRotation = { id: app.id, target };
    const name = escapeHtml(app.name);
    document.getElementById("app_confirm_title").textContent =
      target === "key" ? "Rotate key" : "Rotate token";
    document.getElementById("app_confirm_body").innerHTML =
      target === "key"
        ? `<p>The key of <strong>${name}</strong> becomes <code>${escapeHtml(previewKey(app.name))}</code> (or the next free one).</p>
           <p class="mb-0">The current key <code>${escapeHtml(app.key)}</code> stops working at once.</p>`
        : `<p>A new token is generated for <strong>${name}</strong>.</p>
           <p class="mb-0">The current token stops working at once: update the application with the new one.</p>`;
    this.confirmModal.show();
  }

  async confirmRotation() {
    const rotation = this.pendingRotation;
    this.pendingRotation = null;
    this.confirmModal.hide();
    if (!rotation) {
      return;
    }

    const data = await this.post(API_ENDPOINTS.APPLICATIONS_ROTATE, rotation);
    if (!data) {
      return;
    }

    this.load();
    if (rotation.target === "token") {
      this.showSecret(
        data.application,
        `Token of ${data.application.name} rotated`,
      );
    } else {
      NotificationManager.show(
        "Success",
        `Key of ${escapeHtml(data.application.name)} is now ${escapeHtml(data.application.key)}`,
        "success",
      );
    }
  }

  showSecret(app, title) {
    document.getElementById("app_secret_title").textContent = title;
    document.getElementById("app_secret_key").value = app.key;
    document.getElementById("app_secret_token").value = app.token ?? "";
    this.secretModal.show();
  }

  async copy(inputId) {
    const value = document.getElementById(inputId).value;
    try {
      await navigator.clipboard.writeText(value);
      NotificationManager.show("Copied", "Copied to the clipboard", "success");
    } catch (error) {
      console.warn("Clipboard unavailable", error);
      document.getElementById(inputId).select();
      NotificationManager.show("Copy", "Press Ctrl+C to copy", "info");
    }
  }

  /**
   * POSTs JSON and returns the parsed response, or null after showing the error.
   */
  async post(url, body) {
    try {
      const response = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });

      if (response.status === 401 || response.status === 403) {
        this.apiManager.showLoginModal();
        return null;
      }

      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(data.error || `Request failed (${response.status})`);
      }

      return data;
    } catch (error) {
      NotificationManager.show("Error", escapeHtml(error.message), "error");
      return null;
    }
  }
}

const page = new ApplicationsPage();
window.addEventListener("load", () => page.start());

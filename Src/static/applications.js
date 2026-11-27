/* global bootstrap */
// applications.js — Applications page: create, rename, and rotate the key/token
// of the applications that send messages to the API.
import { API_ENDPOINTS } from "./constants.js";
import { ApiManager } from "./api.js";
import { NotificationManager } from "./ui.js";
import { formatRelative, parseTableDate } from "./timeFormat.js";

const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");

/**
 * Creates an element with the given attributes and children (strings become
 * text nodes, so no value is ever parsed as HTML).
 *
 * @param {string} tag - The tag name.
 * @param {Object<string, string>} [attributes] - The attributes to set.
 * @param {...(Node|string)} children - The child nodes or texts.
 * @returns {HTMLElement}
 */
function el(tag, attributes = {}, ...children) {
  const element = document.createElement(tag);
  for (const [name, value] of Object.entries(attributes)) {
    element.setAttribute(name, value);
  }
  element.append(...children);
  return element;
}

/**
 * A <time> element showing a table date relatively, the exact value on
 * hover; the text itself when it is not a date.
 *
 * @param {string} text - The date text.
 * @returns {Node}
 */
function relativeTimeNode(text) {
  const date = parseTableDate(text);
  if (!date) {
    return document.createTextNode(String(text));
  }

  return el(
    "time",
    { datetime: date.toISOString(), title: String(text) },
    formatRelative(date, Date.now()),
  );
}

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
    this.busy = false;
    this.openModals = 0;
    this.afterModalsClosed = [];

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
    for (const id of ["app_form_modal", "app_confirm_modal"]) {
      const element = document.getElementById(id);
      element.addEventListener("show.bs.modal", () => {
        this.openModals += 1;
      });
      element.addEventListener("hidden.bs.modal", () => this.onModalHidden());
    }
    this.table.addEventListener("click", (event) => this.onTableClick(event));

    void this.load();
  }

  async load() {
    try {
      // eslint-disable-next-line compat/compat
      const response = await fetch(API_ENDPOINTS.APPLICATIONS);
      if (response.status === 401 || response.status === 403) {
        this.apiManager.showLoginModal();
        return;
      }

      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(data.error || `Request failed (${response.status})`);
      }

      this.applications = Array.isArray(data?.applications)
        ? data.applications
        : [];
      this.render();
    } catch (error) {
      this.showLoadError(error.message);
    }
  }

  /**
   * Replaces the table body with the load error and a Retry button.
   */
  showLoadError(message) {
    const cell = document.createElement("td");
    cell.colSpan = 7;
    cell.className = "text-center apps-empty";
    cell.textContent = `Could not load the applications: ${message}`;

    const retry = document.createElement("button");
    retry.type = "button";
    retry.className = "btn btn-sm btn-outline-light ms-2";
    retry.dataset.action = "retry";
    retry.textContent = "Retry";
    cell.append(retry);

    const row = document.createElement("tr");
    row.append(cell);
    this.table.replaceChildren(row);
  }

  /**
   * Runs the callbacks waiting for the form and confirmation dialogs to be
   * closed, once both are.
   */
  onModalHidden() {
    this.openModals = Math.max(0, this.openModals - 1);
    if (this.openModals > 0) {
      return;
    }

    const callbacks = this.afterModalsClosed;
    this.afterModalsClosed = [];
    callbacks.forEach((callback) => callback());
  }

  /**
   * Calls back now, or once the form and confirmation dialogs are closed, so
   * that one dialog never opens while another is still closing.
   */
  whenModalsClosed(callback) {
    if (this.openModals === 0) {
      callback();
      return;
    }

    this.afterModalsClosed.push(callback);
  }

  /**
   * Disables every action while a change is being saved, so two changes
   * (two rotations, say) never overlap.
   */
  setBusy(busy) {
    this.busy = busy;
    document
      .querySelectorAll(
        "#applications_table button, #app_new, #app_form_submit, #app_confirm_ok",
      )
      .forEach((button) => {
        button.disabled = busy;
      });
  }

  render() {
    this.counter.textContent = String(this.applications.length);

    if (this.applications.length === 0) {
      this.table.replaceChildren(
        el(
          "tr",
          {},
          el(
            "td",
            { colspan: "7", class: "text-center apps-empty" },
            "No applications yet.",
          ),
        ),
      );
      return;
    }

    const when = (value, empty) =>
      value
        ? relativeTimeNode(value)
        : el("span", { class: "apps-muted" }, empty);
    const action = (name, title, style, icon) =>
      el(
        "button",
        {
          type: "button",
          class: `btn btn-sm btn-outline-${style}`,
          "data-action": name,
          title,
        },
        el("i", { class: `bi ${icon}` }),
      );

    this.table.replaceChildren(
      ...this.applications.map((app) => {
        const keyCell = el(
          "td",
          {},
          el("code", { class: "apps-code" }, String(app.key ?? "")),
        );
        if (!app.keyFollowsPattern) {
          keyCell.append(
            " ",
            el("i", {
              class: "bi bi-exclamation-circle text-warning",
              title: `This key does not follow pmk-${previewKey(app.name).slice(4, -4)}-key. Rotate the key to fix it (the application must then use the new key).`,
            }),
          );
        }

        return el(
          "tr",
          { "data-id": String(Number(app.id)) },
          el("td", { class: "fw-semibold" }, String(app.name ?? "")),
          keyCell,
          el(
            "td",
            {},
            el(
              "code",
              { class: "apps-code apps-muted" },
              String(app.tokenMasked ?? ""),
            ),
          ),
          el("td", {}, when(app.lastSeenAt, "never")),
          el("td", {}, when(app.updatedAt, "—")),
          el("td", {}, when(app.createdAt, "—")),
          el(
            "td",
            { class: "text-end text-nowrap" },
            action("rename", "Rename", "light", "bi-pencil"),
            " ",
            action("rotate-key", "Rotate key", "warning", "bi-key"),
            " ",
            action("rotate-token", "Rotate token", "danger", "bi-arrow-repeat"),
          ),
        );
      }),
    );
  }

  onTableClick(event) {
    const button = event.target.closest("button[data-action]");
    if (!button || this.busy) {
      return;
    }

    if (button.dataset.action === "retry") {
      void this.load();
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

    if (!name) {
      this.preview.replaceChildren();
      return;
    }

    this.preview.replaceChildren(
      "Key: ",
      el("code", {}, previewKey(name)),
      " (a number is added when it is taken)",
    );
  }

  async submitForm() {
    const name = this.nameInput.value.trim();
    if (this.busy) {
      return;
    }

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
    void this.load();

    if (creating) {
      this.whenModalsClosed(() =>
        this.showSecret(
          data.application,
          `Application ${data.application.name} created`,
        ),
      );
    } else {
      NotificationManager.show("Success", "Application renamed", "success");
    }
  }

  askRotation(app, target) {
    this.pendingRotation = { id: app.id, target };
    const name = () => el("strong", {}, String(app.name ?? ""));
    const code = (text) => el("code", {}, String(text ?? ""));
    document.getElementById("app_confirm_title").textContent =
      target === "key" ? "Rotate key" : "Rotate token";

    let paragraphs;
    if (target !== "key") {
      paragraphs = [
        el("p", {}, "A new token is generated for ", name(), "."),
        el(
          "p",
          { class: "mb-0" },
          "The current token stops working at once: update the application with the new one.",
        ),
      ];
    } else if (app.keyFollowsPattern) {
      paragraphs = [
        el(
          "p",
          {},
          "The key of ",
          name(),
          " is rebuilt from its name: ",
          code(previewKey(app.name)),
          ", or the next free one.",
        ),
        el(
          "p",
          { class: "mb-0" },
          "The current key ",
          code(app.key),
          " already follows the pattern, so it is likely kept. If it changes, the current one stops working at once.",
        ),
      ];
    } else {
      paragraphs = [
        el(
          "p",
          {},
          "The key of ",
          name(),
          " becomes ",
          code(previewKey(app.name)),
          ", or the next free one.",
        ),
        el(
          "p",
          { class: "mb-0" },
          "The current key ",
          code(app.key),
          " stops working at once.",
        ),
      ];
    }
    document.getElementById("app_confirm_body").replaceChildren(...paragraphs);
    this.confirmModal.show();
  }

  async confirmRotation() {
    const rotation = this.pendingRotation;
    this.pendingRotation = null;
    this.confirmModal.hide();
    if (!rotation || this.busy) {
      return;
    }

    const data = await this.post(API_ENDPOINTS.APPLICATIONS_ROTATE, rotation);
    if (!data) {
      return;
    }

    void this.load();
    if (rotation.target === "token") {
      this.whenModalsClosed(() =>
        this.showSecret(
          data.application,
          `Token of ${data.application.name} rotated`,
        ),
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
    this.setBusy(true);
    try {
      // eslint-disable-next-line compat/compat
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
    } finally {
      this.setBusy(false);
    }
  }
}

const page = new ApplicationsPage();
window.addEventListener("load", () => page.start());

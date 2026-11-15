// commandPalette.js
//
// Ctrl+K (Cmd+K on macOS) palette: type to jump to another page or to a
// section of this page (expanding it if it is collapsed).

import { headerTitle } from "./sectionTitle.js";

const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");

export class CommandPalette {
  constructor() {
    this.modal = null;
    this.opening = false;
    this.items = [];
    this.matches = [];
    this.selected = 0;
  }

  init() {
    document.addEventListener("keydown", (event) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        this.open();
      }
    });
    document
      .getElementById("command_palette_button")
      ?.addEventListener("click", () => this.open());
  }

  /**
   * Pages from the navbar tabs, then the sections of this page.
   *
   * @returns {Array<{label:string, hint:string, run:Function}>}
   */
  collectItems() {
    const pages = [...document.querySelectorAll(".page-tab")].map((tab) => ({
      label: tab.title.split(" (")[0],
      hint: "Page",
      run: () => {
        window.location.href = tab.getAttribute("href");
      },
    }));
    const sections = [...document.querySelectorAll("[data-section]")].map(
      (section) => this.sectionItem(section),
    );
    return [...pages, ...sections];
  }

  /**
   * The palette entry of a section: scrolls to it, showing and expanding it.
   *
   * @param {HTMLElement} section - The section ([data-section]).
   * @returns {{label:string, hint:string, run:Function}}
   */
  sectionItem(section) {
    const header = section.querySelector(".section-header");
    const item = {
      label: headerTitle(header) || section.dataset.section,
      hint: "Section",
      run: () => {
        section.classList.remove("is-empty");
        if (header?.classList.contains("collapsed")) header.click();
        section.scrollIntoView({ behavior: "smooth", block: "start" });
      },
    };
    return item;
  }

  build() {
    document.body.insertAdjacentHTML(
      "beforeend",
      `<div class="modal fade" id="command_palette" tabindex="-1" aria-label="Jump to" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-scrollable command-palette">
          <div class="modal-content">
            <input type="search" class="form-control command-palette-input" placeholder="Jump to a page or section…" aria-label="Jump to a page or section">
            <ul class="command-palette-list" role="listbox"></ul>
          </div>
        </div>
      </div>`,
    );
    const element = document.getElementById("command_palette");
    this.modal = new window.bootstrap.Modal(element);
    const input = element.querySelector("input");
    element.addEventListener("shown.bs.modal", () => {
      this.opening = false;
      input.focus();
    });
    // Keys typed while the palette fades in would be lost: handle them here.
    document.addEventListener("keydown", (event) => {
      if (
        !this.opening ||
        event.target === input ||
        event.ctrlKey ||
        event.metaKey
      )
        return;
      if (event.key.length === 1) {
        event.preventDefault();
        input.value += event.key;
        this.filter(input.value);
      } else {
        this.onKey(event);
      }
    });
    input.addEventListener("input", () => this.filter(input.value));
    input.addEventListener("keydown", (event) => this.onKey(event));
    element.querySelector("ul").addEventListener("click", (event) => {
      const option = event.target.closest("[data-index]");
      if (option) this.choose(Number(option.dataset.index));
    });
  }

  open() {
    if (!this.modal) this.build();
    this.items = this.collectItems();
    const input = document.querySelector("#command_palette input");
    input.value = "";
    this.filter("");
    this.opening = true;
    this.modal.show();
  }

  filter(query) {
    const words = query.toLowerCase().split(/\s+/).filter(Boolean);
    this.matches = this.items.filter((item) =>
      words.every((word) => item.label.toLowerCase().includes(word)),
    );
    this.selected = 0;
    this.renderList();
  }

  renderList() {
    const list = document.querySelector("#command_palette ul");
    list.innerHTML =
      this.matches
        .map(
          (item, index) =>
            `<li role="option" data-index="${index}" aria-selected="${index === this.selected}" class="${index === this.selected ? "active" : ""}"><span>${escapeHtml(item.label)}</span><small>${item.hint}</small></li>`,
        )
        .join("") || '<li class="text-white-50">No match</li>';
  }

  onKey(event) {
    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      const step = event.key === "ArrowDown" ? 1 : -1;
      this.selected =
        (this.selected + step + this.matches.length) %
        Math.max(this.matches.length, 1);
      this.renderList();
    } else if (event.key === "Enter") {
      event.preventDefault();
      this.choose(this.selected);
    }
  }

  choose(index) {
    const item = this.matches.find((_match, position) => position === index);
    if (!item) return;
    this.modal.hide();
    item.run();
  }
}

// emptySections.js
//
// Hides sections whose counter reads 0, so empty tables don't take as
// much room as real problems. They are listed in one line at the bottom
// of the page ("3 empty sections hidden: … · Show"), which can show them.

export class EmptySectionsManager {
  constructor() {
    this.sections = [];
  }

  init() {
    this.sections = [...document.querySelectorAll("[data-section]")]
      .map((section) => ({
        section,
        counter: section.querySelector('.section-header [id^="counter_"]'),
        title:
          section
            .querySelector(".section-header")
            ?.textContent.trim()
            .replace(/\s+\d+$/, "") ?? "",
      }))
      .filter((entry) => entry.counter);

    // eslint-disable-next-line compat/compat
    const observer = new MutationObserver(() => this.update());
    for (const entry of this.sections) {
      observer.observe(entry.counter, {
        childList: true,
        characterData: true,
        subtree: true,
      });
    }

    document
      .getElementById("empty_sections_summary")
      ?.addEventListener("click", (event) => {
        if (event.target.closest("[data-action='toggle-empty']")) {
          document.body.classList.toggle("show-empty-sections");
          this.update();
        }
      });
  }

  update() {
    const empty = [];
    for (const entry of this.sections) {
      const isEmpty = entry.counter.textContent.trim() === "0";
      entry.section.classList.toggle("is-empty", isEmpty);
      if (isEmpty) {
        empty.push(entry.title);
      }
    }

    const summary = document.getElementById("empty_sections_summary");
    if (!summary) return;

    if (empty.length === 0) {
      summary.innerHTML = "";
      return;
    }

    const showing = document.body.classList.contains("show-empty-sections");
    const noun = empty.length === 1 ? "section" : "sections";
    summary.innerHTML = `<span class="status-dot status-ok" aria-hidden="true"></span>${empty.length} empty ${noun} ${showing ? "shown" : "hidden"}: ${empty
      .map((title) => title.replaceAll("<", "&lt;"))
      .join(
        ", ",
      )} · <button type="button" class="btn btn-link btn-sm p-0 attention-link" data-action="toggle-empty">${showing ? "Hide" : "Show"}</button>`;
  }
}

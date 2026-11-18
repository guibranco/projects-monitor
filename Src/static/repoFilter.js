// repoFilter.js
//
// The global repository filter in the navbar. It applies on every page:
// tables with a Repository / Repo / Project column only show the rows whose
// repository matches (case-insensitive substring of "owner/name"), and the
// Needs attention list only shows the repository-bound items (workflow
// runs) that match. Tables and items without a repository are not
// filtered. The value is kept in localStorage and can be set with ?repo=
// in the URL, so a filtered link can be shared.

const STORAGE_KEY = "repoFilter";
const CHANGE_EVENT = "repofilterchange";

export const REPO_COLUMN = /^(repo|repository|repositories|project)$/i;

// Attention sources whose items each belong to one repository.
export const REPO_BOUND_SOURCES = new Set(["workflow-runs"]);

let current = null;

/**
 * Text of a cell without its HTML tags, plus the URLs of its links, so a
 * filter matches both "owner/name" text and github.com/owner/name links.
 *
 * @param {*} cell - A table cell.
 * @returns {string}
 */
export function cellText(cell) {
  const value = String(cell ?? "");
  const links = [...value.matchAll(/href="([^"]*)"/g)].map((match) => match[1]);
  return [value.replace(/<[^>]*>/g, " "), ...links].join(" ").toLowerCase();
}

/**
 * Whether a text matches the filter (an empty filter matches everything).
 *
 * @param {string} text - The text.
 * @param {string|null} filter - The filter.
 * @returns {boolean}
 */
export function matchesRepo(text, filter) {
  const needle = String(filter ?? "")
    .trim()
    .toLowerCase();
  return needle === "" || cellText(text).includes(needle);
}

/**
 * The rows of a table that match the filter.
 *
 * @param {Array<*>} header - The header row.
 * @param {Array<Array<*>>} rows - The body rows.
 * @param {string|null} filter - The filter.
 * @param {boolean} anyCell - Match any cell (tables whose repository sits in a details cell).
 * @returns {{rows: Array<Array<*>>, hidden: number, applied: boolean}}
 */
export function filterRows(header, rows, filter, anyCell = false) {
  const needle = String(filter ?? "").trim();
  const columns = (header || [])
    .map((name, index) =>
      REPO_COLUMN.test(cellText(name).trim()) ? index : -1,
    )
    .filter((index) => index >= 0);

  const result = { rows, hidden: 0, applied: false };
  if (needle === "" || (!anyCell && columns.length === 0)) {
    return result;
  }

  result.rows = rows.filter((row) =>
    row
      .filter((_cell, position) => anyCell || columns.includes(position))
      .some((cell) => matchesRepo(cell, needle)),
  );
  result.hidden = rows.length - result.rows.length;
  result.applied = true;
  return result;
}

/**
 * Whether an attention item is shown under the filter: items not bound to
 * one repository always are.
 *
 * @param {object} item - An attention item.
 * @param {string|null} filter - The filter.
 * @returns {boolean}
 */
export function attentionItemMatches(item, filter) {
  if (!REPO_BOUND_SOURCES.has(item?.source)) {
    return true;
  }

  return matchesRepo(`${item.title ?? ""} ${item.fingerprint ?? ""}`, filter);
}

/**
 * The current filter, "" when none.
 *
 * @returns {string}
 */
export function getRepoFilter() {
  if (current !== null) {
    return current;
  }

  let value = "";
  try {
    // eslint-disable-next-line compat/compat
    const fromUrl = new URLSearchParams(window.location.search).get("repo");
    value =
      fromUrl !== null
        ? fromUrl
        : window.localStorage.getItem(STORAGE_KEY) || "";
    if (fromUrl !== null) {
      window.localStorage.setItem(STORAGE_KEY, fromUrl);
    }
  } catch {
    value = "";
  }

  current = value.trim();
  return current;
}

/**
 * Sets the filter, keeps it, and tells the tables and the attention list.
 *
 * @param {string} value - The new filter ("" clears it).
 */
export function setRepoFilter(value) {
  const next = String(value ?? "").trim();
  if (next === getRepoFilter()) {
    return;
  }

  current = next;
  try {
    if (next === "") {
      window.localStorage.removeItem(STORAGE_KEY);
    } else {
      window.localStorage.setItem(STORAGE_KEY, next);
    }

    // eslint-disable-next-line compat/compat
    const url = new URL(window.location.href);
    if (next === "") {
      url.searchParams.delete("repo");
    } else {
      url.searchParams.set("repo", next);
    }
    window.history.replaceState(null, "", url);
  } catch {
    // Storage or history unavailable: the filter still applies to this page.
  }

  window.dispatchEvent(new CustomEvent(CHANGE_EVENT, { detail: next }));
}

/**
 * Calls back whenever the filter changes.
 *
 * @param {Function} callback - Receives the new filter.
 */
export function onRepoFilterChange(callback) {
  window.addEventListener(CHANGE_EVENT, (event) => callback(event.detail));
}

/**
 * The navbar control: a search box (applied as you type, after a pause)
 * with a clear button, and "/" to focus it.
 */
export class RepoFilterControl {
  init() {
    const input = document.getElementById("repo_filter");
    const clear = document.getElementById("repo_filter_clear");
    if (!input) return;

    const sync = () => {
      const value = getRepoFilter();
      if (document.activeElement !== input) input.value = value;
      input
        .closest(".repo-filter")
        ?.classList.toggle("is-active", value !== "");
      if (clear) clear.hidden = value === "";
    };

    let timer = null;
    input.addEventListener("input", () => {
      clearTimeout(timer);
      timer = setTimeout(() => setRepoFilter(input.value), 300);
    });
    input.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        clearTimeout(timer);
        setRepoFilter(input.value);
      } else if (event.key === "Escape") {
        clearTimeout(timer);
        input.value = "";
        setRepoFilter("");
        input.blur();
      }
    });
    clear?.addEventListener("click", () => {
      input.value = "";
      setRepoFilter("");
    });
    document.addEventListener("keydown", (event) => {
      const typing = event.target.closest?.(
        "input, textarea, select, [contenteditable]",
      );
      if (event.key === "/" && !typing && !event.ctrlKey && !event.metaKey) {
        event.preventDefault();
        input.focus();
      }
    });

    onRepoFilterChange(sync);
    sync();
  }
}

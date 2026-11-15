// timeFormat.js
//
// Relative times ("5m ago") with the exact time on hover, for the
// dashboard's tables and indicators.

/**
 * "40s", "5m", "3h", "2d": a compact duration.
 *
 * @param {number} seconds - Elapsed seconds.
 * @returns {string}
 */
export function formatDuration(seconds) {
  const value = Math.max(0, Math.floor(seconds));
  if (value < 60) return `${value}s`;
  if (value < 3600) return `${Math.floor(value / 60)}m`;
  if (value < 86400) return `${Math.floor(value / 3600)}h`;
  return `${Math.floor(value / 86400)}d`;
}

const DASHBOARD_FORMAT = /^(\d{2}):(\d{2}):(\d{2}) (\d{2})\/(\d{2})\/(\d{4})$/;
const SQL_FORMAT = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/;
const ISO_FORMAT =
  /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})$/;

/**
 * Parses the date formats the dashboard's tables use: "H:i:s d/m/Y" and
 * "Y-m-d H:i:s" (both in the browser's time zone, which the API is told
 * through the timezone cookie) and ISO 8601 with a zone.
 *
 * @param {string} text - The cell text.
 * @returns {Date|null} Null when the text is not one of those dates.
 */
export function parseTableDate(text) {
  const value = String(text ?? "").trim();
  let match = DASHBOARD_FORMAT.exec(value);
  if (match) {
    const [, hour, minute, second, day, month, year] = match.map(Number);
    return new Date(year, month - 1, day, hour, minute, second);
  }

  match = SQL_FORMAT.exec(value);
  if (match) {
    const [, year, month, day, hour, minute, second] = match.map((part) =>
      Number(part ?? 0),
    );
    return new Date(year, month - 1, day, hour, minute, second);
  }

  if (ISO_FORMAT.test(value)) {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
  }

  return null;
}

/**
 * "5m ago" or "in 3d" for a date, relative to now.
 *
 * @param {Date} date - The date.
 * @param {number} now - The current time, in milliseconds.
 * @returns {string}
 */
export function formatRelative(date, now) {
  const seconds = (now - date.getTime()) / 1000;
  return seconds >= 0
    ? `${formatDuration(seconds)} ago`
    : `in ${formatDuration(-seconds)}`;
}

/**
 * A <time> element showing a table date relatively, the exact value on
 * hover; null when the text is not a date.
 *
 * @param {string} text - The cell text.
 * @returns {string|null}
 */
export function relativeTimeHtml(text) {
  const date = parseTableDate(text);
  if (!date) {
    return null;
  }

  const exact = String(text)
    .replaceAll("&", "&amp;")
    .replaceAll('"', "&quot;")
    .replaceAll("<", "&lt;");
  return `<time datetime="${date.toISOString()}" title="${exact}">${formatRelative(date, Date.now())}</time>`;
}

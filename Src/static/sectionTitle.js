// sectionTitle.js
/**
 * A section header's title: its own text, without the icon or the counter.
 *
 * @param {HTMLElement|null} header - The .section-header element.
 * @returns {string}
 */
export function headerTitle(header) {
  return [...(header?.childNodes ?? [])]
    .filter((node) => node.nodeType === Node.TEXT_NODE)
    .map((node) => node.textContent)
    .join("")
    .trim();
}

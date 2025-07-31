// collapsibleSections.js
import { CollapsibleSectionsState } from './storage.js';

export class CollapsibleSectionsManager {
    constructor() {
        this.isInitialized = false;
        this.sections = new Map();
        this.state = new CollapsibleSectionsState();

        // Bind methods to maintain context
        this.init = this.init.bind(this);
        this.handleHeaderClick = this.handleHeaderClick.bind(this);
        this.toggleSection = this.toggleSection.bind(this);
    }

    /**
     * Initializes collapsible sections functionality when the DOM is ready.
     */
    init() {
        if (this.isInitialized) return;

        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initializeCollapsibleSections());
        } else {
            this.initializeCollapsibleSections();
        }
    }

    /**
     * Initializes all collapsible sections on the page by setting up event listeners,
     * keyboard support, focusability, ARIA attributes, and restoring previous states.
     */
    initializeCollapsibleSections() {
        const sectionHeaders = document.querySelectorAll('.section-header');

        sectionHeaders.forEach(header => {
            // Add click event listener
            header.addEventListener('click', (e) => this.handleHeaderClick(e, header));

            // Add keyboard support for accessibility
            header.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.handleHeaderClick(e, header);
                }
            });

            // Make header focusable for keyboard navigation
            if (!header.hasAttribute('tabindex')) {
                header.setAttribute('tabindex', '0');
            }

            // Set up ARIA attributes for accessibility
            this.setupAccessibility(header);

            // Store section reference
            const content = this.getContentElement(header);
            if (content) {
                this.sections.set(content.id, { header, content });
            }

            // Restore previous state from storage
            this.restoreState(header);
        });

        this.isInitialized = true;
        console.log(`CollapsibleSectionsManager: Initialized ${sectionHeaders.length} collapsible sections`);

        // Log initial statistics
        const stats = this.getSectionStats();
        console.log(`CollapsibleSectionsManager: Initial state - ${stats.collapsed} collapsed, ${stats.expanded} expanded`);
    }

    /**
     * Set up accessibility attributes for a header and its associated content element.
     *
     * This function ensures that the header and content elements have the necessary
     * ARIA attributes for better accessibility. It assigns unique IDs if they are missing
     * and sets the `aria-expanded`, `aria-controls`, and `aria-labelledby` attributes accordingly.
     *
     * @param {HTMLElement} header - The header element to which accessibility attributes will be set.
     */
    setupAccessibility(header) {
        const content = this.getContentElement(header);
        if (content) {
            const contentId = content.id || `content_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
            if (!content.id) content.id = contentId;

            header.setAttribute('aria-expanded', 'true');
            header.setAttribute('aria-controls', contentId);
            content.setAttribute('aria-labelledby', header.id || contentId + '_header');

            if (!header.id) {
                header.id = contentId + '_header';
            }
        }
    }

    /**
     * Handles click events on headers to toggle sections.
     *
     * This function prevents clicks on badges from triggering section toggles.
     * It adds a loading state briefly for visual feedback and then toggles the section.
     *
     * @param {Event} event - The click event object.
     * @param {HTMLElement} header - The header element that was clicked.
     */
    handleHeaderClick(event, header) {
        // Prevent badge clicks from triggering collapse
        if (event.target.classList.contains('badge') || event.target.closest('.badge')) {
            return;
        }

        const content = this.getContentElement(header);
        if (!content) return;

        // Add loading state briefly for visual feedback
        header.classList.add('loading');

        setTimeout(() => {
            header.classList.remove('loading');
            this.toggleSection(header, content);
        }, 150);
    }

    /**
     * Find and potentially mark the content element associated with a header.
     *
     * This function starts by checking the next sibling of the provided header.
     * If it doesn't have the 'section-content' class, the function then checks if
     * the sibling's ID contains specific keywords or if its tag name is 'DIV'.
     * If either condition is met, the class is added to the element, indicating that
     * it is a content container. The function returns the identified content element.
     *
     * @param {HTMLElement} header - The header element for which to find the associated content.
     * @returns {HTMLElement} The content element associated with the given header.
     */
    getContentElement(header) {
        let content = header.nextElementSibling;

        // If next sibling doesn't have section-content class, try to find it
        if (content && !content.classList.contains('section-content')) {
            // Add the class if it's likely the content container
            if (content.id && (
                content.id.includes('pull_requests') ||
                content.id.includes('issues') ||
                content.id.includes('webhook') ||
                content.id.includes('github') ||
                content.id.includes('messages') ||
                content.id.includes('queues') ||
                content.id.includes('cpanel') ||
                content.id.includes('workflow') ||
                content.id.includes('branches') ||
                content.id.includes('feed') ||
                content.id.includes('repositories') ||
                content.id.includes('api_usage') ||
                content.id.includes('wireguard') ||
                content.id.includes('healthchecksio') ||
                content.id.includes('uptimerobot') ||
                content.id.includes('domains') ||
                content.id.includes('triage') ||
                content.id.includes('assigned') ||
                content.id.includes('wip') ||
                content.id.includes('bug') ||
                content.id.includes('cronjobs') ||
                content.id.includes('senders') ||
                content.id.includes('appveyor') ||
                content.tagName === 'DIV'
            )) {
                content.classList.add('section-content');
            }
        }

        return content;
    }

    /**
     * Toggles the collapsed or expanded state of a section.
     *
     * This function checks if the section has an ID and logs a warning if it does not.
     * It determines whether to collapse or expand the section based on either a forced state
     * or by toggling its current state using a state management system. Visual changes are applied
     * to the header, content, and parent container, and the state is updated if a forceState was provided.
     * A custom event 'sectionToggled' is dispatched with details about the section's new state.
     *
     * @param {HTMLElement} header - The header element of the section to toggle.
     * @param {HTMLElement} content - The content element of the section to toggle.
     * @param {boolean|null} forceState - An optional boolean to force the section to be collapsed or expanded.
     */
    toggleSection(header, content, forceState = null) {
        const sectionId = content.id;
        if (!sectionId) {
            console.warn("CollapsibleSectionsManager: Cannot toggle section without ID");
            return;
        }

        const isCurrentlyCollapsed = header.classList.contains('collapsed');
        let shouldCollapse;

        if (forceState !== null) {
            shouldCollapse = Boolean(forceState);
        } else {
            // Use state management to determine new state
            shouldCollapse = this.state.toggleSection(sectionId);
        }

        // Apply visual changes
        if (shouldCollapse) {
            // Collapse
            header.classList.add('collapsed');
            content.classList.add('collapsed');
            header.setAttribute('aria-expanded', 'false');

            // Add collapsed class to parent container for styling
            const parentContainer = header.closest('.full-width-section, .data-item');
            if (parentContainer) {
                parentContainer.classList.add('collapsed');
            }
        } else {
            // Expand
            header.classList.remove('collapsed');
            content.classList.remove('collapsed');
            header.setAttribute('aria-expanded', 'true');

            // Remove collapsed class from parent container
            const parentContainer = header.closest('.full-width-section, .data-item');
            if (parentContainer) {
                parentContainer.classList.remove('collapsed');
            }
        }

        // Update state if forceState was used
        if (forceState !== null) {
            this.state.setSectionCollapsed(sectionId, shouldCollapse);
        }

        // Trigger custom event for other parts of the application to listen to
        const customEvent = new CustomEvent('sectionToggled', {
            detail: {
                header: header,
                content: content,
                collapsed: shouldCollapse,
                sectionId: sectionId
            }
        });
        document.dispatchEvent(customEvent);

        console.log(`CollapsibleSectionsManager: Section ${sectionId} ${shouldCollapse ? 'collapsed' : 'expanded'}`);
    }

    /**
     * Restores the section state from storage based on the provided header.
     *
     * This function retrieves the content element associated with the given header
     * and checks if it was previously collapsed. If so, it schedules a toggle
     * operation using setTimeout to ensure smooth animation during page load.
     *
     * @param {HTMLElement} header - The header element whose section state needs to be restored.
     */
    restoreState(header) {
        const content = this.getContentElement(header);
        if (!content || !content.id) return;

        const wasCollapsed = this.state.isSectionCollapsed(content.id);
        if (wasCollapsed) {
            // Use setTimeout to ensure smooth animation on page load
            setTimeout(() => {
                this.toggleSection(header, content, true);
            }, 100);
        }
    }

    // Public API methods for external use

    /**
     * Toggles a specific section by its content ID.
     */
    toggleSectionById(sectionId, forceState = null) {
        const sectionData = this.sections.get(sectionId);
        if (!sectionData) {
            console.warn(`CollapsibleSectionsManager: Section with ID '${sectionId}' not found`);
            return false;
        }

        this.toggleSection(sectionData.header, sectionData.content, forceState);
        return true;
    }

    /**
     * Collapses all sections by applying visual changes and updating state.
     */
    collapseAll() {
        const sectionIds = Array.from(this.sections.keys());
        this.state.collapseAll(sectionIds);

        // Apply visual changes to all sections
        this.sections.forEach((sectionData) => {
            if (!sectionData.header.classList.contains('collapsed')) {
                this.toggleSection(sectionData.header, sectionData.content, true);
            }
        });

        console.log('CollapsibleSectionsManager: All sections collapsed');
    }

    /**
     * Expands all collapsible sections and applies visual changes.
     */
    expandAll() {
        this.state.expandAll();

        // Apply visual changes to all sections
        this.sections.forEach((sectionData) => {
            if (sectionData.header.classList.contains('collapsed')) {
                this.toggleSection(sectionData.header, sectionData.content, false);
            }
        });

        console.log('CollapsibleSectionsManager: All sections expanded');
    }

    /**
     * Retrieves the current state of all sections.
     */
    getSectionStates() {
        const allSectionIds = Array.from(this.sections.keys());
        return this.state.getStats(allSectionIds);
    }

    /**
     * Retrieves stats on collapsed vs expanded sections.
     */
    getSectionStats() {
        const allSectionIds = Array.from(this.sections.keys());
        return this.state.getStats(allSectionIds);
    }

    /**
     * Determines if a specific section is collapsed.
     */
    isSectionCollapsed(sectionId) {
        return this.state.isSectionCollapsed(sectionId);
    }

    /**
     * Retrieves the state management instance.
     */
    getStateManager() {
        return this.state;
    }

    /**
     * Reinitializes sections by clearing them and re-running initialization.
     */
    reinitialize() {
        console.log('CollapsibleSectionsManager: Reinitializing...');
        this.isInitialized = false;
        this.sections.clear();
        this.init();
    }

    /**
     * Clears all stored states and resets sections to an expanded state.
     */
    resetAllStates() {
        this.state.clearAll();

        // Apply visual reset to all sections
        this.sections.forEach((sectionData) => {
            if (sectionData.header.classList.contains('collapsed')) {
                this.toggleSection(sectionData.header, sectionData.content, false);
            }
        });

        console.log('CollapsibleSectionsManager: All states reset to expanded');
    }

    /**
     * Exports the current state for debugging purposes.
     */
    exportState() {
        return this.state.exportState();
    }

    /**
     * Imports state and re-applies visual states if necessary.
     */
    importState(stateData) {
        const success = this.state.importState(stateData);
        if (success) {
            // Reapply visual states after import
            this.sections.forEach((sectionData, sectionId) => {
                const shouldBeCollapsed = this.state.isSectionCollapsed(sectionId);
                const isCurrentlyCollapsed = sectionData.header.classList.contains('collapsed');

                if (shouldBeCollapsed !== isCurrentlyCollapsed) {
                    this.toggleSection(sectionData.header, sectionData.content, shouldBeCollapsed);
                }
            });
        }
        return success;
    }

    /**
     * Cleans up resources and resets state.
     */
    destroy() {
        this.sections.forEach((sectionData) => {
            // Remove event listeners would go here if we stored references
            // For now, just clear the sections map
        });
        this.sections.clear();
        this.state = null;
        this.isInitialized = false;
        console.log('CollapsibleSectionsManager: Destroyed');
    }
}
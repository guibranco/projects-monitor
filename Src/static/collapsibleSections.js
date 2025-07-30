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
     * Initialize collapsible sections functionality
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
     * Set up all collapsible sections on the page
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
     * Set up accessibility attributes
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
     * Handle header click events
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
     * Find the content element associated with a header
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
     * Toggle section collapsed/expanded state
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
     * Restore section state from storage
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
     * Toggle a specific section by its content ID
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
     * Collapse all sections
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
     * Expand all sections
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
     * Get current state of all sections
     */
    getSectionStates() {
        const allSectionIds = Array.from(this.sections.keys());
        return this.state.getStats(allSectionIds);
    }

    /**
     * Get count of collapsed vs expanded sections
     */
    getSectionStats() {
        const allSectionIds = Array.from(this.sections.keys());
        return this.state.getStats(allSectionIds);
    }

    /**
     * Check if a specific section is collapsed
     */
    isSectionCollapsed(sectionId) {
        return this.state.isSectionCollapsed(sectionId);
    }

    /**
     * Get the state management instance for advanced operations
     */
    getStateManager() {
        return this.state;
    }

    /**
     * Reinitialize sections (useful when new sections are added dynamically)
     */
    reinitialize() {
        console.log('CollapsibleSectionsManager: Reinitializing...');
        this.isInitialized = false;
        this.sections.clear();
        this.init();
    }

    /**
     * Clear all stored states and reset to expanded
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
     * Export current state for debugging
     */
    exportState() {
        return this.state.exportState();
    }

    /**
     * Import state for debugging or restoration
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
     * Clean up event listeners (for cleanup/disposal)
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

/**
 * =============================================================================
 * HEADSTART GLOBAL UTILITIES
 * =============================================================================
 * PURPOSE: Shared utility functions for all HeadStart JS engines.
 * =============================================================================
 */
const HeadStartUtils = {
    /**
     * Checks if the script is currently running inside the Divi Visual Builder
     * @returns {boolean}
     */
    isDiviBuilder: function() {
        return document.body.classList.contains('et-fb') || 
               window.location.search.includes('et_fb=1') ||
               window.location.href.includes('et_fb=1');
    },

    /**
     * Debounces a function to prevent it from firing too rapidly
     * @param {function} func - The function to debounce
     * @param {number} wait - The delay in milliseconds
     * @returns {function}
     */
    debounce: function(func, wait) {
        let timeout;
        return function(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

/**
 * Safe Event Listeners Helper
 * Prevents errors when DOM elements don't exist
 */

function safeAddEventListener(elementId, event, callback) {
    const element = document.getElementById(elementId);
    if (element) {
        element.addEventListener(event, callback);
    } else {
        console.warn(`Element with ID '${elementId}' not found - event listener not added`);
    }
}

function safeQuerySelectorAddEventListener(selector, event, callback) {
    const element = document.querySelector(selector);
    if (element) {
        element.addEventListener(event, callback);
    } else {
        console.warn(`Element '${selector}' not found - event listener not added`);
    }
}

function safeQuerySelectorAllAddEventListener(selector, event, callback) {
    const elements = document.querySelectorAll(selector);
    if (elements.length > 0) {
        elements.forEach(element => {
            element.addEventListener(event, callback);
        });
    } else {
        console.warn(`No elements found for '${selector}' - event listeners not added`);
    }
}

// Export for use in other scripts
if (typeof window !== 'undefined') {
    window.safeAddEventListener = safeAddEventListener;
    window.safeQuerySelectorAddEventListener = safeQuerySelectorAddEventListener;
    window.safeQuerySelectorAllAddEventListener = safeQuerySelectorAllAddEventListener;
}

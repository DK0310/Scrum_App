/**
 * Header Module - DriveNow
 * Handles: Navigation state, active page highlighting, side menu management
 */

// Initialize header on page load
document.addEventListener('DOMContentLoaded', () => {
    initializeHeader();
});

function initializeHeader() {
    // No specific initialization needed yet - navbar.js handles scroll detection
    // and toggle functionality
    console.log('✅ Header initialized');
}

// Export for external use
window.headerModule = {
    initialized: true
};

/**
 * Toast Notification System
 *
 * Provides a simple toast notification system using Alpine.js.
 * Toasts auto-dismiss after 5 seconds and can be manually dismissed.
 *
 * Usage:
 *   window.showToast('Message', 'success');
 *   window.showToast('Error occurred', 'error');
 *   window.showToast('Warning', 'warning');
 *   window.showToast('Info', 'info');
 */

document.addEventListener('alpine:init', () => {
    Alpine.store('toasts', {
        items: [],
        counter: 0,

        /**
         * Show a toast notification
         * @param {string} message - The message to display
         * @param {string} type - The type: 'success', 'error', 'warning', 'info'
         */
        show(message, type = 'info') {
            const id = ++this.counter;
            this.items.push({ id, message, type, show: true });

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                this.dismiss(id);
            }, 5000);
        },

        /**
         * Dismiss a toast by ID
         * @param {number} id - The toast ID
         */
        dismiss(id) {
            const index = this.items.findIndex(item => item.id === id);
            if (index !== -1) {
                this.items[index].show = false;
                // Remove from array after animation
                setTimeout(() => {
                    this.items = this.items.filter(item => item.id !== id);
                }, 300);
            }
        }
    });
});

// Global helper function for showing toasts
window.showToast = function(message, type = 'info') {
    if (typeof Alpine !== 'undefined' && Alpine.store('toasts')) {
        Alpine.store('toasts').show(message, type);
    } else {
        // Fallback if Alpine isn't ready
        console.log(`[${type.toUpperCase()}] ${message}`);
    }
};

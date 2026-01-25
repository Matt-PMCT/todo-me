/**
 * Toast Notification System
 *
 * Provides a simple toast notification system using Alpine.js.
 * Toasts auto-dismiss after 5 seconds and can be manually dismissed.
 *
 * Usage:
 *   window.showToast('Message', 'success');
 *   window.showToast('Error occurred', 'error');
 *   window.showToastWithUndo('Task completed', 'success', 'undo_abc123', '/api/v1/undo');
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
            this.items.push({ id, message, type, show: true, undoToken: null, countdown: null });

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                this.dismiss(id);
            }, 5000);
        },

        /**
         * Show a toast with undo button and countdown timer
         * @param {string} message - The message to display
         * @param {string} type - The type: 'success', 'error', 'warning', 'info'
         * @param {string} undoToken - The undo token for the API call
         * @param {string} undoUrl - The undo API endpoint (default: '/api/v1/undo')
         * @param {number} duration - Duration in ms before auto-dismiss (default: 5000)
         */
        showWithUndo(message, type, undoToken, undoUrl = '/api/v1/undo', duration = 5000) {
            const id = ++this.counter;
            const countdown = Math.ceil(duration / 1000);

            this.items.push({
                id,
                message,
                type,
                show: true,
                undoToken,
                undoUrl,
                countdown,
                undoing: false,
                undone: false
            });

            // Start countdown
            const countdownInterval = setInterval(() => {
                const item = this.items.find(i => i.id === id);
                if (item && item.countdown > 0) {
                    item.countdown--;
                } else {
                    clearInterval(countdownInterval);
                }
            }, 1000);

            // Auto-dismiss after duration
            setTimeout(() => {
                clearInterval(countdownInterval);
                this.dismiss(id);
            }, duration);
        },

        /**
         * Execute undo action for a toast
         * @param {number} id - The toast ID
         */
        async undo(id) {
            const item = this.items.find(i => i.id === id);
            if (!item || !item.undoToken || item.undoing || item.undone) return;

            item.undoing = true;

            try {
                const response = await fetch(item.undoUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ token: item.undoToken })
                });

                if (response.ok) {
                    item.undone = true;
                    item.message = 'Undone!';
                    // Reload page after brief delay to show success message
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    const data = await response.json();
                    item.message = data.error?.message || 'Undo failed';
                    item.type = 'error';
                    setTimeout(() => this.dismiss(id), 3000);
                }
            } catch (error) {
                item.message = 'Undo failed';
                item.type = 'error';
                setTimeout(() => this.dismiss(id), 3000);
            } finally {
                item.undoing = false;
            }
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

window.showToastWithUndo = function(message, type, undoToken, undoUrl = '/api/v1/undo', duration = 5000) {
    if (typeof Alpine !== 'undefined' && Alpine.store('toasts')) {
        Alpine.store('toasts').showWithUndo(message, type, undoToken, undoUrl, duration);
    } else {
        console.log(`[${type.toUpperCase()}] ${message} (undo: ${undoToken})`);
    }
};

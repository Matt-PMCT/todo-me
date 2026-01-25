/**
 * Notifications JavaScript Module
 * Handles notification polling and display
 */

class NotificationManager {
    constructor(options = {}) {
        this.pollInterval = options.pollInterval || 60000; // 60 seconds default
        this.onUnreadCountChange = options.onUnreadCountChange || null;
        this.pollingTimer = null;
        this.unreadCount = 0;
    }

    /**
     * Start polling for unread notifications
     */
    startPolling() {
        if (this.pollingTimer) {
            return;
        }

        // Initial fetch
        this.fetchUnreadCount();

        // Set up interval
        this.pollingTimer = setInterval(() => {
            this.fetchUnreadCount();
        }, this.pollInterval);

        console.log('[Notifications] Polling started');
    }

    /**
     * Stop polling
     */
    stopPolling() {
        if (this.pollingTimer) {
            clearInterval(this.pollingTimer);
            this.pollingTimer = null;
            console.log('[Notifications] Polling stopped');
        }
    }

    /**
     * Fetch unread notification count
     */
    async fetchUnreadCount() {
        try {
            const response = await fetch('/api/v1/notifications/unread-count', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            const newCount = data.data?.count || 0;

            if (newCount !== this.unreadCount) {
                this.unreadCount = newCount;
                if (this.onUnreadCountChange) {
                    this.onUnreadCountChange(newCount);
                }
            }

            return newCount;
        } catch (error) {
            console.error('[Notifications] Failed to fetch unread count:', error);
            return this.unreadCount;
        }
    }

    /**
     * Fetch notifications list
     */
    async fetchNotifications(options = {}) {
        const params = new URLSearchParams({
            limit: options.limit || 50,
            offset: options.offset || 0,
        });

        if (options.unreadOnly) {
            params.set('unreadOnly', 'true');
        }

        try {
            const response = await fetch(`/api/v1/notifications?${params}`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            return data.data?.notifications || [];
        } catch (error) {
            console.error('[Notifications] Failed to fetch notifications:', error);
            return [];
        }
    }

    /**
     * Mark a notification as read
     */
    async markAsRead(notificationId) {
        try {
            const response = await fetch(`/api/v1/notifications/${notificationId}/read`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // Update local count
            this.unreadCount = Math.max(0, this.unreadCount - 1);
            if (this.onUnreadCountChange) {
                this.onUnreadCountChange(this.unreadCount);
            }

            return true;
        } catch (error) {
            console.error('[Notifications] Failed to mark as read:', error);
            return false;
        }
    }

    /**
     * Mark all notifications as read
     */
    async markAllAsRead() {
        try {
            const response = await fetch('/api/v1/notifications/read-all', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // Update local count
            this.unreadCount = 0;
            if (this.onUnreadCountChange) {
                this.onUnreadCountChange(0);
            }

            const data = await response.json();
            return data.data?.markedAsRead || 0;
        } catch (error) {
            console.error('[Notifications] Failed to mark all as read:', error);
            return 0;
        }
    }

    /**
     * Format relative time
     */
    static formatRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;

        return date.toLocaleDateString();
    }

    /**
     * Get notification icon based on type
     */
    static getNotificationIcon(type) {
        const icons = {
            task_due_soon: 'clock',
            task_overdue: 'exclamation-triangle',
            task_due_today: 'calendar',
            recurring_created: 'refresh',
            system: 'info-circle'
        };
        return icons[type] || 'bell';
    }

    /**
     * Get notification color based on type
     */
    static getNotificationColor(type) {
        const colors = {
            task_due_soon: 'yellow',
            task_overdue: 'red',
            task_due_today: 'blue',
            recurring_created: 'green',
            system: 'gray'
        };
        return colors[type] || 'gray';
    }
}

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationManager;
}

// Create global instance
window.NotificationManager = NotificationManager;

/**
 * Push Notifications Client
 * Handles service worker registration and push subscription management
 */

class PushNotifications {
    constructor() {
        this.swRegistration = null;
        this.isSubscribed = false;
    }

    /**
     * Check if push notifications are supported
     */
    isSupported() {
        return 'serviceWorker' in navigator && 'PushManager' in window;
    }

    /**
     * Initialize push notifications
     */
    async init() {
        if (!this.isSupported()) {
            console.log('[Push] Push notifications not supported');
            return false;
        }

        try {
            // Register service worker
            this.swRegistration = await navigator.serviceWorker.register(window.apiUrl('/sw.js'));
            console.log('[Push] Service worker registered');

            // Check current subscription status
            const subscription = await this.swRegistration.pushManager.getSubscription();
            this.isSubscribed = subscription !== null;

            console.log('[Push] User is ' + (this.isSubscribed ? '' : 'not ') + 'subscribed');

            return true;
        } catch (error) {
            console.error('[Push] Service worker registration failed:', error);
            return false;
        }
    }

    /**
     * Get the VAPID public key from the server
     */
    async getVapidKey() {
        try {
            const response = await fetch(window.apiUrl('/api/v1/push/vapid-key'), {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Failed to get VAPID key');
            }

            const data = await response.json();
            return data.data?.publicKey;
        } catch (error) {
            console.error('[Push] Failed to get VAPID key:', error);
            return null;
        }
    }

    /**
     * Convert VAPID key to Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    /**
     * Subscribe to push notifications
     */
    async subscribe() {
        if (!this.swRegistration) {
            console.error('[Push] Service worker not registered');
            return false;
        }

        try {
            // Get VAPID key
            const vapidKey = await this.getVapidKey();
            if (!vapidKey) {
                console.error('[Push] VAPID key not available');
                return false;
            }

            // Request permission
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                console.log('[Push] Notification permission denied');
                return false;
            }

            // Subscribe to push
            const subscription = await this.swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(vapidKey)
            });

            console.log('[Push] User subscribed:', subscription);

            // Send subscription to server
            const response = await fetch(window.apiUrl('/api/v1/push/subscribe'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(subscription.toJSON())
            });

            if (!response.ok) {
                throw new Error('Failed to save subscription on server');
            }

            this.isSubscribed = true;
            console.log('[Push] Subscription saved on server');

            return true;
        } catch (error) {
            console.error('[Push] Failed to subscribe:', error);
            return false;
        }
    }

    /**
     * Unsubscribe from push notifications
     */
    async unsubscribe() {
        if (!this.swRegistration) {
            console.error('[Push] Service worker not registered');
            return false;
        }

        try {
            const subscription = await this.swRegistration.pushManager.getSubscription();

            if (!subscription) {
                console.log('[Push] No subscription to unsubscribe');
                this.isSubscribed = false;
                return true;
            }

            // Remove from server first
            await fetch(window.apiUrl('/api/v1/push/unsubscribe'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ endpoint: subscription.endpoint })
            });

            // Then unsubscribe locally
            await subscription.unsubscribe();

            this.isSubscribed = false;
            console.log('[Push] User unsubscribed');

            return true;
        } catch (error) {
            console.error('[Push] Failed to unsubscribe:', error);
            return false;
        }
    }

    /**
     * Check if user is subscribed
     */
    async checkSubscription() {
        if (!this.swRegistration) {
            return false;
        }

        const subscription = await this.swRegistration.pushManager.getSubscription();
        this.isSubscribed = subscription !== null;
        return this.isSubscribed;
    }

    /**
     * Get current notification permission status
     */
    getPermissionStatus() {
        if (!this.isSupported()) {
            return 'unsupported';
        }
        return Notification.permission;
    }
}

// Create global instance
window.pushNotifications = new PushNotifications();

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.pushNotifications.init();
});

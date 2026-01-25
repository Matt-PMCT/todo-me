// Service Worker for Push Notifications
// This file must be served from the root of the domain

const CACHE_NAME = 'todo-app-v1';

// Install event - cache essential assets
self.addEventListener('install', (event) => {
    console.log('[SW] Installing service worker');
    self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating service worker');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => caches.delete(name))
            );
        })
    );
    self.clients.claim();
});

// Push event - handle incoming push notifications
self.addEventListener('push', (event) => {
    console.log('[SW] Push notification received');

    let data = {
        title: 'Todo App',
        body: 'You have a new notification',
        data: {}
    };

    try {
        if (event.data) {
            const payload = event.data.json();
            data = {
                title: payload.title || data.title,
                body: payload.body || data.body,
                data: payload.data || {}
            };
        }
    } catch (e) {
        console.error('[SW] Error parsing push data:', e);
    }

    const options = {
        body: data.body,
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        tag: data.data.notificationId || 'default',
        renotify: true,
        requireInteraction: data.data.type === 'task_overdue',
        data: data.data,
        actions: [
            {
                action: 'view',
                title: 'View'
            },
            {
                action: 'dismiss',
                title: 'Dismiss'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification click event - handle user interaction
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event.action);

    event.notification.close();

    if (event.action === 'dismiss') {
        return;
    }

    // Default action or 'view' action
    const data = event.notification.data || {};
    let url = '/tasks';

    if (data.taskId) {
        url = `/tasks#task-${data.taskId}`;
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Check if there's already a window open
            for (const client of clientList) {
                if (client.url.includes('/tasks') && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            // Open a new window if none found
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});

// Notification close event
self.addEventListener('notificationclose', (event) => {
    console.log('[SW] Notification closed');
});

// Background sync for offline notifications (future enhancement)
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-notifications') {
        console.log('[SW] Background sync triggered');
    }
});

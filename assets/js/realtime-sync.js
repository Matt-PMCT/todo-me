/**
 * Real-time sync via polling.
 *
 * Polls the server for changes made by other tabs/devices and applies
 * DOM updates. Uses the Visibility API to pause polling on hidden tabs.
 */
document.addEventListener('alpine:init', () => {
    Alpine.store('sync', {
        version: 0,
        interval: 30,
        polling: false,
        pollTimer: null,
        consecutiveFailures: 0,
        baseInterval: 30,

        init() {
            const body = document.body;
            const isAuthenticated = body.dataset.userAuthenticated === '1';

            if (!isAuthenticated) return;

            this.version = parseInt(body.dataset.syncVersion || '0', 10);
            this.interval = parseInt(body.dataset.syncInterval || '30', 10);
            this.baseInterval = this.interval;

            if (this.interval <= 0) return;

            this.startPolling();

            // Pause/resume on visibility change
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.stopPolling();
                } else {
                    this.consecutiveFailures = 0;
                    this.interval = this.baseInterval;
                    this.poll(); // Immediate poll on resume
                    this.startPolling();
                }
            });
        },

        startPolling() {
            if (this.polling || this.interval <= 0) return;
            this.polling = true;
            this.schedulePoll();
        },

        stopPolling() {
            this.polling = false;
            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
        },

        schedulePoll() {
            if (!this.polling) return;
            this.pollTimer = setTimeout(() => {
                this.poll().then(() => {
                    if (this.polling) this.schedulePoll();
                });
            }, this.interval * 1000);
        },

        async poll() {
            try {
                const response = await window.api.get(
                    `/api/v1/sync/poll?lastVersion=${this.version}`
                );

                if (!response.ok) {
                    this.handleFailure();
                    return;
                }

                const json = await response.json();
                const data = json.data;

                if (!data) return;

                // Reset backoff on success
                this.consecutiveFailures = 0;
                this.interval = this.baseInterval;

                if (data.version < this.version) {
                    // Server version reset (Redis restart). Reset and reload.
                    this.version = data.version;
                    this.debouncedReload();
                    return;
                }

                if (data.version > this.version) {
                    this.version = data.version;
                    this.processChanges(data.changes || []);
                }
            } catch (e) {
                this.handleFailure();
            }
        },

        handleFailure() {
            this.consecutiveFailures++;
            // Exponential backoff: double interval, max 120s
            this.interval = Math.min(this.baseInterval * Math.pow(2, this.consecutiveFailures), 120);

            if (this.consecutiveFailures >= 3) {
                window.showToast?.('Sync connection issue. Retrying...', 'warning');
            }
        },

        processChanges(changes) {
            for (const change of changes) {
                const { entityType, action, entityId } = change;
                const eventName = `${entityType}-sync`;

                // Dispatch custom event for specific handlers
                window.dispatchEvent(new CustomEvent(eventName, {
                    detail: { action, entityId, change }
                }));

                // Handle task changes directly
                if (entityType === 'task') {
                    this.handleTaskChange(action, entityId);
                }
            }
        },

        handleTaskChange(action, entityId) {
            if (action === 'deleted') {
                const el = document.querySelector(`[data-task-id="${CSS.escape(entityId)}"]`);
                if (el) {
                    el.style.transition = 'opacity 0.3s, transform 0.3s';
                    el.style.opacity = '0';
                    el.style.transform = 'translateX(20px)';
                    setTimeout(() => el.remove(), 300);
                }
            } else if (action === 'created' || action === 'updated') {
                // Reload the page for simplicity; a more advanced approach
                // would fetch and patch individual task HTML
                this.debouncedReload();
            }
        },

        _reloadTimer: null,
        debouncedReload() {
            // Debounce reloads: wait 500ms for batch changes to settle
            if (this._reloadTimer) clearTimeout(this._reloadTimer);
            this._reloadTimer = setTimeout(() => {
                window.location.reload();
            }, 500);
        }
    });
});

/**
 * Subtasks Module
 *
 * Manages subtask expansion state and AJAX interactions using Alpine.js.
 *
 * Features:
 * - Toggle expandable subtask lists
 * - Track expanded state per task
 * - Handle subtask status changes via AJAX
 */

/**
 * Initialize subtasks Alpine.js store
 */
document.addEventListener('alpine:init', () => {
    // Store for tracking which tasks have expanded subtask lists
    Alpine.store('subtasks', {
        expanded: {},

        toggle(taskId) {
            this.expanded[taskId] = !this.expanded[taskId];
        },

        isExpanded(taskId) {
            return this.expanded[taskId] ?? false;
        },

        collapse(taskId) {
            this.expanded[taskId] = false;
        },

        collapseAll() {
            this.expanded = {};
        }
    });

    // Global listener for toggle events
    window.addEventListener('toggle-subtasks', (event) => {
        const taskId = event.detail?.taskId;
        if (taskId) {
            Alpine.store('subtasks').toggle(taskId);
        }
    });
});

/**
 * Alpine.js component for subtask list management
 */
export function subtaskComponent(taskId) {
    return {
        taskId,
        loading: false,
        subtasks: [],
        newSubtaskTitle: '',

        get isExpanded() {
            return Alpine.store('subtasks').isExpanded(this.taskId);
        },

        toggle() {
            Alpine.store('subtasks').toggle(this.taskId);
        },

        async loadSubtasks() {
            if (this.subtasks.length > 0 || this.loading) return;

            this.loading = true;
            try {
                const response = await fetch(window.apiUrl(`/api/v1/tasks/${this.taskId}/subtasks`));
                const data = await response.json();
                if (data.success && data.data?.subtasks) {
                    this.subtasks = data.data.subtasks;
                }
            } catch (error) {
                console.error('Failed to load subtasks:', error);
            } finally {
                this.loading = false;
            }
        },

        async toggleSubtaskStatus(subtaskId, currentStatus) {
            const newStatus = currentStatus === 'completed' ? 'pending' : 'completed';
            try {
                const response = await fetch(window.apiUrl(`/api/v1/tasks/${subtaskId}/status`), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ status: newStatus })
                });
                const data = await response.json();
                if (data.success) {
                    // Update local state
                    const subtask = this.subtasks.find(s => s.id === subtaskId);
                    if (subtask) {
                        subtask.status = newStatus;
                    }
                }
            } catch (error) {
                console.error('Failed to update subtask status:', error);
            }
        },

        async createSubtask() {
            if (!this.newSubtaskTitle.trim()) return;

            try {
                const response = await fetch(window.apiUrl(`/api/v1/tasks/${this.taskId}/subtasks`), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ title: this.newSubtaskTitle })
                });
                const data = await response.json();
                if (data.success && data.data?.task) {
                    this.subtasks.push(data.data.task);
                    this.newSubtaskTitle = '';
                }
            } catch (error) {
                console.error('Failed to create subtask:', error);
            }
        }
    };
}

// Make component available globally for Alpine.js
window.subtaskComponent = subtaskComponent;

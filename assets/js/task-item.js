/**
 * Shared taskItem() Alpine.js component for inline task editing.
 * Used by _task_item.html.twig across all view pages (list, today, upcoming, etc.).
 */
document.addEventListener('alpine:init', () => {
    window.taskItem = function (taskId, initialStatus, initialPriority, initialDueDate, initialDueTime, initialProjectId, initialProjectName, initialProjectColor) {
        return {
            taskId: taskId,
            status: initialStatus,
            isUpdating: false,
            showDeleteModal: false,
            actionsMenuOpen: false,

            priority: initialPriority,
            dueDate: initialDueDate || '',
            dueTime: initialDueTime || '',
            projectId: initialProjectId || '',
            projectName: initialProjectName || '',
            projectColor: initialProjectColor || '',
            statusDropdownOpen: false,
            priorityDropdownOpen: false,
            dueDateDropdownOpen: false,
            projectDropdownOpen: false,
            projects: [],
            projectsLoaded: false,

            get subtasksExpanded() {
                return Alpine.store('subtasks')?.expanded || {};
            },

            async toggleStatus() {
                const newStatus = this.status === 'completed' ? 'pending' : 'completed';
                await this.setStatus(newStatus);
            },

            async setStatus(newStatus) {
                if (this.isUpdating || this.status === newStatus) return;

                this.isUpdating = true;

                try {
                    const response = await window.api.patch('/api/v1/tasks/' + this.taskId, {status: newStatus});
                    const data = await response.json();

                    if (data.success) {
                        this.status = newStatus;
                    } else {
                        console.error('Failed to update task status:', data.error);
                    }
                } catch (error) {
                    console.error('Error updating task status:', error);
                } finally {
                    this.isUpdating = false;
                }
            },

            async setPriority(newPriority) {
                if (this.isUpdating || this.priority === newPriority) {
                    this.priorityDropdownOpen = false;
                    return;
                }

                this.isUpdating = true;

                try {
                    const response = await window.api.patch('/api/v1/tasks/' + this.taskId, {priority: newPriority});
                    const data = await response.json();

                    if (data.success) {
                        this.priority = newPriority;
                    } else {
                        console.error('Failed to update priority:', data.error);
                    }
                } catch (error) {
                    console.error('Error updating priority:', error);
                } finally {
                    this.isUpdating = false;
                    this.priorityDropdownOpen = false;
                }
            },

            async setDueDate(newDate, newTime) {
                if (this.isUpdating) return;

                this.isUpdating = true;

                try {
                    const payload = {};
                    if (newDate === null) {
                        payload.clearDueDate = true;
                        payload.clearDueTime = true;
                    } else {
                        payload.dueDate = newDate;
                        if (newTime) {
                            payload.dueTime = newTime;
                        } else if (newTime === null) {
                            payload.clearDueTime = true;
                        }
                    }

                    const response = await window.api.patch('/api/v1/tasks/' + this.taskId, payload);
                    const data = await response.json();

                    if (data.success) {
                        this.dueDate = newDate || '';
                        this.dueTime = (newDate && newTime) ? newTime : '';
                    } else {
                        console.error('Failed to update due date:', data.error);
                    }
                } catch (error) {
                    console.error('Error updating due date:', error);
                } finally {
                    this.isUpdating = false;
                    this.dueDateDropdownOpen = false;
                }
            },

            flattenTree(nodes, depth = 0) {
                const result = [];
                for (const node of (nodes || [])) {
                    result.push({ id: node.id, name: node.name, color: node.color || '', depth });
                    if (node.children && node.children.length > 0) {
                        result.push(...this.flattenTree(node.children, depth + 1));
                    }
                }
                return result;
            },

            async loadProjects() {
                if (this.projectsLoaded) return;

                try {
                    const response = await window.api.get('/api/v1/projects/tree');
                    const data = await response.json();
                    if (data.success && data.data) {
                        this.projects = this.flattenTree(data.data.projects);
                        this.projectsLoaded = true;
                    }
                } catch (error) {
                    console.error('Error loading projects:', error);
                }
            },

            async openProjectDropdown() {
                this.statusDropdownOpen = false;
                this.priorityDropdownOpen = false;
                this.dueDateDropdownOpen = false;
                this.actionsMenuOpen = false;
                this.projectDropdownOpen = true;
                await this.loadProjects();
            },

            async setProject(newProjectId, newProjectName, newProjectColor) {
                if (this.isUpdating) {
                    this.projectDropdownOpen = false;
                    return;
                }

                this.isUpdating = true;

                try {
                    const payload = newProjectId ? {projectId: newProjectId} : {clearProject: true};
                    const response = await window.api.patch('/api/v1/tasks/' + this.taskId, payload);
                    const data = await response.json();

                    if (data.success) {
                        this.projectId = newProjectId || '';
                        this.projectName = newProjectName || '';
                        this.projectColor = newProjectColor || '';
                    } else {
                        console.error('Failed to update project:', data.error);
                    }
                } catch (error) {
                    console.error('Error updating project:', error);
                } finally {
                    this.isUpdating = false;
                    this.projectDropdownOpen = false;
                }
            },

            getQuickDate(option) {
                const today = new Date();
                switch (option) {
                    case 'today':
                        return today.toISOString().split('T')[0];
                    case 'tomorrow':
                        today.setDate(today.getDate() + 1);
                        return today.toISOString().split('T')[0];
                    case 'nextWeek':
                        today.setDate(today.getDate() + 7);
                        return today.toISOString().split('T')[0];
                    default:
                        return '';
                }
            }
        };
    };
});

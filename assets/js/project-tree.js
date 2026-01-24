/**
 * Project Tree Alpine.js Component
 *
 * Handles:
 * - Collapse/expand with localStorage persistence
 * - Auto-collapse beyond depth 3
 * - Keyboard navigation
 * - Tree refresh
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('projectTree', ({ projects, selectedProjectId, showArchived, apiBaseUrl }) => ({
        tree: projects || [],
        selectedProjectId: selectedProjectId || null,
        showArchived: showArchived || false,
        apiBaseUrl: apiBaseUrl || '/api/v1/projects/tree',
        loading: false,
        collapsedIds: [],
        draggedProject: null,
        dropTarget: null,

        init() {
            // Load collapsed state from localStorage
            this.loadCollapsedState();

            // Auto-collapse deep nodes on initial load
            this.autoCollapseDeepNodes(this.tree);

            // Setup keyboard navigation
            this.setupKeyboardNavigation();
        },

        /**
         * Load collapsed state from localStorage
         */
        loadCollapsedState() {
            const stored = localStorage.getItem('projectTree_collapsed');
            if (stored) {
                try {
                    this.collapsedIds = JSON.parse(stored);
                } catch (e) {
                    this.collapsedIds = [];
                }
            }
        },

        /**
         * Save collapsed state to localStorage
         */
        saveCollapsedState() {
            localStorage.setItem('projectTree_collapsed', JSON.stringify(this.collapsedIds));
        },

        /**
         * Check if a project is collapsed
         */
        isCollapsed(projectId) {
            return this.collapsedIds.includes(projectId);
        },

        /**
         * Toggle collapse state for a project
         */
        toggleCollapse(projectId) {
            const index = this.collapsedIds.indexOf(projectId);
            if (index === -1) {
                this.collapsedIds.push(projectId);
            } else {
                this.collapsedIds.splice(index, 1);
            }
            this.saveCollapsedState();
        },

        /**
         * Auto-collapse nodes beyond depth 3
         */
        autoCollapseDeepNodes(nodes, depth = 0) {
            for (const node of nodes) {
                if (depth >= 3 && node.children && node.children.length > 0) {
                    if (!this.collapsedIds.includes(node.id)) {
                        this.collapsedIds.push(node.id);
                    }
                }
                if (node.children && node.children.length > 0) {
                    this.autoCollapseDeepNodes(node.children, depth + 1);
                }
            }
            this.saveCollapsedState();
        },

        /**
         * Setup keyboard navigation
         */
        setupKeyboardNavigation() {
            document.addEventListener('keydown', (e) => {
                // Only handle if project tree is focused
                if (!document.activeElement?.closest('.project-tree')) return;

                const currentNode = document.activeElement?.closest('.project-tree-node');
                if (!currentNode) return;

                switch (e.key) {
                    case 'ArrowUp':
                        e.preventDefault();
                        this.focusPreviousNode(currentNode);
                        break;
                    case 'ArrowDown':
                        e.preventDefault();
                        this.focusNextNode(currentNode);
                        break;
                    case 'ArrowLeft':
                        e.preventDefault();
                        this.collapseOrFocusParent(currentNode);
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        this.expandOrFocusChild(currentNode);
                        break;
                    case 'Enter':
                        e.preventDefault();
                        currentNode.querySelector('a')?.click();
                        break;
                }
            });
        },

        /**
         * Focus the previous visible node
         */
        focusPreviousNode(currentNode) {
            const allNodes = Array.from(document.querySelectorAll('.project-tree-node:not([hidden])'));
            const currentIndex = allNodes.indexOf(currentNode);
            if (currentIndex > 0) {
                const prevNode = allNodes[currentIndex - 1];
                prevNode.querySelector('a')?.focus();
            }
        },

        /**
         * Focus the next visible node
         */
        focusNextNode(currentNode) {
            const allNodes = Array.from(document.querySelectorAll('.project-tree-node:not([hidden])'));
            const currentIndex = allNodes.indexOf(currentNode);
            if (currentIndex < allNodes.length - 1) {
                const nextNode = allNodes[currentIndex + 1];
                nextNode.querySelector('a')?.focus();
            }
        },

        /**
         * Collapse current node or focus parent
         */
        collapseOrFocusParent(currentNode) {
            const projectId = currentNode.dataset.projectId;
            if (!this.isCollapsed(projectId) && this.hasChildren(projectId)) {
                this.toggleCollapse(projectId);
            } else {
                // Focus parent node
                const depth = parseInt(currentNode.dataset.depth);
                if (depth > 0) {
                    const parentNode = currentNode.parentElement?.closest('.project-tree-node');
                    if (parentNode) {
                        parentNode.querySelector('a')?.focus();
                    }
                }
            }
        },

        /**
         * Expand current node or focus first child
         */
        expandOrFocusChild(currentNode) {
            const projectId = currentNode.dataset.projectId;
            if (this.isCollapsed(projectId) && this.hasChildren(projectId)) {
                this.toggleCollapse(projectId);
            } else {
                // Focus first child
                const childNode = currentNode.querySelector('.project-tree-node');
                if (childNode) {
                    childNode.querySelector('a')?.focus();
                }
            }
        },

        /**
         * Check if a project has children
         */
        hasChildren(projectId) {
            const findNode = (nodes, id) => {
                for (const node of nodes) {
                    if (node.id === id) return node;
                    if (node.children) {
                        const found = findNode(node.children, id);
                        if (found) return found;
                    }
                }
                return null;
            };
            const node = findNode(this.tree, projectId);
            return node && node.children && node.children.length > 0;
        },

        /**
         * Refresh the tree from the API
         */
        async refreshTree() {
            this.loading = true;
            try {
                const url = new URL(this.apiBaseUrl, window.location.origin);
                url.searchParams.set('include_archived', this.showArchived);
                url.searchParams.set('include_task_counts', 'true');

                const response = await fetch(url.toString());
                if (response.ok) {
                    const data = await response.json();
                    this.tree = data.data?.projects || [];
                }
            } catch (error) {
                console.error('Failed to refresh project tree:', error);
            } finally {
                this.loading = false;
            }
        },

        /**
         * Add a subproject
         */
        addSubproject(parentId) {
            // Redirect to create project with parent pre-filled
            window.location.href = `/projects/new?parent=${parentId}`;
        },

        /**
         * Archive a project
         */
        async archiveProject(projectId) {
            try {
                const response = await fetch(`/api/v1/projects/${projectId}/archive`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' }
                });

                if (response.ok) {
                    await this.refreshTree();
                    window.showToast('Project archived successfully', 'success');
                } else {
                    const data = await response.json();
                    window.showToast(data.error?.message || 'Failed to archive project', 'error');
                }
            } catch (error) {
                console.error('Failed to archive project:', error);
                window.showToast('Failed to archive project', 'error');
            }
        },

        /**
         * Unarchive a project
         */
        async unarchiveProject(projectId) {
            try {
                const response = await fetch(`/api/v1/projects/${projectId}/unarchive`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' }
                });

                if (response.ok) {
                    await this.refreshTree();
                    window.showToast('Project unarchived successfully', 'success');
                } else {
                    const data = await response.json();
                    window.showToast(data.error?.message || 'Failed to unarchive project', 'error');
                }
            } catch (error) {
                console.error('Failed to unarchive project:', error);
                window.showToast('Failed to unarchive project', 'error');
            }
        },

        // Drag and drop handlers are in drag-drop.js
        handleDragStart(event, project) {
            if (window.projectDragDrop) {
                window.projectDragDrop.handleDragStart(event, project, this);
            }
        },

        handleDragOver(event, project) {
            if (window.projectDragDrop) {
                window.projectDragDrop.handleDragOver(event, project, this);
            }
        },

        handleDrop(event, project) {
            if (window.projectDragDrop) {
                window.projectDragDrop.handleDrop(event, project, this);
            }
        },

        handleDragEnd(event) {
            if (window.projectDragDrop) {
                window.projectDragDrop.handleDragEnd(event, this);
            }
        }
    }));
});

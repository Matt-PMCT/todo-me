/**
 * Project Drag and Drop Handler
 *
 * Handles:
 * - HTML5 drag-drop for reordering projects
 * - Visual drop indicators
 * - API calls to persist reordering
 */

window.projectDragDrop = {
    draggedProject: null,
    dropTarget: null,
    dropPosition: null, // 'before', 'after', 'inside'

    /**
     * Handle drag start
     */
    handleDragStart(event, project, treeComponent) {
        this.draggedProject = project;

        // Set drag data
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', project.id);

        // Add visual feedback
        event.target.classList.add('opacity-50');

        // Store reference to tree component
        this.treeComponent = treeComponent;
    },

    /**
     * Handle drag over
     */
    handleDragOver(event, targetProject, treeComponent) {
        event.preventDefault();

        // Don't allow dropping on self or descendants
        if (this.draggedProject && this.isDescendant(this.draggedProject, targetProject)) {
            event.dataTransfer.dropEffect = 'none';
            return;
        }

        event.dataTransfer.dropEffect = 'move';

        // Determine drop position based on mouse position
        const rect = event.currentTarget.getBoundingClientRect();
        const y = event.clientY - rect.top;
        const height = rect.height;

        // Remove previous indicators
        this.clearDropIndicators();

        // Top third = before, middle third = inside, bottom third = after
        if (y < height * 0.25) {
            this.dropPosition = 'before';
            event.currentTarget.classList.add('border-t-2', 'border-indigo-500');
        } else if (y > height * 0.75) {
            this.dropPosition = 'after';
            event.currentTarget.classList.add('border-b-2', 'border-indigo-500');
        } else {
            this.dropPosition = 'inside';
            event.currentTarget.classList.add('bg-indigo-100');
        }

        this.dropTarget = targetProject;
    },

    /**
     * Handle drop
     */
    async handleDrop(event, targetProject, treeComponent) {
        event.preventDefault();

        this.clearDropIndicators();

        if (!this.draggedProject || !this.dropTarget) return;

        // Don't allow dropping on self or descendants
        if (this.isDescendant(this.draggedProject, targetProject)) {
            this.reset();
            return;
        }

        let newParentId = null;
        let newPosition = null;

        if (this.dropPosition === 'inside') {
            // Move inside the target (make it a child)
            newParentId = targetProject.id;
        } else {
            // Move before/after the target (same parent as target)
            newParentId = targetProject.parentId || null;

            // Calculate position
            // This is simplified - in production you'd need to handle reordering logic
            if (this.dropPosition === 'before') {
                newPosition = targetProject.position;
            } else {
                newPosition = targetProject.position + 1;
            }
        }

        // Call API to move project
        try {
            const response = await fetch(window.apiUrl(`/api/v1/projects/${this.draggedProject.id}/move`), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    parentId: newParentId,
                    position: newPosition
                })
            });

            if (response.ok) {
                // Refresh the tree
                if (treeComponent) {
                    await treeComponent.refreshTree();
                }
                window.showToast('Project moved successfully', 'success');
            } else {
                const data = await response.json();
                window.showToast(data.error?.message || 'Failed to move project', 'error');
            }
        } catch (error) {
            console.error('Failed to move project:', error);
            window.showToast('Failed to move project', 'error');
        }

        this.reset();
    },

    /**
     * Handle drag end
     */
    handleDragEnd(event, treeComponent) {
        event.target.classList.remove('opacity-50');
        this.clearDropIndicators();
        this.reset();
    },

    /**
     * Check if a project is a descendant of another
     */
    isDescendant(potentialAncestor, project) {
        if (potentialAncestor.id === project.id) return true;

        if (potentialAncestor.children) {
            for (const child of potentialAncestor.children) {
                if (this.isDescendant(child, project)) return true;
            }
        }

        // Also check if target is in the dragged project's subtree
        if (project.path) {
            return project.path.some(p => p.id === potentialAncestor.id);
        }

        return false;
    },

    /**
     * Clear all drop indicators
     */
    clearDropIndicators() {
        document.querySelectorAll('.project-tree-node > div').forEach(el => {
            el.classList.remove(
                'border-t-2',
                'border-b-2',
                'border-indigo-500',
                'bg-indigo-100'
            );
        });
    },

    /**
     * Reset drag state
     */
    reset() {
        this.draggedProject = null;
        this.dropTarget = null;
        this.dropPosition = null;
        this.treeComponent = null;
    }
};

/**
 * Project Reorder Handler for batch operations
 */
window.projectReorder = {
    /**
     * Reorder projects within a parent
     */
    async reorderProjects(parentId, projectIds) {
        try {
            const response = await fetch(window.apiUrl('/api/v1/projects/reorder'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    parentId: parentId,
                    projectIds: projectIds
                })
            });

            return response.ok;
        } catch (error) {
            console.error('Failed to reorder projects:', error);
            return false;
        }
    }
};

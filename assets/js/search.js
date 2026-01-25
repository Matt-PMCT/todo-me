/**
 * Search Component
 *
 * Provides global search functionality with:
 * - Debounced API calls
 * - Keyboard navigation
 * - "/" global shortcut to focus
 */

function searchComponent() {
    return {
        query: '',
        open: false,
        loading: false,
        selectedIndex: -1,
        results: {
            tasks: [],
            projects: [],
            tags: []
        },

        async search() {
            if (this.query.length < 2) {
                this.results = { tasks: [], projects: [], tags: [] };
                return;
            }

            this.loading = true;
            this.open = true;

            try {
                const response = await fetch(`/api/v1/search?q=${encodeURIComponent(this.query)}&highlight=true&limit=5`);
                if (response.ok) {
                    const data = await response.json();
                    if (data.success) {
                        this.results = {
                            tasks: data.data.tasks || [],
                            projects: data.data.projects || [],
                            tags: data.data.tags || []
                        };
                    }
                }
            } catch (error) {
                console.error('Search failed:', error);
            } finally {
                this.loading = false;
            }
        },

        navigateDown() {
            const total = this.results.tasks.length + this.results.projects.length + this.results.tags.length;
            if (this.selectedIndex < total - 1) {
                this.selectedIndex++;
            }
        },

        navigateUp() {
            if (this.selectedIndex > 0) {
                this.selectedIndex--;
            }
        },

        selectCurrent() {
            if (this.selectedIndex < 0) return;

            const taskCount = this.results.tasks.length;
            const projectCount = this.results.projects.length;

            if (this.selectedIndex < taskCount) {
                window.location.href = '/tasks/' + this.results.tasks[this.selectedIndex].id;
            } else if (this.selectedIndex < taskCount + projectCount) {
                const projectIndex = this.selectedIndex - taskCount;
                window.location.href = '/projects/' + this.results.projects[projectIndex].id;
            } else {
                const tagIndex = this.selectedIndex - taskCount - projectCount;
                window.location.href = '/tasks?tagIds[]=' + this.results.tags[tagIndex].id;
            }
        },

        close() {
            this.open = false;
            this.selectedIndex = -1;
        }
    };
}

// Note: "/" shortcut to focus search is now handled in keyboard-shortcuts.js

// Make available globally
window.searchComponent = searchComponent;

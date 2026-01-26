/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import Alpine from 'alpinejs';
import './styles/app.css';

// Initialize Alpine stores before starting
Alpine.store('sidebar', {
    open: false,
    toggle() {
        this.open = !this.open;
    }
});

// Make Alpine available globally and start
window.Alpine = Alpine;
Alpine.start();

// Import toast notification system
import './js/toast.js';

// Import project tree components
import './js/project-tree.js';
import './js/drag-drop.js';

// Import search component
import './js/search.js';

// Import keyboard shortcuts
import './js/keyboard-shortcuts.js';

// Import subtask interactions
import './js/subtasks.js';

// Import mobile swipe gestures
import './js/swipe-gestures.js';

console.log('This log comes from assets/app.js - welcome to AssetMapper!');

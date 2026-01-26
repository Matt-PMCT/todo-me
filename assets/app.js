/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import Alpine from 'alpinejs';
import './styles/app.css';

// Import modules that register Alpine stores/components BEFORE Alpine.start()
// These use 'alpine:init' event which must be set up before start()
import './js/toast.js';
import './js/subtasks.js';

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

// Import other modules that don't need alpine:init
import './js/project-tree.js';
import './js/drag-drop.js';
import './js/search.js';
import './js/keyboard-shortcuts.js';
import './js/swipe-gestures.js';

console.log('This log comes from assets/app.js - welcome to AssetMapper!');

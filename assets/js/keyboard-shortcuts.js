/**
 * Keyboard Shortcuts Module
 *
 * Provides global keyboard shortcuts for the todo-me application.
 * Shortcuts are disabled when focus is in input/textarea elements.
 *
 * Shortcuts:
 * - Ctrl/Cmd+K: Focus Quick Add input
 * - Ctrl/Cmd+Enter: Submit Quick Add form
 * - Escape: Close modal/dropdown (dispatches 'closeAll' event)
 * - /: Focus search input
 * - ?: Show keyboard help modal
 * - j/Down: Navigate to next task
 * - k/Up: Navigate to previous task
 * - Enter: Open selected task details
 * - Ctrl/Cmd+Z: Trigger undo (if available)
 */

/**
 * Initialize keyboard shortcuts for the application
 */
export function initKeyboardShortcuts() {
    document.addEventListener('keydown', handleKeydown);
}

/**
 * Check if the event target is an editable element
 * @param {KeyboardEvent} e
 * @returns {boolean}
 */
function isEditableElement(e) {
    const target = e.target;
    const tagName = target.tagName;

    return (
        tagName === 'INPUT' ||
        tagName === 'TEXTAREA' ||
        tagName === 'SELECT' ||
        target.isContentEditable
    );
}

/**
 * Check if modifier key is pressed (Ctrl on Windows/Linux, Cmd on Mac)
 * @param {KeyboardEvent} e
 * @returns {boolean}
 */
function hasModifier(e) {
    return e.ctrlKey || e.metaKey;
}

/**
 * Handle keydown events
 * @param {KeyboardEvent} e
 */
function handleKeydown(e) {
    const inEditable = isEditableElement(e);

    // Shortcuts that work in editable elements
    if (e.key === 'Escape') {
        handleEscape(e);
        return;
    }

    // Ctrl/Cmd+Enter: Submit Quick Add form (works in editable)
    if (e.key === 'Enter' && hasModifier(e)) {
        handleQuickAddSubmit(e);
        return;
    }

    // Ctrl/Cmd+K: Focus Quick Add input (works everywhere)
    if (e.key === 'k' && hasModifier(e)) {
        e.preventDefault();
        focusQuickAdd();
        return;
    }

    // Ctrl/Cmd+Z: Trigger undo (works everywhere)
    if (e.key === 'z' && hasModifier(e) && !e.shiftKey) {
        handleUndo(e);
        return;
    }

    // Shortcuts that only work outside editable elements
    if (inEditable) {
        return;
    }

    switch (e.key) {
        case '/':
            e.preventDefault();
            focusSearch();
            break;

        case '?':
            e.preventDefault();
            showKeyboardHelp();
            break;

        case 'j':
        case 'ArrowDown':
            e.preventDefault();
            navigateTask('down');
            break;

        case 'k':
        case 'ArrowUp':
            e.preventDefault();
            navigateTask('up');
            break;

        case 'Enter':
            handleTaskOpen(e);
            break;
    }
}

/**
 * Handle Escape key - close modals and dropdowns
 * @param {KeyboardEvent} e
 */
function handleEscape(e) {
    // Dispatch custom event for Alpine.js components to listen to
    window.dispatchEvent(new CustomEvent('closeAll'));

    // Also blur any focused input
    if (document.activeElement && isEditableElement(e)) {
        document.activeElement.blur();
    }
}

/**
 * Focus the Quick Add input
 */
function focusQuickAdd() {
    // Look for the quick add input by x-ref or by container
    const quickAddContainer = document.querySelector('[x-data*="quickAdd"]');
    if (quickAddContainer) {
        const input = quickAddContainer.querySelector('input[type="text"]');
        if (input) {
            input.focus();
            input.select();
            return;
        }
    }

    // Fallback: look for any task title input
    const titleInput = document.getElementById('title');
    if (titleInput) {
        titleInput.focus();
        titleInput.select();
    }
}

/**
 * Handle Ctrl/Cmd+Enter to submit Quick Add form
 * @param {KeyboardEvent} e
 */
function handleQuickAddSubmit(e) {
    const quickAddContainer = document.querySelector('[x-data*="quickAdd"]');
    if (quickAddContainer) {
        const input = quickAddContainer.querySelector('input[type="text"]');
        if (input && document.activeElement === input) {
            e.preventDefault();
            // Find and click the submit button
            const submitBtn = quickAddContainer.querySelector('button[type="button"]');
            if (submitBtn) {
                submitBtn.click();
            }
        }
    }
}

/**
 * Focus the search input
 */
function focusSearch() {
    const searchInput = document.querySelector('[x-data*="searchComponent"] input');
    if (searchInput) {
        searchInput.focus();
    }
}

/**
 * Show the keyboard help modal
 */
function showKeyboardHelp() {
    window.dispatchEvent(new CustomEvent('keyboardHelp'));
}

/**
 * Navigate through the task list
 * @param {'up' | 'down'} direction
 */
function navigateTask(direction) {
    // Get all task items
    const taskItems = document.querySelectorAll('[data-task-item]');
    if (taskItems.length === 0) {
        return;
    }

    // Get currently selected index from Alpine store or find focused item
    let currentIndex = -1;
    const store = window.Alpine?.store('keyboard');
    if (store) {
        currentIndex = store.selectedTaskIndex;
    }

    // Calculate new index
    let newIndex;
    if (direction === 'down') {
        newIndex = currentIndex < taskItems.length - 1 ? currentIndex + 1 : 0;
    } else {
        newIndex = currentIndex > 0 ? currentIndex - 1 : taskItems.length - 1;
    }

    // Update store if available
    if (store) {
        store.selectedTaskIndex = newIndex;
    }

    // Dispatch event for task selection
    window.dispatchEvent(new CustomEvent('taskSelect', { detail: { index: newIndex } }));

    // Scroll the selected task into view and add visual highlight
    const selectedTask = taskItems[newIndex];
    if (selectedTask) {
        selectedTask.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        // Remove highlight from all tasks
        taskItems.forEach(item => item.classList.remove('ring-2', 'ring-indigo-500'));

        // Add highlight to selected task
        selectedTask.classList.add('ring-2', 'ring-indigo-500');
    }
}

/**
 * Handle Enter key to open selected task
 * @param {KeyboardEvent} e
 */
function handleTaskOpen(e) {
    const store = window.Alpine?.store('keyboard');
    if (!store || store.selectedTaskIndex < 0) {
        return;
    }

    const taskItems = document.querySelectorAll('[data-task-item]');
    const selectedTask = taskItems[store.selectedTaskIndex];

    if (selectedTask) {
        e.preventDefault();
        // Look for a link in the task item
        const link = selectedTask.querySelector('a[href*="/tasks/"]');
        if (link) {
            window.location.href = link.href;
        }
    }
}

/**
 * Handle Ctrl/Cmd+Z for undo
 * @param {KeyboardEvent} e
 */
function handleUndo(e) {
    // Check if there's an undo toast with an undo button
    const undoButton = document.querySelector('[x-data*="toasts"] button[x-show*="undoToken"]');
    if (undoButton) {
        e.preventDefault();
        undoButton.click();
    }
}

// Initialize keyboard store for Alpine.js
document.addEventListener('alpine:init', () => {
    Alpine.store('keyboard', {
        selectedTaskIndex: -1,

        reset() {
            this.selectedTaskIndex = -1;
        }
    });
});

// Auto-initialize if this script is loaded directly
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKeyboardShortcuts);
} else {
    initKeyboardShortcuts();
}

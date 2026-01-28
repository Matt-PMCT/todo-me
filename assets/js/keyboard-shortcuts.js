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
 * - n: Focus quick add (alias for Ctrl+K)
 * - c: Complete selected task
 * - e: Edit selected task
 * - Delete: Delete selected task (with confirmation)
 * - t: Set due date to today
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

        case 'n':
            e.preventDefault();
            focusQuickAdd();
            break;

        case 'c':
            e.preventDefault();
            completeSelectedTask();
            break;

        case 'e':
            e.preventDefault();
            editSelectedTask();
            break;

        case 'Delete':
        case 'Backspace':
            e.preventDefault();
            deleteSelectedTask();
            break;

        case 't':
            e.preventDefault();
            setDueDateToday();
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
        // Issue #63: Quick-add uses textarea, not input
        const input = quickAddContainer.querySelector('textarea, input[type="text"]');
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
        // Issue #63: Quick-add uses textarea, not input
        const input = quickAddContainer.querySelector('textarea, input[type="text"]');
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

        // Issue #63: Remove highlight from all tasks (including dark mode classes)
        taskItems.forEach(item => item.classList.remove('ring-2', 'ring-teal-500', 'dark:ring-teal-400'));

        // Add highlight to selected task with dark mode support
        selectedTask.classList.add('ring-2', 'ring-teal-500', 'dark:ring-teal-400');
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

/**
 * Get the currently selected task element
 * @returns {Element|null}
 */
function getSelectedTask() {
    const store = window.Alpine?.store('keyboard');
    if (!store || store.selectedTaskIndex < 0) {
        return null;
    }

    const taskItems = document.querySelectorAll('[data-task-item]');
    return taskItems[store.selectedTaskIndex] || null;
}

/**
 * Get the task ID from a task element
 * @param {Element} taskElement
 * @returns {string|null}
 */
function getTaskId(taskElement) {
    return taskElement.getAttribute('data-task-id') || taskElement.dataset.taskItem || null;
}

/**
 * Complete the currently selected task
 */
function completeSelectedTask() {
    const selectedTask = getSelectedTask();
    if (!selectedTask) {
        return;
    }

    // Look for the complete checkbox or button
    const completeCheckbox = selectedTask.querySelector('input[type="checkbox"]');
    if (completeCheckbox) {
        completeCheckbox.click();
        return;
    }

    // Fallback: look for a complete button
    const completeButton = selectedTask.querySelector('[data-action="complete"], button[title*="Complete"]');
    if (completeButton) {
        completeButton.click();
    }
}

/**
 * Edit the currently selected task
 */
function editSelectedTask() {
    const selectedTask = getSelectedTask();
    if (!selectedTask) {
        return;
    }

    // Look for an edit link or button
    const editLink = selectedTask.querySelector('a[href*="/edit"], a[href*="/tasks/"]');
    if (editLink) {
        window.location.href = editLink.href;
        return;
    }

    // Dispatch event for inline editing if available
    const taskId = getTaskId(selectedTask);
    if (taskId) {
        window.dispatchEvent(new CustomEvent('editTask', { detail: { taskId } }));
    }
}

/**
 * Delete the currently selected task with confirmation
 */
function deleteSelectedTask() {
    const selectedTask = getSelectedTask();
    if (!selectedTask) {
        return;
    }

    const taskId = getTaskId(selectedTask);
    if (!taskId) {
        return;
    }

    // Confirm deletion
    if (!confirm('Are you sure you want to delete this task?')) {
        return;
    }

    // Look for a delete button
    const deleteButton = selectedTask.querySelector('[data-action="delete"], button[title*="Delete"]');
    if (deleteButton) {
        deleteButton.click();
        return;
    }

    // Dispatch delete event
    window.dispatchEvent(new CustomEvent('deleteTask', { detail: { taskId } }));
}

/**
 * Set due date to today for the currently selected task
 */
function setDueDateToday() {
    const selectedTask = getSelectedTask();
    if (!selectedTask) {
        return;
    }

    const taskId = getTaskId(selectedTask);
    if (!taskId) {
        return;
    }

    // Dispatch event for setting due date
    window.dispatchEvent(new CustomEvent('setDueDateToday', { detail: { taskId } }));
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

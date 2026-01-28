/**
 * Swipe Gestures Module
 *
 * Provides touch-based swipe gestures for task items on mobile devices.
 *
 * Features:
 * - Swipe right to complete task
 * - Swipe left to reveal delete action
 * - Visual feedback during swipe
 * - Threshold-based action triggers
 */

const SWIPE_THRESHOLD = 80; // pixels needed to trigger action
const SWIPE_MAX = 120; // maximum swipe distance

/**
 * Initialize swipe gestures for all task items
 */
export function initSwipeGestures() {
    // Only enable on touch devices
    if (!('ontouchstart' in window)) {
        return;
    }

    // Use event delegation for dynamically loaded tasks
    document.addEventListener('touchstart', handleTouchStart, { passive: true });
    document.addEventListener('touchmove', handleTouchMove, { passive: false });
    document.addEventListener('touchend', handleTouchEnd, { passive: true });
    // Issue #63: Handle touchcancel for edge cases (e.g., incoming call)
    document.addEventListener('touchcancel', handleTouchEnd, { passive: true });
}

let startX = 0;
let startY = 0;
let currentX = 0;
let isDragging = false;
let currentTaskItem = null;
let swipeDirection = null; // 'left' or 'right'

/**
 * Handle touch start
 * @param {TouchEvent} e
 */
function handleTouchStart(e) {
    const taskItem = e.target.closest('[data-task-item]');
    if (!taskItem) return;

    const touch = e.touches[0];
    startX = touch.clientX;
    startY = touch.clientY;
    currentX = startX;
    isDragging = false;
    currentTaskItem = taskItem;
    swipeDirection = null;

    // Add swipe container if not present
    ensureSwipeContainer(taskItem);
}

/**
 * Handle touch move
 * @param {TouchEvent} e
 */
function handleTouchMove(e) {
    if (!currentTaskItem) return;

    const touch = e.touches[0];
    const deltaX = touch.clientX - startX;
    const deltaY = touch.clientY - startY;

    // Determine if this is a horizontal or vertical scroll
    if (!isDragging && Math.abs(deltaX) > 10) {
        // Check if horizontal movement is greater than vertical
        if (Math.abs(deltaX) > Math.abs(deltaY)) {
            isDragging = true;
            swipeDirection = deltaX > 0 ? 'right' : 'left';
        }
    }

    if (!isDragging) return;

    // Prevent vertical scrolling while swiping
    e.preventDefault();

    currentX = touch.clientX;
    const swipeAmount = Math.min(Math.abs(deltaX), SWIPE_MAX);
    const clampedDelta = swipeDirection === 'right' ? swipeAmount : -swipeAmount;

    // Apply transform
    const content = currentTaskItem.querySelector('[data-swipe-content]');
    if (content) {
        content.style.transform = `translateX(${clampedDelta}px)`;
        content.style.transition = 'none';
    }

    // Update action indicators
    updateActionIndicator(currentTaskItem, swipeDirection, swipeAmount);
}

/**
 * Handle touch end
 * @param {TouchEvent} e
 */
function handleTouchEnd(e) {
    if (!currentTaskItem || !isDragging) {
        resetState();
        return;
    }

    const deltaX = currentX - startX;
    const swipeAmount = Math.abs(deltaX);

    // Check if swipe threshold was met
    if (swipeAmount >= SWIPE_THRESHOLD) {
        if (swipeDirection === 'right') {
            triggerComplete(currentTaskItem);
        } else if (swipeDirection === 'left') {
            showDeleteAction(currentTaskItem);
        }
    }

    // Reset position
    resetTaskPosition(currentTaskItem);
    resetState();
}

/**
 * Reset tracking state
 */
function resetState() {
    startX = 0;
    startY = 0;
    currentX = 0;
    isDragging = false;
    currentTaskItem = null;
    swipeDirection = null;
}

/**
 * Reset task item position with animation
 * @param {HTMLElement} taskItem
 */
function resetTaskPosition(taskItem) {
    const content = taskItem.querySelector('[data-swipe-content]');
    if (content) {
        content.style.transition = 'transform 0.2s ease-out';
        content.style.transform = 'translateX(0)';
    }

    // Issue #63: Clear inline opacity styles before adding class
    // Inline styles override classes, causing stuck backgrounds
    const leftIndicator = taskItem.querySelector('[data-swipe-left]');
    const rightIndicator = taskItem.querySelector('[data-swipe-right]');
    if (leftIndicator) {
        leftIndicator.style.opacity = '';
        leftIndicator.classList.add('opacity-0');
    }
    if (rightIndicator) {
        rightIndicator.style.opacity = '';
        rightIndicator.classList.add('opacity-0');
    }
}

/**
 * Ensure the task item has swipe containers
 * @param {HTMLElement} taskItem
 */
function ensureSwipeContainer(taskItem) {
    if (taskItem.querySelector('[data-swipe-content]')) return;

    // Wrap content - Issue #63: Add dark mode support
    const content = document.createElement('div');
    content.setAttribute('data-swipe-content', '');
    content.className = 'relative bg-white dark:bg-gray-800 rounded-lg';

    // Move children to content wrapper
    while (taskItem.firstChild) {
        content.appendChild(taskItem.firstChild);
    }
    taskItem.appendChild(content);

    // Add left indicator (delete)
    const leftIndicator = document.createElement('div');
    leftIndicator.setAttribute('data-swipe-left', '');
    leftIndicator.className = 'absolute inset-y-0 right-0 w-20 bg-red-500 flex items-center justify-center opacity-0 transition-opacity rounded-r-lg';
    leftIndicator.innerHTML = `
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
        </svg>
    `;
    taskItem.insertBefore(leftIndicator, content);

    // Add right indicator (complete)
    const rightIndicator = document.createElement('div');
    rightIndicator.setAttribute('data-swipe-right', '');
    rightIndicator.className = 'absolute inset-y-0 left-0 w-20 bg-green-500 flex items-center justify-center opacity-0 transition-opacity rounded-l-lg';
    rightIndicator.innerHTML = `
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
    `;
    taskItem.insertBefore(rightIndicator, content);

    // Style container
    taskItem.classList.add('relative', 'overflow-hidden');
}

/**
 * Update action indicator visibility
 * @param {HTMLElement} taskItem
 * @param {'left' | 'right'} direction
 * @param {number} amount
 */
function updateActionIndicator(taskItem, direction, amount) {
    const leftIndicator = taskItem.querySelector('[data-swipe-left]');
    const rightIndicator = taskItem.querySelector('[data-swipe-right]');
    const opacity = Math.min(amount / SWIPE_THRESHOLD, 1);

    if (direction === 'left' && leftIndicator) {
        leftIndicator.style.opacity = opacity;
        leftIndicator.classList.remove('opacity-0');
    }
    if (direction === 'right' && rightIndicator) {
        rightIndicator.style.opacity = opacity;
        rightIndicator.classList.remove('opacity-0');
    }
}

/**
 * Trigger task completion
 * @param {HTMLElement} taskItem
 */
function triggerComplete(taskItem) {
    const taskId = taskItem.dataset.taskId;
    if (!taskId) return;

    // Find and click the completion form button
    const form = taskItem.querySelector('form[action*="status"]');
    if (form) {
        // Check if task is already completed (has hidden input with value "pending")
        const statusInput = form.querySelector('input[name="status"]');
        if (statusInput && statusInput.value !== 'completed') {
            // Animate success
            taskItem.classList.add('bg-green-50');
            setTimeout(() => {
                form.submit();
            }, 200);
        }
    }
}

/**
 * Show delete action confirmation
 * @param {HTMLElement} taskItem
 */
function showDeleteAction(taskItem) {
    // Trigger the delete modal via Alpine.js
    const alpineComponent = taskItem.__x;
    if (alpineComponent && alpineComponent.$data) {
        alpineComponent.$data.showDeleteModal = true;
    } else {
        // Fallback: dispatch custom event
        taskItem.dispatchEvent(new CustomEvent('show-delete-modal', { bubbles: true }));
    }
}

// Auto-initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSwipeGestures);
} else {
    initSwipeGestures();
}

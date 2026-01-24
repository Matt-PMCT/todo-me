# Phase 8: Polish & Testing (Revised)

## Overview
**Duration**: Week 8
**Goal**: Complete UI polish including keyboard shortcuts, mobile responsiveness improvements, performance optimization, comprehensive testing, and CI/CD pipeline setup.

## Revision History
- **2026-01-24**: Initial creation with revised scope based on actual implementation state

## Current Implementation Status

### Already Implemented (from earlier phases)

**Subtasks** (100% complete - was originally planned for Phase 8):
- [x] Task entity has `parent_task_id` foreign key and `parentTask` relationship
- [x] `Task::subtasks` collection (one-to-many with orphanRemoval)
- [x] Database index `idx_tasks_parent` for performance
- [x] Helper methods: `getParentTask()`, `setParentTask()`, `getSubtasks()`, `addSubtask()`, `removeSubtask()`
- [x] API support: Can create tasks with `parent_task_id`
- [x] Cascade delete: Child tasks deleted with parent

**Mobile Responsiveness** (85% complete):
- [x] Tailwind CSS with responsive utilities (sm:, md:, lg: breakpoints)
- [x] Mobile-first design approach throughout
- [x] Hamburger menu on mobile (`mobileMenuOpen` Alpine.js state)
- [x] Collapsible sidebar (hidden on mobile, visible on md+ screens)
- [x] Full-width task cards on mobile with padded grids on desktop
- [x] Collapsible filter panel on mobile
- [x] Touch-friendly button sizes (min 44px targets)
- [ ] Swipe gestures for task actions (not implemented)
- [ ] Bottom navigation bar (not implemented)

**Keyboard Shortcuts** (20% complete):
- [x] `/` (forward slash) - Global shortcut to focus search input
- [x] Arrow Up/Down - Navigate search results
- [x] Enter - Select highlighted search result
- [x] Escape - Close search dropdown
- [ ] Remaining shortcuts (see Sub-Phase 8.2)

**Performance & Caching** (70% complete):
- [x] Project tree caching with 5-minute TTL (ProjectCacheService)
- [x] Redis integration for undo tokens and rate limiting
- [x] 20+ database indexes on common query patterns
- [x] GIN index for full-text search
- [x] Pagination with configurable limits (max 100)
- [x] Atomic Redis operations via Lua scripts
- [ ] Query performance testing/benchmarks
- [ ] Load testing

**Testing Infrastructure** (80% complete):
- [x] 93 test files with 450+ test methods
- [x] Unit tests for services, DTOs, entities, parsers
- [x] Functional/API tests for all endpoints
- [x] Integration tests for repositories and cache
- [x] Test fixtures available
- [ ] CI/CD pipeline (not implemented)
- [ ] Code coverage reporting (not configured)
- [ ] Performance regression tests (not implemented)

---

## Prerequisites

- Phases 1-7 completed (with remaining Phase 7 items noted as dependencies)
- Docker environment functional
- Redis and PostgreSQL configured

---

## Phase 7 Remaining Items (Complete First)

Before proceeding with Phase 8, these Phase 7 items should be completed:

1. **Add `dueTime` to TaskStateService** - Quick fix for undo completeness
2. **Recurring task completion undo** - Delete auto-created next task when undoing
3. **Search UI enhancements** - Toast notifications with countdown integration
4. **Search endpoint highlights** - `ts_headline()` for highlighting matches

These items are tracked in `docs/original_plans/PHASE-7-SEARCH-UNDO.md` and should be addressed before or in parallel with Phase 8 work.

---

## Sub-Phase 8.1: Subtasks UI Enhancement

### Status: BACKEND COMPLETE, UI ENHANCEMENT NEEDED

### Background
Subtask support is fully implemented in the backend. This sub-phase focuses on UI/UX improvements for working with subtasks.

### Tasks

- [ ] **8.1.1** Add subtask indicator and count to task cards
  ```twig
  {# In task card component, show subtask count #}
  {% if task.subtasks|length > 0 %}
  <div class="flex items-center gap-1 text-xs text-gray-500">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 6h16M4 12h16M4 18h7"/>
      </svg>
      <span>{{ task.completedSubtaskCount }}/{{ task.subtasks|length }}</span>
  </div>
  {% endif %}
  ```

- [ ] **8.1.2** Create expandable subtask list in task detail view
  ```twig
  {# templates/task/partials/_subtasks.html.twig #}
  <div x-data="{ expanded: true }" class="mt-4 border-t pt-4">
      <button @click="expanded = !expanded"
              class="flex items-center gap-2 text-sm font-medium text-gray-700">
          <svg :class="{ 'rotate-90': expanded }"
               class="w-4 h-4 transition-transform">...</svg>
          Subtasks ({{ subtasks|length }})
      </button>

      <div x-show="expanded" x-transition class="mt-2 pl-6 space-y-2">
          {% for subtask in subtasks %}
              {% include 'task/partials/_task_row.html.twig' with {'task': subtask, 'compact': true} %}
          {% endfor %}

          {# Inline add subtask #}
          <div x-data="inlineSubtaskAdd('{{ task.id }}')" class="mt-2">
              <input type="text" x-model="title" @keydown.enter="add()"
                     placeholder="Add subtask..."
                     class="text-sm w-full border-0 border-b border-gray-200
                            focus:border-indigo-500 focus:ring-0 py-1">
          </div>
      </div>
  </div>
  ```

- [ ] **8.1.3** Add "Add subtask" action to task menu
  ```javascript
  // In task actions dropdown, add subtask option
  {
      label: 'Add subtask',
      icon: 'plus',
      action: () => showAddSubtaskModal(taskId)
  }
  ```

- [ ] **8.1.4** Implement subtask progress indicator
  ```twig
  {# Progress bar showing subtask completion #}
  {% set completedCount = task.subtasks|filter(s => s.status == 'completed')|length %}
  {% set totalCount = task.subtasks|length %}
  {% if totalCount > 0 %}
  <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2">
      <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-300"
           style="width: {{ (completedCount / totalCount) * 100 }}%"></div>
  </div>
  {% endif %}
  ```

### Completion Criteria
- [ ] Subtask count visible on task cards
- [ ] Expandable subtask list in task detail
- [ ] Inline subtask addition
- [ ] Progress indicator for subtask completion
- [ ] Subtask actions (complete, delete) functional

### Files to Create/Update
```
templates/task/partials/_subtasks.html.twig (new)
templates/components/task-card.html.twig (update)
templates/task/view.html.twig (update)
assets/js/subtasks.js (new)
```

---

## Sub-Phase 8.2: Keyboard Shortcuts

### Status: PARTIALLY COMPLETE

### Implemented Shortcuts
| Shortcut | Action | Status |
|----------|--------|--------|
| `/` | Focus search | Done |
| Arrow keys | Navigate search | Done |
| Enter | Select search result | Done |
| Escape | Close search | Done |

### Tasks

- [ ] **8.2.1** Implement global keyboard handler
  ```javascript
  // assets/js/keyboard-shortcuts.js

  const SHORTCUTS = {
      'n': { action: 'focusQuickAdd', description: 'New task' },
      'c': { action: 'completeSelected', description: 'Complete task', requiresSelection: true },
      'e': { action: 'editSelected', description: 'Edit task', requiresSelection: true },
      'Delete': { action: 'deleteSelected', description: 'Delete task', requiresSelection: true },
      'Backspace': { action: 'deleteSelected', description: 'Delete task', requiresSelection: true },
      't': { action: 'setDueToday', description: 'Due today', requiresSelection: true },
      'y': { action: 'setDueTomorrow', description: 'Due tomorrow', requiresSelection: true },
      '?': { action: 'showShortcutsModal', description: 'Show shortcuts' },
      'j': { action: 'navigateDown', description: 'Next task' },
      'k': { action: 'navigateUp', description: 'Previous task' },
      '1': { action: 'setPriority1', description: 'Priority: Low', requiresSelection: true },
      '2': { action: 'setPriority2', description: 'Priority: Medium', requiresSelection: true },
      '3': { action: 'setPriority3', description: 'Priority: High', requiresSelection: true },
      '4': { action: 'setPriority4', description: 'Priority: Urgent', requiresSelection: true },
  };

  // With Ctrl/Cmd modifiers
  const MODIFIED_SHORTCUTS = {
      'Ctrl+k': { action: 'focusSearch', description: 'Search' },
      'Ctrl+Enter': { action: 'submitQuickAdd', description: 'Submit quick add' },
      'Ctrl+z': { action: 'undo', description: 'Undo last action' },
  };

  document.addEventListener('keydown', (e) => {
      // Skip if in input/textarea
      if (['INPUT', 'TEXTAREA'].includes(e.target.tagName) || e.target.isContentEditable) {
          // Allow escape to blur
          if (e.key === 'Escape') {
              e.target.blur();
          }
          return;
      }

      const key = e.key;
      const modKey = (e.ctrlKey || e.metaKey) ? 'Ctrl+' : '';
      const fullKey = modKey + key;

      const shortcut = MODIFIED_SHORTCUTS[fullKey] || SHORTCUTS[key];
      if (!shortcut) return;

      if (shortcut.requiresSelection && !getSelectedTask()) {
          return;
      }

      e.preventDefault();
      executeAction(shortcut.action);
  });
  ```

- [ ] **8.2.2** Implement task selection state
  ```javascript
  // Task selection for keyboard navigation
  let selectedTaskId = null;

  function getSelectedTask() {
      return selectedTaskId;
  }

  function selectTask(taskId) {
      // Remove previous selection
      document.querySelectorAll('.task-card.selected').forEach(el => {
          el.classList.remove('selected', 'ring-2', 'ring-indigo-500');
      });

      selectedTaskId = taskId;

      if (taskId) {
          const taskEl = document.querySelector(`[data-task-id="${taskId}"]`);
          if (taskEl) {
              taskEl.classList.add('selected', 'ring-2', 'ring-indigo-500');
              taskEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }
      }
  }

  function navigateUp() {
      const tasks = Array.from(document.querySelectorAll('[data-task-id]'));
      const currentIndex = tasks.findIndex(t => t.dataset.taskId === selectedTaskId);
      if (currentIndex > 0) {
          selectTask(tasks[currentIndex - 1].dataset.taskId);
      }
  }

  function navigateDown() {
      const tasks = Array.from(document.querySelectorAll('[data-task-id]'));
      const currentIndex = tasks.findIndex(t => t.dataset.taskId === selectedTaskId);
      if (currentIndex < tasks.length - 1) {
          selectTask(tasks[currentIndex + 1].dataset.taskId);
      } else if (currentIndex === -1 && tasks.length > 0) {
          selectTask(tasks[0].dataset.taskId);
      }
  }
  ```

- [ ] **8.2.3** Create keyboard shortcuts help modal
  ```twig
  {# templates/components/keyboard-shortcuts-modal.html.twig #}
  <div x-data="{ open: false }"
       @keydown.window.?="open = true"
       @keydown.window.escape="open = false">

      <template x-teleport="body">
          <div x-show="open" x-transition:enter="ease-out duration-300"
               class="fixed inset-0 z-50 overflow-y-auto" role="dialog">

              <div class="fixed inset-0 bg-gray-500/75" @click="open = false"></div>

              <div class="relative min-h-screen flex items-center justify-center p-4">
                  <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
                      <h2 class="text-lg font-semibold text-gray-900 mb-4">
                          Keyboard Shortcuts
                      </h2>

                      <div class="space-y-4">
                          {# Navigation #}
                          <div>
                              <h3 class="text-sm font-medium text-gray-500 uppercase mb-2">Navigation</h3>
                              <div class="space-y-1">
                                  {% for shortcut in navigationShortcuts %}
                                  <div class="flex justify-between text-sm">
                                      <span class="text-gray-600">{{ shortcut.description }}</span>
                                      <kbd class="bg-gray-100 px-2 py-0.5 rounded text-xs font-mono">
                                          {{ shortcut.key }}
                                      </kbd>
                                  </div>
                                  {% endfor %}
                              </div>
                          </div>

                          {# Task Actions #}
                          <div>
                              <h3 class="text-sm font-medium text-gray-500 uppercase mb-2">Task Actions</h3>
                              {# ... similar structure ... #}
                          </div>

                          {# Priority #}
                          <div>
                              <h3 class="text-sm font-medium text-gray-500 uppercase mb-2">Priority</h3>
                              {# ... #}
                          </div>
                      </div>

                      <button @click="open = false"
                              class="mt-6 w-full bg-gray-100 text-gray-700 rounded-md py-2 text-sm font-medium hover:bg-gray-200">
                          Close (Esc)
                      </button>
                  </div>
              </div>
          </div>
      </template>
  </div>
  ```

- [ ] **8.2.4** Add keyboard hint to UI elements
  ```twig
  {# Add keyboard hints to buttons #}
  <button class="...">
      New Task
      <kbd class="ml-2 text-xs bg-gray-100 px-1 rounded hidden sm:inline">n</kbd>
  </button>

  {# In search input #}
  <div class="relative">
      <input type="text" placeholder="Search..." class="pr-12 ...">
      <kbd class="absolute right-3 top-1/2 -translate-y-1/2
                  bg-gray-100 px-1.5 py-0.5 rounded text-xs text-gray-500">/</kbd>
  </div>
  ```

### Target Shortcuts (Full List)

| Shortcut | Action | Context |
|----------|--------|---------|
| `n` | New task (focus quick add) | Global |
| `/` | Focus search | Global |
| `?` | Show shortcuts help | Global |
| `j` / `k` | Navigate down / up | Task list |
| `Enter` | Open task detail | Task selected |
| `c` | Complete task | Task selected |
| `e` | Edit task | Task selected |
| `Delete` / `Backspace` | Delete task | Task selected |
| `t` | Set due today | Task selected |
| `y` | Set due tomorrow | Task selected |
| `1-4` | Set priority | Task selected |
| `Escape` | Clear selection / close modal | Global |
| `Ctrl+K` | Focus search (alternate) | Global |
| `Ctrl+Enter` | Submit quick add | Quick add focused |
| `Ctrl+Z` | Undo last action | Global |

### Completion Criteria
- [ ] All shortcuts in target list functional
- [ ] Visual selection state for tasks
- [ ] Keyboard navigation through task list
- [ ] Shortcuts help modal accessible via `?`
- [ ] Keyboard hints shown in UI
- [ ] Shortcuts disabled when typing in inputs

### Files to Create/Update
```
assets/js/keyboard-shortcuts.js (new)
templates/components/keyboard-shortcuts-modal.html.twig (new)
templates/base.html.twig (update - include modal)
templates/components/task-card.html.twig (update - add data-task-id)
```

---

## Sub-Phase 8.3: Mobile Responsiveness Enhancements

### Status: MOSTLY COMPLETE, POLISH NEEDED

### Tasks

- [ ] **8.3.1** Add swipe gestures for task actions
  ```javascript
  // assets/js/swipe-gestures.js
  // Using a lightweight swipe detection library or custom implementation

  function initSwipeGestures() {
      const taskCards = document.querySelectorAll('.task-card[data-task-id]');

      taskCards.forEach(card => {
          let startX, startY, distX, distY;
          const threshold = 100; // minimum distance for swipe
          const restraint = 100; // maximum perpendicular distance

          card.addEventListener('touchstart', (e) => {
              const touch = e.changedTouches[0];
              startX = touch.pageX;
              startY = touch.pageY;
          });

          card.addEventListener('touchend', (e) => {
              const touch = e.changedTouches[0];
              distX = touch.pageX - startX;
              distY = touch.pageY - startY;

              if (Math.abs(distX) >= threshold && Math.abs(distY) <= restraint) {
                  if (distX > 0) {
                      // Swipe right: Complete task
                      completeTask(card.dataset.taskId);
                  } else {
                      // Swipe left: Show action menu
                      showTaskActionMenu(card.dataset.taskId);
                  }
              }
          });
      });
  }

  // Visual feedback during swipe
  function showSwipeIndicator(card, direction, progress) {
      const indicator = card.querySelector('.swipe-indicator') || createSwipeIndicator(card);

      if (direction === 'right') {
          indicator.style.background = 'rgb(34, 197, 94)'; // green-500
          indicator.innerHTML = '<svg>...</svg> Complete';
      } else {
          indicator.style.background = 'rgb(107, 114, 128)'; // gray-500
          indicator.innerHTML = '<svg>...</svg> Actions';
      }

      indicator.style.width = `${Math.min(progress * 100, 100)}%`;
  }
  ```

- [ ] **8.3.2** Implement bottom navigation bar for mobile
  ```twig
  {# templates/partials/bottom-nav.html.twig #}
  <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200
              sm:hidden z-40 safe-area-bottom">
      <div class="flex justify-around items-center h-16">
          <a href="{{ path('app_today') }}"
             class="flex flex-col items-center px-4 py-2 text-gray-500
                    {{ app.request.pathInfo starts with '/today' ? 'text-indigo-600' : '' }}">
              <svg class="w-6 h-6">...</svg>
              <span class="text-xs mt-1">Today</span>
          </a>

          <a href="{{ path('app_inbox') }}"
             class="flex flex-col items-center px-4 py-2 text-gray-500">
              <svg class="w-6 h-6">...</svg>
              <span class="text-xs mt-1">Inbox</span>
          </a>

          {# Quick Add Button (Center, Prominent) #}
          <button @click="$dispatch('open-quick-add')"
                  class="flex items-center justify-center w-14 h-14 -mt-4
                         bg-indigo-600 text-white rounded-full shadow-lg">
              <svg class="w-6 h-6">...</svg>
          </button>

          <a href="{{ path('app_projects') }}"
             class="flex flex-col items-center px-4 py-2 text-gray-500">
              <svg class="w-6 h-6">...</svg>
              <span class="text-xs mt-1">Projects</span>
          </a>

          <a href="{{ path('app_more') }}"
             class="flex flex-col items-center px-4 py-2 text-gray-500">
              <svg class="w-6 h-6">...</svg>
              <span class="text-xs mt-1">More</span>
          </a>
      </div>
  </nav>

  {# Add padding to main content to account for bottom nav #}
  <style>
      @media (max-width: 640px) {
          main { padding-bottom: 5rem; }
      }
      .safe-area-bottom { padding-bottom: env(safe-area-inset-bottom); }
  </style>
  ```

- [ ] **8.3.3** Optimize touch targets
  ```css
  /* Ensure all interactive elements meet 44x44px minimum */
  @media (max-width: 640px) {
      .task-checkbox { min-width: 44px; min-height: 44px; }
      .task-action-btn { min-width: 44px; min-height: 44px; }
      .dropdown-item { min-height: 44px; }
  }
  ```

- [ ] **8.3.4** Add pull-to-refresh functionality
  ```javascript
  // Simple pull-to-refresh for task lists
  let touchStartY = 0;
  let isPulling = false;

  document.addEventListener('touchstart', (e) => {
      if (window.scrollY === 0) {
          touchStartY = e.touches[0].clientY;
      }
  });

  document.addEventListener('touchmove', (e) => {
      if (touchStartY && e.touches[0].clientY - touchStartY > 60) {
          isPulling = true;
          showPullToRefreshIndicator();
      }
  });

  document.addEventListener('touchend', () => {
      if (isPulling) {
          refreshTaskList();
      }
      touchStartY = 0;
      isPulling = false;
      hidePullToRefreshIndicator();
  });
  ```

- [ ] **8.3.5** Verify touch-optimized date picker
  ```twig
  {# Ensure date picker is touch-friendly #}
  <input type="date"
         class="w-full text-base py-3 px-4 rounded-md border-gray-300
                focus:ring-indigo-500 focus:border-indigo-500"
         pattern="\d{4}-\d{2}-\d{2}">
  ```

### Completion Criteria
- [ ] Swipe right completes task with visual feedback
- [ ] Swipe left shows action menu
- [ ] Bottom navigation visible on mobile only
- [ ] Quick add accessible from bottom nav
- [ ] All touch targets meet 44x44px minimum
- [ ] Pull-to-refresh functional
- [ ] Date picker touch-optimized

### Files to Create/Update
```
assets/js/swipe-gestures.js (new)
templates/partials/bottom-nav.html.twig (new)
templates/base.html.twig (update - include bottom nav)
assets/css/mobile.css (new or update app.css)
```

---

## Sub-Phase 8.4: Performance Optimization

### Status: PARTIALLY COMPLETE

### Tasks

- [ ] **8.4.1** Analyze and optimize slow queries
  ```sql
  -- Run EXPLAIN ANALYZE on common queries

  -- Task list with filters
  EXPLAIN ANALYZE
  SELECT * FROM tasks
  WHERE owner_id = :ownerId
    AND status = 'pending'
    AND due_date <= :today
  ORDER BY priority DESC, due_date ASC
  LIMIT 20;

  -- Project tree
  EXPLAIN ANALYZE
  SELECT * FROM projects
  WHERE owner_id = :ownerId
    AND is_archived = false
  ORDER BY position;

  -- Verify index usage
  SELECT indexrelname, idx_scan, idx_tup_read
  FROM pg_stat_user_indexes
  WHERE schemaname = 'public';
  ```

- [ ] **8.4.2** Add composite indexes for common filter combinations
  ```php
  // migrations/VersionXXX_CompositeIndexes.php

  // Index for today view (owner + status + due_date)
  $this->addSql('CREATE INDEX idx_tasks_owner_status_due
                 ON tasks (owner_id, status, due_date)
                 WHERE status != \'completed\'');

  // Index for priority sorting within status
  $this->addSql('CREATE INDEX idx_tasks_owner_status_priority
                 ON tasks (owner_id, status, priority DESC, due_date)');

  // Partial index for overdue tasks (high-value query)
  $this->addSql('CREATE INDEX idx_tasks_overdue
                 ON tasks (owner_id, due_date)
                 WHERE status != \'completed\' AND due_date < CURRENT_DATE');
  ```

- [ ] **8.4.3** Optimize N+1 queries with eager loading
  ```php
  // Ensure repository methods use proper joins

  // TaskRepository - load project and tags in single query
  public function findForListView(User $owner, TaskFilterRequest $filter): array
  {
      return $this->createQueryBuilder('t')
          ->select('t', 'p', 'tags')
          ->leftJoin('t.project', 'p')
          ->leftJoin('t.tags', 'tags')
          ->where('t.owner = :owner')
          ->setParameter('owner', $owner)
          // ... filters
          ->getQuery()
          ->getResult();
  }
  ```

- [ ] **8.4.4** Implement response compression
  ```yaml
  # config/packages/framework.yaml
  framework:
      # Enable gzip compression for API responses
      http_client:
          default_options:
              headers:
                  Accept-Encoding: 'gzip, deflate'
  ```

- [ ] **8.4.5** Add cache headers for static responses
  ```php
  // For responses that don't change frequently
  #[Cache(maxage: 60, public: false, mustRevalidate: true)]
  public function getProjectTree(): JsonResponse
  {
      // ...
  }
  ```

- [ ] **8.4.6** Profile and optimize critical paths
  ```php
  // Add timing metrics to service methods (dev environment)

  use Symfony\Component\Stopwatch\Stopwatch;

  public function __construct(private readonly Stopwatch $stopwatch) {}

  public function findTasksWithFilters(...): array
  {
      $this->stopwatch->start('tasks.findWithFilters');
      try {
          // ... query logic
      } finally {
          $event = $this->stopwatch->stop('tasks.findWithFilters');
          // Log if > 100ms
          if ($event->getDuration() > 100) {
              $this->logger->warning('Slow query: tasks.findWithFilters', [
                  'duration_ms' => $event->getDuration(),
                  'memory_mb' => $event->getMemory() / 1024 / 1024,
              ]);
          }
      }
  }
  ```

### Performance Targets

| Operation | Target | Current |
|-----------|--------|---------|
| Task list (20 items) | < 50ms | TBD |
| Task list with filters | < 100ms | TBD |
| Full-text search | < 100ms | TBD |
| Project tree | < 50ms | TBD (cached) |
| Task create | < 100ms | TBD |
| Task update | < 100ms | TBD |

### Completion Criteria
- [ ] All common queries use appropriate indexes
- [ ] No N+1 queries in list views
- [ ] Response times meet targets
- [ ] Slow query logging configured
- [ ] Cache headers set appropriately

### Files to Create/Update
```
migrations/VersionXXX_CompositeIndexes.php (new)
src/Repository/TaskRepository.php (update)
config/packages/cache.yaml (update)
```

---

## Sub-Phase 8.5: Test Coverage & Quality

### Status: GOOD FOUNDATION, GAPS TO FILL

### Current Coverage Summary
- Unit tests: ~50 files covering services, DTOs, entities, parsers
- Functional tests: ~30 files covering API endpoints
- Integration tests: ~10 files for repositories and cache
- Total: 93 test classes, 450+ test methods

### Tasks

- [ ] **8.5.1** Add missing unit tests

  **TaskStateService tests (dueTime)**:
  ```php
  // tests/Unit/Service/TaskStateServiceTest.php
  public function testSerializeTaskStateIncludesDueTime(): void
  {
      $task = new Task();
      $task->setTitle('Test');
      $task->setDueTime(new \DateTimeImmutable('14:30:00'));

      $state = $this->service->serializeTaskState($task);

      $this->assertArrayHasKey('dueTime', $state);
      $this->assertEquals('14:30:00', $state['dueTime']);
  }

  public function testApplyStateToTaskRestoresDueTime(): void
  {
      $task = new Task();
      $state = ['dueTime' => '14:30:00'];

      $this->service->applyStateToTask($task, $state);

      $this->assertEquals('14:30:00', $task->getDueTime()->format('H:i:s'));
  }
  ```

  **Recurring task undo tests**:
  ```php
  // tests/Unit/Service/TaskUndoServiceTest.php
  public function testUndoCompletionDeletesNextTask(): void;
  public function testUndoCompletionPreservesModifiedNextTask(): void;
  public function testUndoCompletionPreservesCompletedNextTask(): void;
  ```

- [ ] **8.5.2** Add edge case tests

  ```php
  // tests/Unit/Entity/TaskTest.php
  public function testSubtaskCannotBeItsOwnParent(): void;
  public function testSubtaskDepthLimit(): void;

  // tests/Unit/Service/ProjectServiceTest.php
  public function testArchiveProjectWithSubprojects(): void;
  public function testDeleteProjectCascadesToTasks(): void;

  // tests/Functional/Api/TaskApiTest.php
  public function testCreateTaskWithInvalidProjectId(): void;
  public function testUpdateTaskToInvalidStatus(): void;
  public function testBatchOperationPartialFailure(): void;
  ```

- [ ] **8.5.3** Add performance assertion tests
  ```php
  // tests/Performance/QueryPerformanceTest.php

  public function testTaskListQueryUnder50ms(): void
  {
      $this->loadFixtures([TaskFixtures::class]); // 1000 tasks

      $start = microtime(true);
      $tasks = $this->taskRepository->findByOwner($this->user, new TaskFilterRequest());
      $duration = (microtime(true) - $start) * 1000;

      $this->assertLessThan(50, $duration, "Task list query took {$duration}ms");
  }

  public function testSearchQueryUnder100ms(): void
  {
      $start = microtime(true);
      $results = $this->taskRepository->search($this->user, 'meeting');
      $duration = (microtime(true) - $start) * 1000;

      $this->assertLessThan(100, $duration, "Search query took {$duration}ms");
  }
  ```

- [ ] **8.5.4** Generate code coverage report
  ```bash
  # Add to composer.json scripts
  "coverage": "XDEBUG_MODE=coverage php bin/phpunit --coverage-html var/coverage --coverage-clover var/coverage/clover.xml"

  # Run coverage
  composer coverage
  ```

- [ ] **8.5.5** Add mutation testing (optional)
  ```bash
  # Install infection
  composer require --dev infection/infection

  # Run mutation tests on critical services
  vendor/bin/infection --filter=TaskService,ProjectService,UndoService
  ```

### Coverage Targets

| Component | Target | Priority |
|-----------|--------|----------|
| TaskService | 90% | Critical |
| ProjectService | 90% | Critical |
| UndoService | 95% | Critical |
| TaskUndoService | 95% | Critical |
| Repositories | 80% | High |
| Controllers | 70% | Medium |
| DTOs | 80% | High |
| Entities | 85% | High |

### Completion Criteria
- [ ] All critical services have 90%+ coverage
- [ ] Edge cases documented and tested
- [ ] Performance tests pass
- [ ] Coverage report generated
- [ ] No regressions in existing tests

### Files to Create/Update
```
tests/Unit/Service/TaskStateServiceTest.php (update)
tests/Unit/Service/TaskUndoServiceTest.php (update)
tests/Performance/QueryPerformanceTest.php (new)
phpunit.xml.dist (update - coverage config)
composer.json (update - coverage script)
```

---

## Sub-Phase 8.6: CI/CD Pipeline

### Status: NOT IMPLEMENTED

### Tasks

- [ ] **8.6.1** Create GitHub Actions workflow for tests
  ```yaml
  # .github/workflows/tests.yml
  name: Tests

  on:
    push:
      branches: [main, develop, 'claude/**']
    pull_request:
      branches: [main, develop]

  jobs:
    test:
      runs-on: ubuntu-latest

      services:
        postgres:
          image: postgres:15
          env:
            POSTGRES_USER: todo_test
            POSTGRES_PASSWORD: todo_test
            POSTGRES_DB: todo_test
          ports:
            - 5432:5432
          options: >-
            --health-cmd pg_isready
            --health-interval 10s
            --health-timeout 5s
            --health-retries 5

        redis:
          image: redis:7
          ports:
            - 6379:6379
          options: >-
            --health-cmd "redis-cli ping"
            --health-interval 10s
            --health-timeout 5s
            --health-retries 5

      steps:
        - uses: actions/checkout@v4

        - name: Setup PHP
          uses: shivammathur/setup-php@v2
          with:
            php-version: '8.4'
            extensions: pdo_pgsql, redis, intl, mbstring, xml
            coverage: xdebug

        - name: Cache Composer dependencies
          uses: actions/cache@v4
          with:
            path: vendor
            key: ${{ runner.os }}-composer-${{ hashFiles('composer.lock') }}
            restore-keys: ${{ runner.os }}-composer-

        - name: Install dependencies
          run: composer install --prefer-dist --no-progress

        - name: Create test database
          run: |
            php bin/console doctrine:database:create --env=test --if-not-exists
            php bin/console doctrine:migrations:migrate --env=test --no-interaction

        - name: Run tests
          run: php bin/phpunit --coverage-clover coverage.xml
          env:
            DATABASE_URL: "postgresql://todo_test:todo_test@localhost:5432/todo_test?serverVersion=15"
            REDIS_URL: "redis://localhost:6379"

        - name: Upload coverage to Codecov
          uses: codecov/codecov-action@v4
          with:
            file: coverage.xml
            fail_ci_if_error: false
  ```

- [ ] **8.6.2** Add static analysis workflow
  ```yaml
  # .github/workflows/static-analysis.yml
  name: Static Analysis

  on:
    push:
      branches: [main, develop, 'claude/**']
    pull_request:
      branches: [main, develop]

  jobs:
    phpstan:
      runs-on: ubuntu-latest
      steps:
        - uses: actions/checkout@v4

        - name: Setup PHP
          uses: shivammathur/setup-php@v2
          with:
            php-version: '8.4'

        - name: Install dependencies
          run: composer install --prefer-dist --no-progress

        - name: Run PHPStan
          run: vendor/bin/phpstan analyse src tests --level=6

    php-cs-fixer:
      runs-on: ubuntu-latest
      steps:
        - uses: actions/checkout@v4

        - name: Setup PHP
          uses: shivammathur/setup-php@v2
          with:
            php-version: '8.4'

        - name: Install PHP-CS-Fixer
          run: composer global require friendsofphp/php-cs-fixer

        - name: Check code style
          run: php-cs-fixer fix --dry-run --diff
  ```

- [ ] **8.6.3** Configure PHPStan
  ```yaml
  # phpstan.neon
  parameters:
      level: 6
      paths:
          - src
          - tests
      excludePaths:
          - var/
          - vendor/
      ignoreErrors:
          - '#Parameter \#1 \$callback of method .* expects callable#'
      reportUnmatchedIgnoredErrors: false
  ```

- [ ] **8.6.4** Add PHP-CS-Fixer configuration
  ```php
  // .php-cs-fixer.php
  <?php

  $finder = PhpCsFixer\Finder::create()
      ->in(__DIR__.'/src')
      ->in(__DIR__.'/tests');

  return (new PhpCsFixer\Config())
      ->setRules([
          '@Symfony' => true,
          '@Symfony:risky' => true,
          'array_syntax' => ['syntax' => 'short'],
          'ordered_imports' => true,
          'declare_strict_types' => true,
          'final_class' => true,
          'no_unused_imports' => true,
      ])
      ->setRiskyAllowed(true)
      ->setFinder($finder);
  ```

- [ ] **8.6.5** Add deployment workflow (optional)
  ```yaml
  # .github/workflows/deploy.yml
  name: Deploy

  on:
    push:
      branches: [main]

  jobs:
    deploy-staging:
      runs-on: ubuntu-latest
      environment: staging
      steps:
        - uses: actions/checkout@v4

        # Deploy to staging server
        # This is a placeholder - actual deployment depends on infrastructure
        - name: Deploy to staging
          run: echo "Deploy to staging - configure based on infrastructure"
  ```

### Completion Criteria
- [ ] Tests run automatically on PR/push
- [ ] Code coverage uploaded to Codecov
- [ ] PHPStan passes at level 6
- [ ] Code style checked automatically
- [ ] Workflows pass on all branches

### Files to Create
```
.github/workflows/tests.yml
.github/workflows/static-analysis.yml
phpstan.neon
.php-cs-fixer.php
```

---

## Sub-Phase 8.7: UI Design System Audit

### Status: NOT STARTED

### Objective
Ensure all UI components adhere to the UI Design System (`docs/UI-DESIGN-SYSTEM.md`).

### Tasks

- [ ] **8.7.1** Audit all templates for design system compliance
  ```
  Review checklist:
  □ Colors match design tokens (indigo-600 primary, semantic colors)
  □ Typography follows scale (system font, text sizes)
  □ Spacing uses 4px base unit (Tailwind scale)
  □ Components match specifications (buttons, inputs, cards)
  □ Transitions follow standards (duration-100, duration-300)
  □ Accessibility requirements met (contrast, keyboard, sr-only labels)
  ```

- [ ] **8.7.2** Create list of deviations and fixes
  ```markdown
  # UI Audit Findings

  ## Templates Reviewed
  - [ ] base.html.twig
  - [ ] task/list.html.twig
  - [ ] task/view.html.twig
  - [ ] components/task-card.html.twig
  - [ ] components/filter-panel.html.twig
  - [ ] components/quick-add.html.twig
  - [ ] components/project-tree.html.twig
  - [ ] components/modal.html.twig
  - [ ] components/toast.html.twig

  ## Findings
  | File | Issue | Fix Required |
  |------|-------|--------------|
  | ... | ... | ... |
  ```

- [ ] **8.7.3** Fix identified inconsistencies

- [ ] **8.7.4** Update design system if patterns evolved
  - Document any intentional deviations
  - Update design system with new patterns discovered during implementation

### Completion Criteria
- [ ] All templates audited
- [ ] Inconsistencies fixed
- [ ] DEVIATIONS.md updated with intentional deviations
- [ ] UI-DESIGN-SYSTEM.md updated if patterns evolved

### Files to Update
```
templates/**/*.html.twig (various)
docs/DEVIATIONS.md (update)
docs/UI-DESIGN-SYSTEM.md (update if needed)
```

---

## Sub-Phase 8.8: Documentation Updates

### Status: PARTIAL

### Tasks

- [ ] **8.8.1** Update README with current setup instructions
  ```markdown
  # todo-me

  A self-hosted todo/task management application with REST API and web UI.

  ## Quick Start

  1. Clone the repository
  2. Copy `.env.local.example` to `.env.local` and configure
  3. Start Docker services: `docker-compose -f docker/docker-compose.yml up -d`
  4. Install dependencies: `docker-compose exec php composer install`
  5. Access at http://localhost:8080

  ## Development

  - Run tests: `php bin/phpunit`
  - Clear cache: `php bin/console cache:clear`

  ## API Documentation

  See `docs/api/` for API documentation.
  ```

- [ ] **8.8.2** Ensure API documentation is current
  - Review `docs/api/reference.md`
  - Update endpoint documentation
  - Add new endpoints from Phases 6-7

- [ ] **8.8.3** Create keyboard shortcuts documentation
  ```markdown
  # Keyboard Shortcuts

  | Shortcut | Action |
  |----------|--------|
  | `n` | New task |
  | `/` | Search |
  | ... | ... |
  ```

- [ ] **8.8.4** Update CLAUDE.md if needed
  - Ensure testing guidelines are current
  - Add any new patterns discovered

### Completion Criteria
- [ ] README is accurate and helpful
- [ ] API docs match implementation
- [ ] All features documented
- [ ] CLAUDE.md reflects current practices

---

## Phase 8 Deliverables Checklist

At the end of Phase 8, the following should be complete:

### Subtasks UI
- [ ] Subtask count on task cards
- [ ] Expandable subtask list
- [ ] Inline subtask addition
- [ ] Progress indicator

### Keyboard Shortcuts
- [ ] All target shortcuts implemented
- [ ] Task selection with keyboard navigation
- [ ] Shortcuts help modal (`?`)
- [ ] Keyboard hints in UI

### Mobile
- [ ] Swipe gestures functional
- [ ] Bottom navigation bar
- [ ] Touch targets optimized (44x44px)
- [ ] Pull-to-refresh (optional)

### Performance
- [ ] Query optimization verified
- [ ] Composite indexes added
- [ ] No N+1 queries
- [ ] Performance targets met
- [ ] Slow query logging

### Testing
- [ ] Missing unit tests added
- [ ] Edge cases covered
- [ ] Performance tests pass
- [ ] Code coverage >= 80%

### CI/CD
- [ ] GitHub Actions for tests
- [ ] Static analysis (PHPStan)
- [ ] Code style checking
- [ ] Coverage reporting

### Documentation
- [ ] README updated
- [ ] API docs current
- [ ] Keyboard shortcuts documented
- [ ] UI audit completed

---

## Implementation Order Recommendation

1. **Phase 7 Remaining Items** (dependency):
   - Add dueTime to TaskStateService
   - Recurring task undo cleanup
   - Toast notifications

2. **Sub-Phase 8.6**: CI/CD Pipeline (foundational)
   - Enables automated testing for all subsequent work

3. **Sub-Phase 8.5**: Test Coverage
   - Fill gaps before making changes

4. **Sub-Phase 8.4**: Performance Optimization
   - May require index migrations

5. **Sub-Phase 8.2**: Keyboard Shortcuts
   - High user value, independent of other work

6. **Sub-Phase 8.3**: Mobile Enhancements
   - Swipe gestures and bottom nav

7. **Sub-Phase 8.1**: Subtasks UI
   - Backend complete, UI polish

8. **Sub-Phase 8.7**: UI Design System Audit
   - Final polish pass

9. **Sub-Phase 8.8**: Documentation Updates
   - Final documentation sync

---

## Dependencies

```
Phase 7 Remaining ──► Sub-Phase 8.5 (Tests)
                           │
                           ▼
                    Sub-Phase 8.6 (CI/CD)
                           │
         ┌─────────────────┼─────────────────┐
         ▼                 ▼                 ▼
    Sub-Phase 8.2    Sub-Phase 8.4    Sub-Phase 8.3
    (Keyboard)       (Performance)    (Mobile)
         │                 │                 │
         └────────┬────────┴────────┬────────┘
                  ▼                 ▼
           Sub-Phase 8.1      Sub-Phase 8.7
           (Subtasks UI)      (UI Audit)
                  │                 │
                  └────────┬────────┘
                           ▼
                    Sub-Phase 8.8
                    (Documentation)
```

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Performance optimization causes regressions | Medium | High | Run full test suite after each change |
| Swipe gestures conflict with scroll | Medium | Medium | Test on multiple devices, add threshold |
| Keyboard shortcuts conflict with browser | Low | Medium | Use standard conventions, test across browsers |
| CI/CD setup delays | Low | Low | Start with simple workflow, iterate |
| Coverage target too ambitious | Low | Medium | Prioritize critical paths over coverage percentage |

---

## Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Test coverage | >= 80% | Codecov report |
| CI build time | < 5 minutes | GitHub Actions |
| Task list load time | < 50ms | Performance tests |
| Search response time | < 100ms | Performance tests |
| Mobile Lighthouse score | >= 90 | Lighthouse audit |
| Keyboard accessibility | Full | Manual audit |

# Phase 4: Views & Filtering

## Overview
**Duration**: Week 4-5  
**Goal**: Implement specialized task views (Today, Upcoming, Overdue), comprehensive filtering system, sorting capabilities, and the filter UI components.

## Prerequisites
- Phases 1-3 completed
- Task and Project entities functional
- Project hierarchy working

---

## Core Design Decisions

### User Ownership Scoping (CRITICAL)
```
ALL QUERIES MUST BE SCOPED BY USER_ID

Every task query in this phase MUST:
1. Include user_id in WHERE clause
2. Verify user ownership before returning data
3. Never leak data from other users

This applies to:
- All specialized view endpoints (today, upcoming, overdue, no-date)
- All filter queries
- All saved filter operations
- Task counts in responses

Implementation:
- Repository methods receive User parameter
- QueryBuilder always starts with: ->where('t.user_id = :userId')
- Controllers get authenticated user from Security component
- Tests verify user isolation
```

### Standard API Parameters for All Endpoints
```
All task-listing endpoints (including specialized views) MUST support:

PAGINATION:
- page: int (default: 1)
- per_page: int (default: 20, max: 100)

Response meta MUST include:
- current_page
- per_page
- total
- total_pages

INCLUDE EXPANSIONS:
- include: comma-separated list
- Supported: project, tags, subtasks, user
- Example: ?include=project,tags

SORTING:
- sort: field name(s)
- sort_order: asc|desc
- Specialized views have DEFAULT sorts but accept overrides

This standardization ensures consistent API behavior.
```

### Sorting Behavior for Specialized Views
```
SORTING DEFAULTS VS OVERRIDES:

Specialized views apply DEFAULT sorts appropriate to their purpose,
but clients CAN override with any valid sort field.

Today View:
- Default: overdue first, then priority DESC, then due_time ASC
- Override: ?sort=title&sort_order=asc

Upcoming View:
- Default: due_date ASC, then priority DESC
- Override: ?sort=priority&sort_order=desc

Overdue View:
- Default: due_date ASC (oldest/most overdue first)
- Override: ?sort=priority&sort_order=desc

No-Date View:
- Default: priority DESC, then created_at DESC
- Override: Any valid sort field

ALL View:
- Default: grouped by project, then position ASC
- Override: ?sort=due_date&sort_order=asc (disables grouping)

RATIONALE:
- Defaults provide useful out-of-box experience
- Overrides give power users flexibility
- Architecture requires full sorting support on all views
```

---

## Sub-Phase 4.1: Today View Implementation

### Objective
Create the Today view showing tasks due today and overdue tasks.

### Tasks

- [ ] **4.1.1** Create Today query in TaskRepository
  ```php
  // src/Repository/TaskRepository.php
  
  /**
   * CRITICAL: Query is scoped by user_id
   */
  public function findTodayTasks(
      User $user, 
      TaskFilterCriteria $filters,
      TaskSortCriteria $sort,
      int $page = 1,
      int $perPage = 20
  ): PaginatedResult {
      // Returns tasks where:
      // - user_id = $user->getId()  // OWNERSHIP CHECK
      // - due_date <= end of today (user's timezone)
      // - status != 'completed'
      // Default sort: overdue first, then priority DESC, then due_time ASC
  }
  ```

- [ ] **4.1.2** Create Today API endpoint
  ```php
  GET /api/v1/tasks/today
  
  Query params:
  // Standard pagination (REQUIRED on all endpoints)
  - page: int (default: 1)
  - per_page: int (default: 20, max: 100)
  
  // Include expansions (REQUIRED on all endpoints)
  - include: project,tags,subtasks
  
  // Additional filtering
  - project_id: int|int[]
  - tag: string|string[]
  - tag_match: any|all
  - priority: int
  - priority_min: int
  - priority_max: int
  - include_archived_projects: bool (default: false)
  
  // Sorting (override defaults)
  - sort: due_date|priority|created_at|updated_at|title
  - sort_order: asc|desc
  
  Response:
  {
    "data": [
      {
        "id": 123,
        "title": "Review document",
        "is_overdue": true,
        "overdue_days": 2,
        "project": { "id": 5, "name": "Work" },  // if include=project
        "tags": [{ "id": 1, "name": "urgent" }], // if include=tags
        ...
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 20,
      "total": 8,
      "total_pages": 1,
      "overdue_count": 5,
      "today_count": 3
    },
    "links": {
      "self": "/api/v1/tasks/today?page=1",
      "first": "/api/v1/tasks/today?page=1",
      "last": "/api/v1/tasks/today?page=1",
      "prev": null,
      "next": null
    }
  }
  ```

- [ ] **4.1.3** Create Today view template
  ```twig
  {# templates/task/today.html.twig #}
  
  Layout:
  - Header: "Today" with date
  - Overdue section (collapsed by default if many)
  - Today section
  - Visual distinction for overdue (red text/icon)
  ```

- [ ] **4.1.4** Add overdue visual indicators
  ```css
  /* Overdue task styling */
  .task-overdue {
    border-left: 3px solid #e74c3c;
  }
  .task-overdue .due-date {
    color: #e74c3c;
  }
  ```

### Completion Criteria
- [ ] Today endpoint returns correct tasks
- [ ] User ownership enforced (user only sees own tasks)
- [ ] Pagination working with correct meta
- [ ] Include expansions working (project, tags, subtasks)
- [ ] Overdue tasks included
- [ ] Default sorting applied (overdue, priority, time)
- [ ] Sort override working
- [ ] Additional filters applicable
- [ ] UI displays with visual overdue indicators

### Files to Create/Update
```
src/Repository/TaskRepository.php (updated)
src/Controller/Api/TaskController.php (updated)
src/Controller/Web/ViewController.php (new)
templates/task/today.html.twig (new)
```

---

## Sub-Phase 4.2: Upcoming View Implementation

### Objective
Create the Upcoming view showing all tasks with future due dates, grouped by time period.

### Tasks

- [ ] **4.2.1** Create Upcoming query
  ```php
  // src/Repository/TaskRepository.php
  
  /**
   * CRITICAL: Query is scoped by user_id
   */
  public function findUpcomingTasks(
      User $user,
      TaskFilterCriteria $filters,
      int $page = 1,
      int $perPage = 20
  ): PaginatedResult {
      // Returns tasks where:
      // - user_id = $user->getId()  // OWNERSHIP CHECK
      // - due_date > now (in user's timezone)
      // - status != 'completed'
      // Default sort: due_date ASC
  }
  ```

- [ ] **4.2.2** Create grouping service with user preferences
  ```php
  // src/Service/TaskGroupingService.php
  
  /**
   * Groups tasks by time period using user's timezone and start_of_week preference.
   * 
   * @param string $timezone User's timezone (e.g., 'America/Los_Angeles')
   * @param int $startOfWeek User's start_of_week preference (0=Sunday, 1=Monday, ...)
   */
  public function groupByTimePeriod(
      array $tasks, 
      string $timezone,
      int $startOfWeek = 0
  ): array {
      // Week boundaries use user's start_of_week preference
      // "this_week" = from today to end of current week (exclusive of next week start)
      // "next_week" = 7 days starting from next week's start day
      
      return [
          'today' => [...],
          'tomorrow' => [...],
          'this_week' => [...],    // Rest of current week (respects start_of_week)
          'next_week' => [...],    // All of next week (respects start_of_week)
          'this_month' => [...],   // Rest of current month (after next_week)
          'later' => [...],        // Everything else
      ];
  }
  
  /**
   * WEEK BOUNDARY CALCULATION:
   * 
   * Given start_of_week = 1 (Monday):
   * - If today is Wednesday Jan 22, 2026
   * - this_week = Jan 22-25 (Wed-Sat)
   * - next_week = Jan 26 - Feb 1 (Sun is end, Mon starts new week)
   * 
   * Given start_of_week = 0 (Sunday):
   * - If today is Wednesday Jan 22, 2026
   * - this_week = Jan 22-24 (Wed-Sat)
   * - next_week = Jan 25-31 (Sun-Sat)
   * 
   * EDGE CASES:
   * - Month rollover: handled naturally by date math
   * - Year rollover: handled naturally by date math
   * - Leap days: handled by Carbon/DateTime
   * - DST transitions: use timezone-aware date math
   */
  ```

- [ ] **4.2.3** Create Upcoming API endpoint
  ```php
  GET /api/v1/tasks/upcoming
  
  Query params:
  // Standard pagination
  - page: int (default: 1)
  - per_page: int (default: 20, max: 100)
  
  // Include expansions
  - include: project,tags,subtasks
  
  // Additional filtering
  - project_id: int|int[]
  - tag: string|string[]
  - priority_min: int
  - priority_max: int
  
  // Sorting (override default due_date ASC)
  - sort: due_date|priority|created_at|title
  - sort_order: asc|desc
  
  // Grouping control
  - grouped: bool (default: true)
  
  Response (grouped=true):
  {
    "data": {
      "today": [...],
      "tomorrow": [...],
      "this_week": [...],
      "next_week": [...],
      "this_month": [...],
      "later": [...]
    },
    "meta": {
      "current_page": 1,
      "per_page": 20,
      "total": 45,
      "total_pages": 3,
      "group_counts": {
        "today": 2,
        "tomorrow": 5,
        "this_week": 10,
        "next_week": 15,
        "this_month": 8,
        "later": 5
      }
    }
  }
  
  Response (grouped=false):
  {
    "data": [...],  // Flat array, paginated
    "meta": { ... }
  }
  ```

- [ ] **4.2.4** Create Upcoming view template
  ```twig
  {# templates/task/upcoming.html.twig #}
  
  Layout:
  - Grouped sections with collapsible headers
  - Date headers showing "Tomorrow", "Mon, Jan 27", etc.
  - Week/month boundaries visually marked
  ```

### Completion Criteria
- [ ] Upcoming endpoint returns grouped tasks
- [ ] User ownership enforced
- [ ] Pagination working
- [ ] Include expansions working
- [ ] Grouping logic correct for all periods
- [ ] start_of_week preference respected for week boundaries
- [ ] Timezone-aware grouping
- [ ] Sort override working
- [ ] UI shows collapsible sections

### Files to Create
```
src/Service/TaskGroupingService.php (new)
src/Controller/Api/TaskController.php (updated)
templates/task/upcoming.html.twig (new)
```

---

## Sub-Phase 4.3: Overdue View Implementation

### Objective
Create dedicated Overdue view with urgency-based sorting.

### Tasks

- [ ] **4.3.1** Create Overdue query
  ```php
  // src/Repository/TaskRepository.php
  
  /**
   * CRITICAL: Query is scoped by user_id
   */
  public function findOverdueTasks(
      User $user,
      TaskFilterCriteria $filters,
      TaskSortCriteria $sort,
      int $page = 1,
      int $perPage = 20
  ): PaginatedResult {
      // Returns tasks where:
      // - user_id = $user->getId()  // OWNERSHIP CHECK
      // - due_date < start of today (user's timezone)
      // - status != 'completed'
      // Default sort: due_date ASC (oldest/most overdue first)
  }
  ```

- [ ] **4.3.2** Create Overdue API endpoint
  ```php
  GET /api/v1/tasks/overdue
  
  Query params:
  // Standard pagination
  - page: int (default: 1)
  - per_page: int (default: 20, max: 100)
  
  // Include expansions
  - include: project,tags,subtasks
  
  // Additional filtering
  - project_id: int|int[]
  - tag: string|string[]
  - priority_min: int
  - priority_max: int
  - severity: low|medium|high (filter by overdue severity)
  
  // Sorting (override default)
  - sort: overdue_days|due_date|priority|created_at|title
  - sort_order: asc|desc
  
  Response:
  {
    "data": [
      {
        "id": 123,
        "title": "Task",
        "due_date": "2026-01-20",
        "overdue_days": 3,
        "overdue_severity": "medium",  // low: 1-2 days, medium: 3-7, high: 7+
        "project": { ... },  // if include=project
        "tags": [ ... ]      // if include=tags
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 20,
      "total": 12,
      "total_pages": 1,
      "severity_counts": {
        "low": 3,
        "medium": 5,
        "high": 4
      }
    }
  }
  ```

- [ ] **4.3.3** Create severity classification
  ```php
  // src/Service/OverdueService.php
  
  public function calculateSeverity(DateTimeInterface $dueDate): string
  {
      $days = $this->getOverdueDays($dueDate);
      if ($days <= 2) return 'low';
      if ($days <= 7) return 'medium';
      return 'high';
  }
  ```

- [ ] **4.3.4** Create Overdue view template
  ```twig
  {# templates/task/overdue.html.twig #}
  
  Layout:
  - Header with count
  - Severity grouping (optional)
  - Visual urgency indicators (color intensity)
  - "Reschedule all to tomorrow" bulk action
  ```

### Completion Criteria
- [ ] Overdue endpoint returns correct tasks
- [ ] User ownership enforced
- [ ] Pagination working
- [ ] Include expansions working
- [ ] Severity calculated correctly
- [ ] Sorting by overdue days works
- [ ] Sort override working
- [ ] UI shows urgency visually

### Files to Create
```
src/Service/OverdueService.php (new)
templates/task/overdue.html.twig (new)
```

---

## Sub-Phase 4.4: All Tasks View & No-Date View

### Objective
Create the ALL tasks view (non-completed tasks grouped by project) and a view for tasks without due dates.

### Tasks

- [ ] **4.4.1** Create ALL view query
  ```php
  // src/Repository/TaskRepository.php
  
  /**
   * CRITICAL: Query is scoped by user_id
   * 
   * ALL VIEW SHOWS NON-COMPLETED TASKS ONLY by default.
   * Completed tasks are excluded unless status filter explicitly includes them.
   */
  public function findAllTasksGroupedByProject(
      User $user,
      TaskFilterCriteria $filters,
      int $page = 1,
      int $perPage = 20
  ): PaginatedResult {
      // Returns tasks where:
      // - user_id = $user->getId()  // OWNERSHIP CHECK
      // - status != 'completed' (unless explicitly filtered)
      // - NOT in archived projects (unless include_archived_projects=true)
      // Grouped by project (including "No Project" group)
      // Sorted by project position, then task position
  }
  ```

- [ ] **4.4.2** Create No-Date query
  ```php
  /**
   * CRITICAL: Query is scoped by user_id
   */
  public function findTasksWithoutDueDate(
      User $user,
      TaskFilterCriteria $filters,
      TaskSortCriteria $sort,
      int $page = 1,
      int $perPage = 20
  ): PaginatedResult {
      // Returns tasks where:
      // - user_id = $user->getId()  // OWNERSHIP CHECK
      // - due_date IS NULL
      // - status != 'completed'
      // Default sort: priority DESC, created_at DESC
  }
  ```

- [ ] **4.4.3** Create endpoints
  ```php
  GET /api/v1/tasks/no-date
  
  Query params:
  // Standard pagination
  - page: int (default: 1)
  - per_page: int (default: 20, max: 100)
  
  // Include expansions
  - include: project,tags,subtasks
  
  // Additional filtering
  - project_id: int|int[]
  - tag: string|string[]
  - priority_min: int
  - priority_max: int
  - status: string|string[] (can include 'completed' to see completed no-date tasks)
  
  // Sorting (override default priority DESC)
  - sort: priority|created_at|updated_at|title
  - sort_order: asc|desc
  
  // For ALL view, use standard endpoint with grouping:
  GET /api/v1/tasks?group_by=project&status=pending,in_progress
  
  // To include completed tasks:
  GET /api/v1/tasks?group_by=project&status=pending,in_progress,completed
  ```

- [ ] **4.4.4** Create view templates
  ```twig
  {# templates/task/all.html.twig #}
  - Tasks grouped by project
  - Visual project separators with project color
  - "No Project" group at end
  - Click project header to filter
  - NOTE: Completed tasks hidden by default
  
  {# templates/task/no-date.html.twig #}
  - List of tasks without dates
  - Quick date picker to assign dates
  - Sorted by priority by default
  ```

### Completion Criteria
- [ ] ALL view shows non-completed tasks by default
- [ ] ALL view excludes tasks from archived projects by default
- [ ] User ownership enforced
- [ ] Pagination working
- [ ] Include expansions working
- [ ] No-date view shows tasks without due dates
- [ ] Project grouping visual and functional

### Files to Create
```
templates/task/all.html.twig (new)
templates/task/no-date.html.twig (new)
```

---

## Sub-Phase 4.5: Comprehensive Filter System (Backend)

### Objective
Implement all filter parameters for the task list API with project hierarchy support.

### Tasks

- [ ] **4.5.1** Create FilterBuilder service
  ```php
  // src/Service/Filter/TaskFilterBuilder.php
  
  Supported filters:
  - status: string|array ('pending', 'in_progress', 'completed')
  - project_id: int|array
  - exclude_project_id: int
  - parent_task_id: int|null (for subtasks)
  - tag: string|array
  - tag_match: 'any'|'all' (OR vs AND logic)
  - priority: int (exact match)
  - priority_min: int
  - priority_max: int
  - due_before: ISO date
  - due_after: ISO date
  - due_on: ISO date
  - completed_before: ISO date
  - completed_after: ISO date
  - completed_on: ISO date
  - overdue: bool
  - today: bool
  - upcoming: bool
  - no_due_date: bool
  - is_recurring: bool
  - original_task_id: int (recurring chain)
  - search: string (full-text)
  - include_archived_projects: bool (default: false)
  ```

- [ ] **4.5.2** Implement project hierarchy in filtering
  ```php
  PROJECT HIERARCHY FILTERING:
  
  When project_id filter is applied:
  
  1. Check the project's show_children_tasks setting
  2. If show_children_tasks = true (default):
     - Include tasks from the project AND all descendant projects
     - Respects include_archived_projects for descendants
  3. If show_children_tasks = false:
     - Include only tasks directly assigned to the project
  
  API override:
  - ?project_id=5&include_project_children=true  // Force include descendants
  - ?project_id=5&include_project_children=false // Force exclude descendants
  - ?project_id=5  // Use project's show_children_tasks setting
  
  Multi-select behavior:
  - ?project_id[]=5&project_id[]=10
  - Each project's children are included based on its own show_children_tasks
  - Or use include_project_children to override all
  
  Implementation:
  public function applyProjectFilter(
      QueryBuilder $qb, 
      array $projectIds,
      ?bool $includeChildren = null,
      bool $includeArchived = false
  ): void {
      $allProjectIds = [];
      
      foreach ($projectIds as $projectId) {
          $project = $this->projectRepository->find($projectId);
          
          // Determine whether to include children
          $includeDescendants = $includeChildren ?? $project->getShowChildrenTasks();
          
          if ($includeDescendants) {
              $descendantIds = $this->projectRepository->getDescendantIds($project);
              if (!$includeArchived) {
                  $descendantIds = $this->filterOutArchived($descendantIds);
              }
              $allProjectIds = array_merge($allProjectIds, $descendantIds);
          }
          
          $allProjectIds[] = $projectId;
      }
      
      $qb->andWhere('t.project_id IN (:projectIds)')
         ->setParameter('projectIds', array_unique($allProjectIds));
  }
  ```

- [ ] **4.5.3** Implement filter parsing
  ```php
  public function buildFromRequest(Request $request): TaskFilterCriteria
  {
      // Parse query parameters
      // Validate values
      // Return structured filter criteria
  }
  ```

- [ ] **4.5.4** Implement filter application in repository
  ```php
  // src/Repository/TaskRepository.php
  
  /**
   * CRITICAL: Always scoped by user_id first
   */
  public function findByFilters(
      User $user, 
      TaskFilterCriteria $criteria,
      int $page = 1,
      int $perPage = 20
  ): PaginatedResult {
      $qb = $this->createQueryBuilder('t')
          ->where('t.user = :user')  // OWNERSHIP CHECK FIRST
          ->setParameter('user', $user);
      
      // Apply each filter to query builder
      foreach ($criteria->getFilters() as $filter) {
          $this->applyFilter($qb, $filter);
      }
      
      return $this->paginate($qb, $page, $perPage);
  }
  ```

- [ ] **4.5.5** Implement tag match logic
  ```php
  // tag_match=any (OR logic)
  // Task has tag1 OR tag2 OR tag3
  
  // tag_match=all (AND logic)
  // Task has tag1 AND tag2 AND tag3
  
  // Implementation uses subqueries for AND logic
  ```

- [ ] **4.5.6** Implement date range filters
  ```php
  // Timezone-aware date filtering
  // due_before/after: Compare against due_date
  // completed_before/after: Compare against completed_at
  // Handle null due_dates properly
  ```

### Completion Criteria
- [ ] All filters functional
- [ ] User ownership always enforced
- [ ] Project hierarchy filtering respects show_children_tasks
- [ ] include_project_children override working
- [ ] Multiple filters combinable
- [ ] Tag match logic works
- [ ] Date ranges timezone-aware
- [ ] No SQL injection vulnerabilities

### Files to Create
```
src/Service/Filter/
├── TaskFilterBuilder.php
├── TaskFilterCriteria.php
└── FilterInterface.php

src/Repository/TaskRepository.php (updated)

tests/Functional/Api/TaskFilterApiTest.php
```

---

## Sub-Phase 4.6: Sorting System

### Objective
Implement comprehensive sorting for task lists.

### Tasks

- [ ] **4.6.1** Create SortBuilder service
  ```php
  // src/Service/Sort/TaskSortBuilder.php
  
  Supported sort fields:
  - due_date
  - priority
  - created_at
  - updated_at
  - completed_at
  - title
  - manual (position field)
  - overdue_days (computed, for overdue view)
  
  Sort order: asc|desc
  ```

- [ ] **4.6.2** Implement null handling for sorting
  ```php
  // For due_date sorting:
  // asc: nulls LAST (dated tasks first)
  // desc: nulls LAST (most recent first)
  
  // Custom SQL for null handling
  ORDER BY CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC
  ```

- [ ] **4.6.3** Implement multi-field sorting
  ```php
  GET /api/v1/tasks?sort=priority,due_date&sort_order=desc,asc
  
  // Primary sort by priority DESC
  // Secondary sort by due_date ASC
  ```

- [ ] **4.6.4** Implement manual sorting with position
  ```php
  // Manual sort uses the position field
  // Supports drag-and-drop reordering
  
  PATCH /api/v1/tasks/{id}/position
  {
    "position": 5,
    "context": "project"  // 'project' or 'today' or 'global'
  }
  
  // Reposition other tasks as needed
  ```

### Completion Criteria
- [ ] All sort fields functional
- [ ] Sort order (asc/desc) works
- [ ] Null handling consistent
- [ ] Multi-field sorting works
- [ ] Manual position sorting works

### Files to Create
```
src/Service/Sort/
├── TaskSortBuilder.php
└── SortCriteria.php

tests/Functional/Api/TaskSortApiTest.php
```

---

## Sub-Phase 4.7: Filter UI Components

### Objective
Create the frontend UI for filtering and sorting tasks.

### Tasks

- [ ] **4.7.1** Create filter panel component
  ```twig
  {# templates/components/filter-panel.html.twig #}
  
  Sections:
  - Status (checkboxes: Pending, In Progress, Completed)
  - Projects (multi-select with search, shows hierarchy)
    - Checkbox: "Include sub-projects" (controls include_project_children)
  - Tags (multi-select with search)
    - Toggle: AND/OR mode (tag_match)
  - Priority (range slider or checkboxes)
  - Due date (date range picker)
  - Search input
  ```

- [ ] **4.7.2** Create JavaScript for filter management
  ```javascript
  // assets/js/filter-manager.js
  
  Features:
  - Collect filter values from UI
  - Build query string
  - Make API request
  - Update task list without page reload
  - Remember filter state (URL params or localStorage)
  ```

- [ ] **4.7.3** Create active filters display
  ```twig
  {# templates/components/active-filters.html.twig #}
  
  Layout:
  - Row of filter chips above task list
  - Each chip shows: filter type + value + remove button
  - "Clear all" button
  
  Example:
  [Status: Pending ×] [Project: Work (+ children) ×] [Tag: urgent ×] [Clear all]
  ```

- [ ] **4.7.4** Create sort dropdown component
  ```twig
  {# templates/components/sort-dropdown.html.twig #}
  
  Options:
  - Due date (nearest first)
  - Due date (furthest first)
  - Priority (high to low)
  - Priority (low to high)
  - Recently created
  - Alphabetical
  - Manual
  
  Visual indicator of current sort
  ```

- [ ] **4.7.5** Create project multi-select with hierarchy
  ```javascript
  // assets/js/project-select.js
  
  Features:
  - Searchable dropdown
  - Shows project hierarchy with indentation
  - Select parent = shows "Include children" toggle
  - Checkboxes for multi-select
  - Respects show_children_tasks default
  ```

- [ ] **4.7.6** Create tag multi-select
  ```javascript
  // assets/js/tag-select.js
  
  Features:
  - Searchable dropdown
  - Show tag colors
  - Toggle AND/OR mode (tag_match)
  - Show selected as chips
  ```

### Completion Criteria
- [ ] Filter panel renders all options
- [ ] Project filter shows hierarchy and include-children option
- [ ] Filters update task list dynamically
- [ ] Active filters displayed as chips
- [ ] Chips removable
- [ ] Sort dropdown functional
- [ ] Project/tag selects show hierarchy and colors

### Files to Create
```
templates/components/
├── filter-panel.html.twig
├── active-filters.html.twig
├── sort-dropdown.html.twig
├── project-select.html.twig
└── tag-select.html.twig

assets/js/
├── filter-manager.js
├── project-select.js
└── tag-select.js

assets/css/
└── filters.css
```

---

## Sub-Phase 4.8: Saved Filters (Custom Views)

### Objective
Allow users to save filter combinations as custom views.

### Tasks

- [ ] **4.8.1** Create SavedFilter entity
  ```php
  // src/Entity/SavedFilter.php
  
  Fields:
  - id: BIGINT
  - user_id: BIGINT FK  // OWNERSHIP
  - name: VARCHAR(100)
  - icon: VARCHAR(50)
  - color: VARCHAR(7)
  - filters: JSON (serialized filter criteria)
  - position: INTEGER
  - created_at: DATETIME
  ```

- [ ] **4.8.2** Create CRUD endpoints
  ```php
  GET /api/v1/saved-filters
  // Returns only current user's saved filters
  
  POST /api/v1/saved-filters
  {
    "name": "High Priority Work",
    "icon": "⚡",
    "color": "#f39c12",
    "filters": {
      "project_id": 5,
      "include_project_children": true,
      "priority_min": 3,
      "status": "pending"
    }
  }
  
  PATCH /api/v1/saved-filters/{id}
  // Validates user owns the saved filter
  
  DELETE /api/v1/saved-filters/{id}
  // Validates user owns the saved filter
  ```

- [ ] **4.8.3** Add saved filters to sidebar
  ```twig
  {# In sidebar, under standard views #}
  
  - Inbox
  - Today
  - Upcoming
  - Saved Filters:
    - ⚡ High Priority Work
    - 🏠 Home Tasks
    - ...
  ```

- [ ] **4.8.4** Create "Save current filters" UI
  ```javascript
  // When filters are active, show "Save" button
  // Opens modal to name the saved filter
  // Creates via API
  ```

### Completion Criteria
- [ ] Saved filters persist in database
- [ ] User ownership enforced on all operations
- [ ] Saved filters appear in sidebar
- [ ] Clicking saved filter applies its filters
- [ ] Can edit and delete saved filters

### Files to Create
```
src/Entity/SavedFilter.php
src/Repository/SavedFilterRepository.php
src/Controller/Api/SavedFilterController.php

templates/components/saved-filters.html.twig

migrations/VersionXXX_CreateSavedFilters.php
```

---

## Sub-Phase 4.9: View Tests

### Objective
Create comprehensive tests for all views and filters.

### Tasks

- [ ] **4.9.1** Today view tests
  ```php
  // tests/Functional/Api/TodayViewApiTest.php
  
  - testTodayReturnsTasksDueToday()
  - testTodayIncludesOverdueTasks()
  - testTodayExcludesCompletedTasks()
  - testTodayExcludesFutureTasks()
  - testTodayUserIsolation()  // Cannot see other users' tasks
  - testTodayPagination()
  - testTodayPaginationMeta()
  - testTodayIncludeProject()
  - testTodayIncludeTags()
  - testTodayIncludeSubtasks()
  - testTodayDefaultSort()
  - testTodaySortOverride()
  - testTodayWithAdditionalFilters()
  ```

- [ ] **4.9.2** Upcoming view tests
  ```php
  // tests/Functional/Api/UpcomingViewApiTest.php
  
  - testUpcomingGroupsByPeriod()
  - testUpcomingExcludesOverdue()
  - testUpcomingExcludesNoDateTasks()
  - testUpcomingUserIsolation()
  - testUpcomingPagination()
  - testUpcomingIncludeExpansions()
  - testUpcomingCorrectGroupBoundaries()
  - testUpcomingRespectsStartOfWeekSunday()
  - testUpcomingRespectsStartOfWeekMonday()
  - testUpcomingSortOverride()
  ```

- [ ] **4.9.3** Overdue view tests
  ```php
  // tests/Functional/Api/OverdueViewApiTest.php
  
  - testOverdueReturnsCorrectTasks()
  - testOverdueUserIsolation()
  - testOverduePagination()
  - testOverdueIncludeExpansions()
  - testOverdueSeverityCalculation()
  - testOverdueSortByOverdueDays()
  - testOverdueSortOverride()
  ```

- [ ] **4.9.4** All view and No-Date view tests
  ```php
  // tests/Functional/Api/AllViewApiTest.php
  
  - testAllViewExcludesCompletedByDefault()
  - testAllViewCanIncludeCompleted()
  - testAllViewExcludesArchivedProjects()
  - testAllViewGroupsByProject()
  - testAllViewUserIsolation()
  - testAllViewPagination()
  
  // tests/Functional/Api/NoDateViewApiTest.php
  - testNoDateReturnsTasksWithoutDueDate()
  - testNoDateExcludesCompletedByDefault()
  - testNoDateUserIsolation()
  - testNoDatePagination()
  - testNoDateDefaultSort()
  - testNoDateSortOverride()
  ```

- [ ] **4.9.5** Timezone edge case tests
  ```php
  // tests/Functional/Api/TimezoneEdgeCasesTest.php
  
  - testTodayViewAtMidnightBoundaryUTC()
  - testTodayViewAtMidnightBoundaryPST()
  - testTodayViewCrossingDateLine()
  - testUpcomingGroupingDuringDSTTransition()
  - testOverdueCalculationWithDueTimeSet()
  - testOverdueCalculationWithDueDateOnly()
  - testFilteringWithMixedTimeAndDateOnly()
  - testLongRunningQueryDoesNotShiftDays()  // Query should snapshot "today"
  ```

- [ ] **4.9.6** Filter combination tests
  ```php
  // tests/Functional/Api/TaskFilterApiTest.php
  
  - testFilterByStatus()
  - testFilterByMultipleStatuses()
  - testFilterByProject()
  - testFilterByProjectWithChildren()
  - testFilterByProjectWithoutChildren()
  - testFilterRespectsShowChildrenTasksSetting()
  - testFilterByMultipleProjects()
  - testFilterByTag()
  - testFilterByMultipleTagsAny()
  - testFilterByMultipleTagsAll()
  - testFilterByPriorityRange()
  - testFilterByDateRange()
  - testCombinedFilters()
  - testNoResultsWithImpossibleFilters()
  - testUserIsolation()  // Cannot filter to see other users' tasks
  ```

- [ ] **4.9.7** Sort tests
  ```php
  // tests/Functional/Api/TaskSortApiTest.php
  
  - testSortByDueDateAsc()
  - testSortByDueDateDesc()
  - testSortByPriority()
  - testSortWithNulls()
  - testMultiFieldSort()
  - testManualSort()
  - testSpecializedViewDefaultSort()
  - testSpecializedViewSortOverride()
  ```

- [ ] **4.9.8** Saved filter tests
  ```php
  // tests/Functional/Api/SavedFilterApiTest.php
  
  - testCreateSavedFilter()
  - testListSavedFilters()
  - testListSavedFiltersUserIsolation()  // Cannot see other users' filters
  - testApplySavedFilter()
  - testUpdateSavedFilter()
  - testUpdateSavedFilterOwnershipCheck()
  - testDeleteSavedFilter()
  - testDeleteSavedFilterOwnershipCheck()
  - testSavedFilterWithProjectHierarchy()
  ```

### Completion Criteria
- [ ] All view endpoints tested
- [ ] User isolation verified for all endpoints
- [ ] Pagination tested for all endpoints
- [ ] Include expansions tested
- [ ] Timezone edge cases tested
- [ ] Filter combinations tested
- [ ] Project hierarchy filtering tested
- [ ] Sort options tested
- [ ] Saved filter CRUD tested
- [ ] 90%+ coverage for filter services

### Files to Create
```
tests/Functional/Api/
├── TodayViewApiTest.php
├── UpcomingViewApiTest.php
├── OverdueViewApiTest.php
├── AllViewApiTest.php
├── NoDateViewApiTest.php
├── TimezoneEdgeCasesTest.php
├── TaskFilterApiTest.php
├── TaskSortApiTest.php
└── SavedFilterApiTest.php

tests/Unit/Service/
├── TaskFilterBuilderTest.php
├── TaskSortBuilderTest.php
└── TaskGroupingServiceTest.php
```

---

## Phase 4 Deliverables Checklist

At the end of Phase 4, the following should be complete:

### Core Views
- [ ] Today view showing due today + overdue
- [ ] Upcoming view with time period grouping (respects start_of_week)
- [ ] Overdue view with severity indicators
- [ ] ALL tasks view with project grouping (excludes completed by default)
- [ ] No-date view functional

### Standard API Features (All Endpoints)
- [ ] Pagination (page, per_page, total, total_pages)
- [ ] Include expansions (project, tags, subtasks)
- [ ] Sorting with defaults and overrides
- [ ] User ownership enforced on all queries

### Filtering
- [ ] All filter parameters implemented
- [ ] Project hierarchy filtering respects show_children_tasks
- [ ] include_project_children override working
- [ ] Tag match (AND/OR) logic working

### Sorting
- [ ] All sort options implemented
- [ ] Specialized views have defaults but accept overrides

### UI
- [ ] Filter UI panel complete with project hierarchy support
- [ ] Active filters displayed as chips
- [ ] Sort dropdown functional

### Saved Filters
- [ ] Saved filters CRUD working
- [ ] User ownership enforced
- [ ] Saved filters appear in sidebar

### Testing
- [ ] Comprehensive test coverage
- [ ] User isolation tests for all endpoints
- [ ] Timezone edge cases tested
- [ ] Saved filter tests complete
- [ ] Performance acceptable with many tasks

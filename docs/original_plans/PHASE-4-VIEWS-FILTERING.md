# Phase 4: Views & Filtering

## Overview
**Goal**: Implement specialized task views (Today, Upcoming, Overdue), comprehensive filtering system, sorting capabilities, and the filter UI components.

## Prerequisites
- Phases 1-3 completed
- Task and Project entities functional
- Project hierarchy working with `show_children_tasks` setting

---

## Current Implementation State

Before implementing Phase 4, note these **existing capabilities** from earlier phases:

### Already Implemented in TaskRepository
```php
// Filtering support in createFilteredQueryBuilder():
- Single status filtering
- Single priority exact match
- Single project filtering
- Due date range (dueBefore, dueAfter)
- Tag array filtering (OR logic)
- Free-text search (LIKE on title/description)
- Eager loading of project and tags

// Specialized query methods:
- findOverdueByOwner()     // Tasks with dueDate < today
- findDueSoonByOwner(days) // Tasks due within N days
- findByProjectWithChildren() // Project hierarchy support
- search()                 // PostgreSQL full-text search
```

### Current API Endpoint Parameters (GET /api/v1/tasks)
```
page, limit, status, priority, projectId, search, dueBefore, dueAfter, tagIds
```

### What Phase 4 Adds
- Specialized view endpoints (today, upcoming, overdue, no-date)
- Multiple status filtering
- Priority range (min/max)
- Tag match logic (AND/OR)
- Sorting parameters
- Project hierarchy in filters (include_project_children)
- Saved filters
- Enhanced UI components

---

## Core Design Decisions

### User Ownership Scoping (CRITICAL)
```
ALL QUERIES MUST BE SCOPED BY USER_ID

Every task query in this phase MUST:
1. Include owner in WHERE clause
2. Verify user ownership before returning data
3. Never leak data from other users

This applies to:
- All specialized view endpoints (today, upcoming, overdue, no-date)
- All filter queries
- All saved filter operations
- Task counts in responses

Implementation pattern (from Phase 3):
- Repository methods receive User parameter
- QueryBuilder always starts with: ->where('t.owner = :owner')
- Controllers get authenticated user from Security component
- Tests verify user isolation
```

### Entity ID Convention
```
ALL ENTITIES USE UUID PRIMARY KEYS

Per established architecture:
- SavedFilter entity uses UUID (not BIGINT)
- All references use UUID strings
- Follows pattern from User, Task, Project, Tag entities
```

### DTO Pattern (from Phases 1-3)
```php
// Request DTOs use static fromArray() factory
$dto = TaskFilterRequest::fromArray($data);

// Response DTOs use static fromEntity() factory
$response = TaskResponse::fromTask($task);

// Validation via Symfony Validator constraints
```

### API Response Format (from Phases 1-3)
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "requestId": "uuid",
    "timestamp": "ISO-8601",
    "total": 100,
    "page": 1,
    "limit": 20,
    "totalPages": 5
  }
}
```

### Parameter Naming Convention
```
Query Parameters: snake_case (REST convention)
- project_id, due_before, tag_ids, sort_order

Response Body: camelCase (per DEVIATIONS.md)
- projectId, dueDate, tagIds, sortOrder
```

### Standard API Parameters for All Endpoints
```
All task-listing endpoints (including specialized views) MUST support:

PAGINATION:
- page: int (default: 1)
- limit: int (default: 20, max: 100)

Response meta MUST include:
- page
- limit
- total
- totalPages

INCLUDE EXPANSIONS (future enhancement):
- include: comma-separated list
- Supported: project, tags, subtasks
- Note: Currently project and tags are always included

SORTING:
- sort: field name
- sort_order: asc|desc
- Specialized views have DEFAULT sorts but accept overrides
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
   * Returns tasks due today or overdue.
   * Builds on existing findOverdueByOwner() method.
   */
  public function findTodayTasksQueryBuilder(
      User $owner,
      array $filters = [],
      string $sort = 'priority',
      string $sortOrder = 'desc'
  ): QueryBuilder {
      $today = new \DateTimeImmutable('today');
      $endOfToday = new \DateTimeImmutable('today 23:59:59');

      $qb = $this->createQueryBuilder('t')
          ->where('t.owner = :owner')
          ->andWhere('t.dueDate <= :endOfToday')
          ->andWhere('t.status != :completed')
          ->setParameter('owner', $owner)
          ->setParameter('endOfToday', $endOfToday)
          ->setParameter('completed', Task::STATUS_COMPLETED);

      // Apply additional filters using existing pattern
      $this->applyFilters($qb, $filters);

      // Default: overdue first, then priority DESC
      if ($sort === 'default') {
          $qb->addOrderBy('CASE WHEN t.dueDate < :today THEN 0 ELSE 1 END', 'ASC')
             ->addOrderBy('t.priority', 'DESC')
             ->addOrderBy('t.dueTime', 'ASC')
             ->setParameter('today', $today);
      } else {
          $this->applySorting($qb, $sort, $sortOrder);
      }

      return $qb;
  }
  ```

- [ ] **4.1.2** Create TodayViewResponse DTO
  ```php
  // src/DTO/TodayViewResponse.php

  final readonly class TodayViewResponse
  {
      public function __construct(
          public array $items,
          public array $meta,
      ) {}

      public static function fromTasks(
          array $tasks,
          array $paginationMeta,
          int $overdueCount,
          int $todayCount
      ): self {
          return new self(
              items: array_map(
                  fn(Task $task) => TaskResponse::fromTask($task),
                  $tasks
              ),
              meta: array_merge($paginationMeta, [
                  'overdueCount' => $overdueCount,
                  'todayCount' => $todayCount,
              ]),
          );
      }
  }
  ```

- [ ] **4.1.3** Extend TaskResponse with computed overdue fields
  ```php
  // Add to src/DTO/TaskResponse.php

  public readonly bool $isOverdue,
  public readonly ?int $overdueDays,

  // In fromTask():
  $isOverdue = $task->isOverdue();
  $overdueDays = $isOverdue ? $task->getOverdueDays() : null;
  ```

- [ ] **4.1.4** Create Today API endpoint
  ```php
  // src/Controller/Api/TaskController.php (or TaskViewController.php)

  #[Route('/today', name: 'today', methods: ['GET'])]
  public function today(Request $request): JsonResponse
  {
      $user = $this->getUser();

      // Parse filter/sort/pagination parameters
      $filters = TaskFilterRequest::fromRequest($request);
      $sort = $request->query->get('sort', 'default');
      $sortOrder = $request->query->get('sort_order', 'desc');
      $page = $request->query->getInt('page', 1);
      $limit = min($request->query->getInt('limit', 20), 100);

      // Build query
      $qb = $this->taskRepository->findTodayTasksQueryBuilder(
          $user, $filters->toArray(), $sort, $sortOrder
      );

      // Get paginated results
      $result = $this->paginationHelper->paginate($qb, $page, $limit);

      // Get counts
      $overdueCount = $this->taskRepository->countOverdue($user);
      $todayCount = $this->taskRepository->countDueToday($user);

      return $this->json([
          'success' => true,
          'data' => TodayViewResponse::fromTasks(
              $result->items,
              $result->meta,
              $overdueCount,
              $todayCount
          ),
      ]);
  }
  ```

- [ ] **4.1.5** Create Today view template
  ```twig
  {# templates/task/today.html.twig #}

  {% extends 'base.html.twig' %}

  {% block title %}Today - {{ parent() }}{% endblock %}

  {% block content %}
  <div class="max-w-4xl mx-auto">
      {# Header #}
      <div class="flex items-center justify-between mb-6">
          <div>
              <h1 class="text-2xl font-bold text-gray-900">Today</h1>
              <p class="text-sm text-gray-500">{{ "now"|date("l, F j, Y") }}</p>
          </div>
          {% if overdueCount > 0 %}
              <span class="bg-red-100 text-red-800 rounded-full px-3 py-1 text-sm font-medium">
                  {{ overdueCount }} overdue
              </span>
          {% endif %}
      </div>

      {# Overdue Section (if any) #}
      {% if overdueTasks|length > 0 %}
      <div class="mb-6">
          <h2 class="text-sm font-semibold text-red-600 mb-3 flex items-center">
              <svg class="w-4 h-4 mr-2"><!-- alert icon --></svg>
              Overdue
          </h2>
          <div class="space-y-2">
              {% for task in overdueTasks %}
                  {% include 'task/_task_item.html.twig' with {task: task, showOverdue: true} %}
              {% endfor %}
          </div>
      </div>
      {% endif %}

      {# Today Section #}
      <div>
          <h2 class="text-sm font-semibold text-gray-700 mb-3">Due Today</h2>
          <div class="space-y-2">
              {% for task in todayTasks %}
                  {% include 'task/_task_item.html.twig' with {task: task} %}
              {% empty %}
                  <p class="text-gray-500 text-center py-8">No tasks due today</p>
              {% endfor %}
          </div>
      </div>
  </div>
  {% endblock %}
  ```

- [ ] **4.1.6** Add overdue visual indicators
  ```twig
  {# OVERDUE STYLING (per UI-DESIGN-SYSTEM.md): #}
  {# - Task card: border-l-4 border-red-500 (4px left border) #}
  {# - Due date text: text-red-600 font-medium #}
  {# - Overdue badge: bg-red-100 text-red-800 rounded-full px-2.5 py-0.5 text-xs font-medium #}
  {# - Calendar icon: w-4 h-4 text-red-500 #}

  {# Update task/_task_item.html.twig: #}
  <div class="task-card bg-white rounded-lg shadow p-4
              {% if task.isOverdue %}border-l-4 border-red-500{% endif %}">
      {% if task.isOverdue %}
          <span class="bg-red-100 text-red-800 rounded-full px-2.5 py-0.5 text-xs font-medium">
              {{ task.overdueDays }} day{% if task.overdueDays != 1 %}s{% endif %} overdue
          </span>
      {% endif %}
      {# ... rest of task card #}
  </div>
  ```

### Completion Criteria
- [ ] Today endpoint returns correct tasks (today + overdue)
- [ ] User ownership enforced (user only sees own tasks)
- [ ] Pagination working with correct meta
- [ ] Overdue tasks included with isOverdue/overdueDays fields
- [ ] Default sorting applied (overdue first, then priority)
- [ ] Sort override working
- [ ] Additional filters applicable
- [ ] UI displays with visual overdue indicators

### Files to Create/Update
```
src/Repository/TaskRepository.php (add findTodayTasksQueryBuilder)
src/DTO/TodayViewResponse.php (new)
src/DTO/TaskResponse.php (add isOverdue, overdueDays)
src/Controller/Api/TaskController.php (add today endpoint)
src/Controller/Web/TaskViewController.php (new, for web views)
templates/task/today.html.twig (new)
templates/task/_task_item.html.twig (update with overdue styling)
```

---

## Sub-Phase 4.2: Upcoming View Implementation

### Objective
Create the Upcoming view showing all tasks with future due dates, grouped by time period.

### Tasks

- [ ] **4.2.1** Create Upcoming query
  ```php
  // src/Repository/TaskRepository.php

  public function findUpcomingTasksQueryBuilder(
      User $owner,
      array $filters = [],
      string $sort = 'due_date',
      string $sortOrder = 'asc'
  ): QueryBuilder {
      $today = new \DateTimeImmutable('today 23:59:59');

      $qb = $this->createQueryBuilder('t')
          ->where('t.owner = :owner')
          ->andWhere('t.dueDate > :today')
          ->andWhere('t.status != :completed')
          ->setParameter('owner', $owner)
          ->setParameter('today', $today)
          ->setParameter('completed', Task::STATUS_COMPLETED);

      $this->applyFilters($qb, $filters);
      $this->applySorting($qb, $sort, $sortOrder);

      return $qb;
  }
  ```

- [ ] **4.2.2** Create TaskGroupingService
  ```php
  // src/Service/TaskGroupingService.php

  final class TaskGroupingService
  {
      /**
       * Groups tasks by time period using user's timezone and week preferences.
       *
       * @param Task[] $tasks
       * @param string $timezone User's timezone (e.g., 'America/Los_Angeles')
       * @param int $startOfWeek 0=Sunday, 1=Monday (from user settings)
       * @return array<string, Task[]>
       */
      public function groupByTimePeriod(
          array $tasks,
          string $timezone = 'UTC',
          int $startOfWeek = 0
      ): array {
          $tz = new \DateTimeZone($timezone);
          $now = new \DateTimeImmutable('now', $tz);
          $today = $now->setTime(0, 0, 0);
          $tomorrow = $today->modify('+1 day');

          // Calculate week boundaries
          $daysUntilWeekEnd = (6 - (int)$today->format('w') + $startOfWeek) % 7;
          $thisWeekEnd = $today->modify("+{$daysUntilWeekEnd} days")->setTime(23, 59, 59);
          $nextWeekEnd = $thisWeekEnd->modify('+7 days');
          $thisMonthEnd = $today->modify('last day of this month')->setTime(23, 59, 59);

          $groups = [
              'tomorrow' => [],
              'this_week' => [],
              'next_week' => [],
              'this_month' => [],
              'later' => [],
          ];

          foreach ($tasks as $task) {
              $dueDate = $task->getDueDate();
              if ($dueDate === null) continue;

              // Convert to user timezone for comparison
              $due = \DateTimeImmutable::createFromInterface($dueDate)
                  ->setTimezone($tz);

              if ($due < $tomorrow->modify('+1 day')) {
                  $groups['tomorrow'][] = $task;
              } elseif ($due <= $thisWeekEnd) {
                  $groups['this_week'][] = $task;
              } elseif ($due <= $nextWeekEnd) {
                  $groups['next_week'][] = $task;
              } elseif ($due <= $thisMonthEnd) {
                  $groups['this_month'][] = $task;
              } else {
                  $groups['later'][] = $task;
              }
          }

          return $groups;
      }
  }
  ```

- [ ] **4.2.3** Create Upcoming API endpoint
  ```php
  #[Route('/upcoming', name: 'upcoming', methods: ['GET'])]
  public function upcoming(Request $request): JsonResponse
  {
      $user = $this->getUser();
      $grouped = $request->query->getBoolean('grouped', true);

      $qb = $this->taskRepository->findUpcomingTasksQueryBuilder($user);

      if ($grouped) {
          // Fetch all (with reasonable limit) and group in memory
          $tasks = $qb->setMaxResults(500)->getQuery()->getResult();
          $groups = $this->taskGroupingService->groupByTimePeriod(
              $tasks,
              $user->getSettings()['timezone'] ?? 'UTC',
              $user->getSettings()['start_of_week'] ?? 0
          );

          return $this->json([
              'success' => true,
              'data' => $this->transformGroupedTasks($groups),
              'meta' => [
                  'total' => count($tasks),
                  'groupCounts' => array_map('count', $groups),
              ],
          ]);
      } else {
          // Flat paginated list
          $result = $this->paginationHelper->paginate(
              $qb,
              $request->query->getInt('page', 1),
              $request->query->getInt('limit', 20)
          );

          return $this->json([
              'success' => true,
              'data' => TaskListResponse::fromTasks($result->items, $result->meta),
          ]);
      }
  }
  ```

- [ ] **4.2.4** Create Upcoming view template
  ```twig
  {# templates/task/upcoming.html.twig #}

  {% extends 'base.html.twig' %}

  {% block content %}
  <div class="max-w-4xl mx-auto" x-data="{ openGroups: { tomorrow: true, this_week: true, next_week: false, this_month: false, later: false } }">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">Upcoming</h1>

      {% for groupKey, tasks in groupedTasks %}
          {% if tasks|length > 0 %}
          <div class="mb-6">
              {# Group Header #}
              <button @click="openGroups.{{ groupKey }} = !openGroups.{{ groupKey }}"
                      class="w-full flex items-center justify-between py-2 px-3
                             {% if groupKey == 'tomorrow' %}bg-indigo-50 text-indigo-700{% else %}bg-gray-50 text-gray-900{% endif %}
                             rounded-md hover:bg-gray-100 transition-colors">
                  <span class="text-sm font-semibold">{{ groupKey|replace({'_': ' '})|title }}</span>
                  <div class="flex items-center gap-2">
                      <span class="text-xs text-gray-500">({{ tasks|length }} tasks)</span>
                      <svg :class="{ 'rotate-90': openGroups.{{ groupKey }} }"
                           class="w-4 h-4 text-gray-400 transition-transform">
                          <!-- chevron-right icon -->
                      </svg>
                  </div>
              </button>

              {# Tasks #}
              <div x-show="openGroups.{{ groupKey }}" x-transition class="mt-2 space-y-2">
                  {% for task in tasks %}
                      {% include 'task/_task_item.html.twig' with {task: task} %}
                  {% endfor %}
              </div>
          </div>
          {% endif %}
      {% endfor %}
  </div>
  {% endblock %}
  ```

### Completion Criteria
- [ ] Upcoming endpoint returns future tasks (excludes today and overdue)
- [ ] User ownership enforced
- [ ] Grouped mode groups correctly by period
- [ ] start_of_week preference respected for week boundaries
- [ ] Timezone-aware grouping
- [ ] Sort override working
- [ ] UI shows collapsible sections

### Files to Create
```
src/Service/TaskGroupingService.php (new)
src/Controller/Api/TaskController.php (add upcoming endpoint)
templates/task/upcoming.html.twig (new)
tests/Unit/Service/TaskGroupingServiceTest.php (new)
```

---

## Sub-Phase 4.3: Overdue View Implementation

### Objective
Create dedicated Overdue view with urgency-based sorting.

### Tasks

- [ ] **4.3.1** Create OverdueService
  ```php
  // src/Service/OverdueService.php

  final class OverdueService
  {
      public const SEVERITY_LOW = 'low';      // 1-2 days
      public const SEVERITY_MEDIUM = 'medium'; // 3-7 days
      public const SEVERITY_HIGH = 'high';    // 7+ days

      public function calculateSeverity(\DateTimeInterface $dueDate): string
      {
          $days = $this->getOverdueDays($dueDate);

          return match(true) {
              $days <= 2 => self::SEVERITY_LOW,
              $days <= 7 => self::SEVERITY_MEDIUM,
              default => self::SEVERITY_HIGH,
          };
      }

      public function getOverdueDays(\DateTimeInterface $dueDate): int
      {
          $today = new \DateTimeImmutable('today');
          $interval = $today->diff($dueDate);

          // Return positive days if overdue
          return $interval->invert ? $interval->days : 0;
      }

      /**
       * @return array{low: int, medium: int, high: int}
       */
      public function countBySeverity(array $tasks): array
      {
          $counts = ['low' => 0, 'medium' => 0, 'high' => 0];

          foreach ($tasks as $task) {
              if ($task->getDueDate() !== null) {
                  $severity = $this->calculateSeverity($task->getDueDate());
                  $counts[$severity]++;
              }
          }

          return $counts;
      }
  }
  ```

- [ ] **4.3.2** Create Overdue API endpoint
  ```php
  #[Route('/overdue', name: 'overdue', methods: ['GET'])]
  public function overdue(Request $request): JsonResponse
  {
      $user = $this->getUser();

      // Use existing findOverdueByOwner() or create enhanced version
      $qb = $this->taskRepository->findOverdueQueryBuilder($user);

      // Apply filters
      $filters = TaskFilterRequest::fromRequest($request);
      $this->taskRepository->applyFilters($qb, $filters->toArray());

      // Apply severity filter if specified
      $severity = $request->query->get('severity');
      if ($severity) {
          $this->applyOverdueSeverityFilter($qb, $severity);
      }

      // Sorting: default by due_date ASC (most overdue first)
      $sort = $request->query->get('sort', 'due_date');
      $sortOrder = $request->query->get('sort_order', 'asc');
      $this->taskRepository->applySorting($qb, $sort, $sortOrder);

      // Paginate
      $result = $this->paginationHelper->paginate(
          $qb,
          $request->query->getInt('page', 1),
          $request->query->getInt('limit', 20)
      );

      // Get severity counts
      $severityCounts = $this->overdueService->countBySeverity($result->items);

      return $this->json([
          'success' => true,
          'data' => TaskListResponse::fromTasks($result->items, $result->meta),
          'meta' => array_merge($result->meta, [
              'severityCounts' => $severityCounts,
          ]),
      ]);
  }
  ```

- [ ] **4.3.3** Add severity to TaskResponse
  ```php
  // In TaskResponse::fromTask(), add:
  $overdueSeverity = $task->isOverdue()
      ? $this->overdueService->calculateSeverity($task->getDueDate())
      : null;
  ```

- [ ] **4.3.4** Create Overdue view template
  ```twig
  {# templates/task/overdue.html.twig #}

  {% extends 'base.html.twig' %}

  {% set severityClasses = {
      'low': 'border-yellow-400 bg-yellow-50',
      'medium': 'border-orange-500 bg-orange-50',
      'high': 'border-red-600 bg-red-50'
  } %}

  {% set severityBadgeClasses = {
      'low': 'bg-yellow-100 text-yellow-800',
      'medium': 'bg-orange-100 text-orange-800',
      'high': 'bg-red-100 text-red-800'
  } %}

  {% block content %}
  <div class="max-w-4xl mx-auto">
      {# Header with bulk action #}
      <div class="flex items-center justify-between mb-6">
          <div>
              <h1 class="text-2xl font-bold text-gray-900">Overdue</h1>
              <p class="text-sm text-gray-500">{{ total }} tasks need attention</p>
          </div>
          <button class="bg-white border border-gray-300 rounded-md px-4 py-2
                         text-sm font-medium text-gray-700 hover:bg-gray-50
                         inline-flex items-center gap-2">
              <svg class="w-4 h-4"><!-- calendar icon --></svg>
              Reschedule all to tomorrow
          </button>
      </div>

      {# Severity summary #}
      <div class="flex gap-4 mb-6">
          {% for severity, count in severityCounts %}
              <div class="px-3 py-2 rounded-md {{ severityBadgeClasses[severity] }}">
                  <span class="font-medium">{{ count }}</span>
                  <span class="text-xs">{{ severity }}</span>
              </div>
          {% endfor %}
      </div>

      {# Task list #}
      <div class="space-y-3">
          {% for task in tasks %}
              <div class="task-card bg-white rounded-lg shadow p-4 border-l-4
                          {{ severityClasses[task.overdueSeverity] }}">
                  {% include 'task/_task_item.html.twig' with {task: task} %}
              </div>
          {% endfor %}
      </div>
  </div>
  {% endblock %}
  ```

### Completion Criteria
- [ ] Overdue endpoint returns correct tasks
- [ ] User ownership enforced
- [ ] Pagination working
- [ ] Severity calculated correctly (1-2d low, 3-7d medium, 7+d high)
- [ ] Severity counts in meta
- [ ] Sort by overdue days works
- [ ] UI shows urgency visually

### Files to Create
```
src/Service/OverdueService.php (new)
templates/task/overdue.html.twig (new)
tests/Unit/Service/OverdueServiceTest.php (new)
```

---

## Sub-Phase 4.4: All Tasks View & No-Date View

### Objective
Create the ALL tasks view (non-completed tasks grouped by project) and a view for tasks without due dates.

### Tasks

- [ ] **4.4.1** Create No-Date query
  ```php
  // src/Repository/TaskRepository.php

  public function findNoDateTasksQueryBuilder(
      User $owner,
      array $filters = [],
      string $sort = 'priority',
      string $sortOrder = 'desc'
  ): QueryBuilder {
      $qb = $this->createQueryBuilder('t')
          ->where('t.owner = :owner')
          ->andWhere('t.dueDate IS NULL')
          ->andWhere('t.status != :completed')
          ->setParameter('owner', $owner)
          ->setParameter('completed', Task::STATUS_COMPLETED);

      $this->applyFilters($qb, $filters);

      // Default: priority DESC, then createdAt DESC
      if ($sort === 'priority') {
          $qb->orderBy('t.priority', 'DESC')
             ->addOrderBy('t.createdAt', 'DESC');
      } else {
          $this->applySorting($qb, $sort, $sortOrder);
      }

      return $qb;
  }
  ```

- [ ] **4.4.2** Create No-Date API endpoint
  ```php
  #[Route('/no-date', name: 'no_date', methods: ['GET'])]
  public function noDate(Request $request): JsonResponse
  {
      $user = $this->getUser();

      $qb = $this->taskRepository->findNoDateTasksQueryBuilder($user);

      // Apply additional filters
      $filters = TaskFilterRequest::fromRequest($request);
      $this->taskRepository->applyFilters($qb, $filters->toArray());

      // Sorting
      $sort = $request->query->get('sort', 'priority');
      $sortOrder = $request->query->get('sort_order', 'desc');
      $this->taskRepository->applySorting($qb, $sort, $sortOrder);

      // Paginate
      $result = $this->paginationHelper->paginate(
          $qb,
          $request->query->getInt('page', 1),
          $request->query->getInt('limit', 20)
      );

      return $this->json([
          'success' => true,
          'data' => TaskListResponse::fromTasks($result->items, $result->meta),
      ]);
  }
  ```

- [ ] **4.4.3** Create Completed Tasks endpoint (missing from original plan)
  ```php
  #[Route('/completed', name: 'completed', methods: ['GET'])]
  public function completed(Request $request): JsonResponse
  {
      $user = $this->getUser();

      $qb = $this->createQueryBuilder('t')
          ->where('t.owner = :owner')
          ->andWhere('t.status = :completed')
          ->setParameter('owner', $user)
          ->setParameter('completed', Task::STATUS_COMPLETED)
          ->orderBy('t.completedAt', 'DESC');

      // Apply date range filter (last 7 days by default)
      $since = $request->query->get('since');
      if ($since) {
          $qb->andWhere('t.completedAt >= :since')
             ->setParameter('since', new \DateTimeImmutable($since));
      }

      $result = $this->paginationHelper->paginate($qb, ...);

      return $this->json([
          'success' => true,
          'data' => TaskListResponse::fromTasks($result->items, $result->meta),
      ]);
  }
  ```

- [ ] **4.4.4** Enhance main task list with group_by parameter
  ```php
  // Existing GET /api/v1/tasks enhanced with:
  // ?group_by=project - returns tasks grouped by project

  if ($request->query->get('group_by') === 'project') {
      $tasks = $qb->getQuery()->getResult();
      $grouped = $this->groupTasksByProject($tasks);

      return $this->json([
          'success' => true,
          'data' => $grouped,
          'meta' => ['total' => count($tasks)],
      ]);
  }
  ```

- [ ] **4.4.5** Create view templates
  ```twig
  {# templates/task/no-date.html.twig #}
  - List of tasks without dates
  - Quick date picker to assign dates
  - Sorted by priority by default
  - Option to view by project grouping
  ```

### Completion Criteria
- [ ] No-date view shows only tasks without due dates
- [ ] Completed view shows completed tasks sorted by completedAt
- [ ] User ownership enforced on all endpoints
- [ ] Pagination working
- [ ] Project grouping option available

### Files to Create
```
templates/task/no-date.html.twig (new)
templates/task/completed.html.twig (new)
```

---

## Sub-Phase 4.5: Comprehensive Filter System (Backend)

### Objective
Enhance existing filtering with all filter parameters and project hierarchy support.

### Tasks

- [ ] **4.5.1** Create TaskFilterRequest DTO
  ```php
  // src/DTO/TaskFilterRequest.php

  final readonly class TaskFilterRequest
  {
      public function __construct(
          public ?array $status = null,              // Multiple statuses
          public ?array $projectId = null,           // Multiple projects (UUIDs)
          public ?bool $includeProjectChildren = null, // Override show_children_tasks
          public ?array $tagIds = null,              // Multiple tags (UUIDs)
          public ?string $tagMatch = 'any',          // 'any' or 'all'
          public ?int $priorityMin = null,
          public ?int $priorityMax = null,
          public ?string $dueBefore = null,
          public ?string $dueAfter = null,
          public ?string $search = null,
          public ?bool $includeArchivedProjects = false,
      ) {}

      public static function fromRequest(Request $request): self
      {
          return new self(
              status: self::parseArray($request->query->get('status')),
              projectId: self::parseArray($request->query->get('project_id')),
              includeProjectChildren: $request->query->has('include_project_children')
                  ? $request->query->getBoolean('include_project_children')
                  : null,
              tagIds: self::parseArray($request->query->get('tag_ids')),
              tagMatch: $request->query->get('tag_match', 'any'),
              priorityMin: $request->query->getInt('priority_min') ?: null,
              priorityMax: $request->query->getInt('priority_max') ?: null,
              dueBefore: $request->query->get('due_before'),
              dueAfter: $request->query->get('due_after'),
              search: $request->query->get('search'),
              includeArchivedProjects: $request->query->getBoolean('include_archived_projects', false),
          );
      }

      private static function parseArray(?string $value): ?array
      {
          if ($value === null) return null;
          return array_filter(explode(',', $value));
      }

      public function toArray(): array
      {
          return array_filter(get_object_vars($this), fn($v) => $v !== null);
      }
  }
  ```

- [ ] **4.5.2** Enhance TaskRepository::applyFilters()
  ```php
  // src/Repository/TaskRepository.php

  public function applyFilters(QueryBuilder $qb, array $filters): void
  {
      // Multiple status support
      if (!empty($filters['status'])) {
          $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
          $qb->andWhere('t.status IN (:statuses)')
             ->setParameter('statuses', $statuses);
      }

      // Priority range
      if (isset($filters['priorityMin'])) {
          $qb->andWhere('t.priority >= :priorityMin')
             ->setParameter('priorityMin', $filters['priorityMin']);
      }
      if (isset($filters['priorityMax'])) {
          $qb->andWhere('t.priority <= :priorityMax')
             ->setParameter('priorityMax', $filters['priorityMax']);
      }

      // Project with hierarchy support
      if (!empty($filters['projectId'])) {
          $this->applyProjectFilter(
              $qb,
              $filters['projectId'],
              $filters['includeProjectChildren'] ?? null,
              $filters['includeArchivedProjects'] ?? false
          );
      }

      // Tag match logic
      if (!empty($filters['tagIds'])) {
          $tagMatch = $filters['tagMatch'] ?? 'any';
          $this->applyTagFilter($qb, $filters['tagIds'], $tagMatch);
      }

      // Date ranges (existing)
      if (isset($filters['dueBefore'])) {
          $qb->andWhere('t.dueDate <= :dueBefore')
             ->setParameter('dueBefore', new \DateTimeImmutable($filters['dueBefore']));
      }
      if (isset($filters['dueAfter'])) {
          $qb->andWhere('t.dueDate >= :dueAfter')
             ->setParameter('dueAfter', new \DateTimeImmutable($filters['dueAfter']));
      }

      // Search (existing)
      if (!empty($filters['search'])) {
          $qb->andWhere('(t.title LIKE :search OR t.description LIKE :search)')
             ->setParameter('search', '%' . $filters['search'] . '%');
      }
  }
  ```

- [ ] **4.5.3** Implement project hierarchy filter
  ```php
  private function applyProjectFilter(
      QueryBuilder $qb,
      array $projectIds,
      ?bool $includeChildren,
      bool $includeArchived
  ): void {
      $allProjectIds = [];

      foreach ($projectIds as $projectId) {
          $project = $this->projectRepository->findOneByOwnerAndId(
              $this->getCurrentUser(),
              $projectId
          );

          if ($project === null) {
              continue; // Skip invalid/unowned projects
          }

          // Determine whether to include children:
          // 1. Use explicit parameter if provided
          // 2. Fall back to project's show_children_tasks setting
          $includeDesc = $includeChildren ?? $project->getShowChildrenTasks();

          if ($includeDesc) {
              $descendantIds = $this->projectRepository->getDescendantIds($project);
              if (!$includeArchived) {
                  $descendantIds = $this->filterOutArchivedProjects($descendantIds);
              }
              $allProjectIds = array_merge($allProjectIds, $descendantIds);
          }

          $allProjectIds[] = $projectId;
      }

      if (empty($allProjectIds)) {
          // No valid projects - return no results
          $qb->andWhere('1 = 0');
          return;
      }

      $qb->andWhere('t.project IN (:projectIds)')
         ->setParameter('projectIds', array_unique($allProjectIds));
  }
  ```

- [ ] **4.5.4** Implement tag match logic (AND/OR)
  ```php
  private function applyTagFilter(QueryBuilder $qb, array $tagIds, string $match): void
  {
      if ($match === 'all') {
          // AND logic: task must have ALL specified tags
          foreach ($tagIds as $i => $tagId) {
              $subQuery = $this->getEntityManager()->createQueryBuilder()
                  ->select('1')
                  ->from('App\Entity\Task', "t{$i}")
                  ->innerJoin("t{$i}.tags", "tag{$i}")
                  ->where("t{$i}.id = t.id")
                  ->andWhere("tag{$i}.id = :tagId{$i}");

              $qb->andWhere($qb->expr()->exists($subQuery->getDQL()))
                 ->setParameter("tagId{$i}", $tagId);
          }
      } else {
          // OR logic: task has ANY of the specified tags
          $qb->innerJoin('t.tags', 'tag')
             ->andWhere('tag.id IN (:tagIds)')
             ->setParameter('tagIds', $tagIds);
      }
  }
  ```

- [ ] **4.5.5** Add input validation (security from Phase 3)
  ```php
  // In TaskFilterRequest::fromRequest():

  // Limit array sizes to prevent DoS
  $projectIds = self::parseArray($request->query->get('project_id'));
  if ($projectIds && count($projectIds) > 50) {
      throw ValidationException::forField('project_id', 'Maximum 50 projects allowed');
  }

  $tagIds = self::parseArray($request->query->get('tag_ids'));
  if ($tagIds && count($tagIds) > 50) {
      throw ValidationException::forField('tag_ids', 'Maximum 50 tags allowed');
  }
  ```

### Completion Criteria
- [ ] Multiple status filtering works
- [ ] Priority range (min/max) works
- [ ] Project hierarchy filtering respects show_children_tasks setting
- [ ] include_project_children override works
- [ ] Tag match (AND/OR) logic works
- [ ] All filters combinable
- [ ] Input validation prevents abuse
- [ ] User ownership always enforced

### Files to Create
```
src/DTO/TaskFilterRequest.php (new)
src/Repository/TaskRepository.php (enhance applyFilters)
tests/Functional/Api/TaskFilterApiTest.php (new)
```

---

## Sub-Phase 4.6: Sorting System

### Objective
Implement comprehensive sorting for task lists.

### Tasks

- [ ] **4.6.1** Create TaskSortRequest DTO
  ```php
  // src/DTO/TaskSortRequest.php

  final readonly class TaskSortRequest
  {
      public const ALLOWED_FIELDS = [
          'due_date',
          'priority',
          'created_at',
          'updated_at',
          'completed_at',
          'title',
          'position',
      ];

      public function __construct(
          public string $field = 'position',
          public string $order = 'asc',
      ) {
          if (!in_array($field, self::ALLOWED_FIELDS, true)) {
              throw new \InvalidArgumentException("Invalid sort field: {$field}");
          }
          if (!in_array($order, ['asc', 'desc'], true)) {
              throw new \InvalidArgumentException("Invalid sort order: {$order}");
          }
      }

      public static function fromRequest(Request $request, string $defaultField = 'position'): self
      {
          return new self(
              field: $request->query->get('sort', $defaultField),
              order: $request->query->get('sort_order', 'asc'),
          );
      }
  }
  ```

- [ ] **4.6.2** Add applySorting() to TaskRepository
  ```php
  // src/Repository/TaskRepository.php

  private const SORT_FIELD_MAP = [
      'due_date' => 't.dueDate',
      'priority' => 't.priority',
      'created_at' => 't.createdAt',
      'updated_at' => 't.updatedAt',
      'completed_at' => 't.completedAt',
      'title' => 't.title',
      'position' => 't.position',
  ];

  public function applySorting(QueryBuilder $qb, string $field, string $order): void
  {
      if (!isset(self::SORT_FIELD_MAP[$field])) {
          return; // Ignore invalid fields
      }

      $column = self::SORT_FIELD_MAP[$field];
      $direction = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

      // Handle nulls for due_date
      if ($field === 'due_date') {
          // Nulls always last
          $qb->addOrderBy('CASE WHEN t.dueDate IS NULL THEN 1 ELSE 0 END', 'ASC');
      }

      $qb->addOrderBy($column, $direction);

      // Secondary sort by position for stability
      if ($field !== 'position') {
          $qb->addOrderBy('t.position', 'ASC');
      }
  }
  ```

- [ ] **4.6.3** Add sort parameters to all list endpoints
  ```php
  // In TaskController, for each listing endpoint:

  $sort = TaskSortRequest::fromRequest($request, 'due_date'); // or appropriate default
  $this->taskRepository->applySorting($qb, $sort->field, $sort->order);
  ```

### Completion Criteria
- [ ] All sort fields functional
- [ ] Sort order (asc/desc) works
- [ ] Null handling consistent (nulls last for due_date)
- [ ] Invalid sort fields rejected gracefully

### Files to Create
```
src/DTO/TaskSortRequest.php (new)
src/Repository/TaskRepository.php (add applySorting)
tests/Functional/Api/TaskSortApiTest.php (new)
```

---

## Sub-Phase 4.7: Filter UI Components

### Objective
Create the frontend UI for filtering and sorting tasks.

### Tasks

- [ ] **4.7.1** Create filter panel component
  ```twig
  {# templates/components/filter-panel.html.twig #}

  <div x-data="{ expanded: false }" class="bg-white shadow rounded-lg p-4 mb-4">
      {# Collapsed header #}
      <button @click="expanded = !expanded"
              class="w-full flex items-center justify-between py-2 hover:bg-gray-50 rounded-md transition-colors">
          <div class="flex items-center">
              <svg class="w-5 h-5 text-gray-500 mr-2"><!-- funnel icon --></svg>
              <span class="text-sm font-medium text-gray-700">Filters</span>
              {% if activeFilterCount > 0 %}
                  <span class="bg-indigo-100 text-indigo-700 rounded-full px-2 py-0.5 text-xs ml-2">
                      {{ activeFilterCount }}
                  </span>
              {% endif %}
          </div>
          <svg :class="{ 'rotate-180': expanded }" class="w-4 h-4 text-gray-400 transition-transform">
              <!-- chevron-down icon -->
          </svg>
      </button>

      {# Expanded panel #}
      <div x-show="expanded" x-transition class="mt-4 border-t border-gray-200 pt-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              {# Status filter #}
              <div>
                  <label class="text-sm font-medium text-gray-700 mb-2 block">Status</label>
                  <div class="space-y-2">
                      {% for status in ['pending', 'in_progress', 'completed'] %}
                          <label class="flex items-center">
                              <input type="checkbox" name="status[]" value="{{ status }}"
                                     class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                              <span class="ml-2 text-sm text-gray-700">{{ status|replace({'_': ' '})|title }}</span>
                          </label>
                      {% endfor %}
                  </div>
              </div>

              {# Priority filter #}
              <div>
                  <label class="text-sm font-medium text-gray-700 mb-2 block">Priority</label>
                  <div class="flex gap-2">
                      <input type="number" name="priority_min" min="1" max="5" placeholder="Min"
                             class="w-16 rounded-md border-gray-300 text-sm">
                      <span class="text-gray-500">to</span>
                      <input type="number" name="priority_max" min="1" max="5" placeholder="Max"
                             class="w-16 rounded-md border-gray-300 text-sm">
                  </div>
              </div>

              {# Project filter - include hierarchy component #}
              {% include 'components/project-select.html.twig' %}

              {# Tag filter - include tag select component #}
              {% include 'components/tag-select.html.twig' %}
          </div>

          {# Action buttons #}
          <div class="flex items-center justify-end gap-3 mt-4 pt-4 border-t border-gray-200">
              <button type="button" @click="clearFilters()"
                      class="text-sm text-indigo-600 hover:text-indigo-500 font-medium">
                  Clear all
              </button>
              <button type="submit"
                      class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-md px-4 py-2 text-sm font-semibold">
                  Apply Filters
              </button>
          </div>
      </div>
  </div>
  ```

- [ ] **4.7.2** Create active-filters component
  ```twig
  {# templates/components/active-filters.html.twig #}

  {% if activeFilters|length > 0 %}
  <div class="flex flex-wrap items-center gap-2 py-2 mb-4">
      {% for filter in activeFilters %}
          <span x-data x-transition
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 bg-gray-100 text-gray-700 text-sm">
              <span class="text-xs text-gray-500">{{ filter.label }}:</span>
              <span class="font-medium">{{ filter.value }}</span>
              <button @click="removeFilter('{{ filter.key }}')"
                      class="w-4 h-4 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-300 p-0.5">
                  <svg class="w-3 h-3"><!-- x-mark icon --></svg>
              </button>
          </span>
      {% endfor %}
      <button @click="clearAllFilters()"
              class="text-sm text-indigo-600 hover:text-indigo-500 font-medium ml-2">
          Clear all
      </button>
  </div>
  {% endif %}
  ```

- [ ] **4.7.3** Create sort-dropdown component
  ```twig
  {# templates/components/sort-dropdown.html.twig #}

  {% set sortOptions = [
      {value: 'due_date:asc', label: 'Due date (nearest first)'},
      {value: 'due_date:desc', label: 'Due date (furthest first)'},
      {value: 'priority:desc', label: 'Priority (high to low)'},
      {value: 'priority:asc', label: 'Priority (low to high)'},
      {value: 'created_at:desc', label: 'Recently created'},
      {value: 'title:asc', label: 'Alphabetical'},
      {value: 'position:asc', label: 'Manual order'},
  ] %}

  <div x-data="{ open: false }" class="relative">
      <button @click="open = !open" @click.away="open = false"
              class="bg-white border border-gray-300 rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 inline-flex items-center gap-2">
          <svg class="w-4 h-4 text-gray-500"><!-- arrows-up-down icon --></svg>
          <span>{{ currentSortLabel }}</span>
          <svg class="w-4 h-4 text-gray-400"><!-- chevron-down icon --></svg>
      </button>

      <div x-show="open" x-transition
           class="absolute right-0 z-10 mt-2 w-56 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5">
          <div class="py-1">
              {% for option in sortOptions %}
                  <button @click="setSort('{{ option.value }}'); open = false"
                          class="w-full px-4 py-2 text-sm text-left flex items-center justify-between
                                 {% if option.value == currentSort %}bg-indigo-50 text-indigo-700 font-medium{% else %}text-gray-700 hover:bg-gray-100{% endif %}">
                      <span>{{ option.label }}</span>
                      {% if option.value == currentSort %}
                          <svg class="w-4 h-4 text-indigo-600"><!-- check icon --></svg>
                      {% endif %}
                  </button>
              {% endfor %}
          </div>
      </div>
  </div>
  ```

- [ ] **4.7.4** Create project-select component with hierarchy
  ```twig
  {# templates/components/project-select.html.twig #}

  <div x-data="projectSelect()" class="relative">
      <label class="text-sm font-medium text-gray-700 mb-2 block">Project</label>

      {# Selected projects display #}
      <div class="min-h-[38px] border border-gray-300 rounded-md p-2 flex flex-wrap gap-1">
          <template x-for="project in selectedProjects" :key="project.id">
              <span class="inline-flex items-center gap-1 bg-gray-100 rounded px-2 py-0.5 text-sm">
                  <span x-text="project.name"></span>
                  <button @click="removeProject(project.id)" class="text-gray-400 hover:text-gray-600">
                      <svg class="w-3 h-3"><!-- x icon --></svg>
                  </button>
              </span>
          </template>
          <input type="text" x-model="searchQuery" @focus="open = true"
                 class="flex-1 min-w-[100px] border-none p-0 text-sm focus:ring-0"
                 placeholder="Search projects...">
      </div>

      {# Dropdown with hierarchy #}
      <div x-show="open" @click.away="open = false" x-transition
           class="absolute z-10 mt-1 w-full bg-white rounded-md shadow-lg border border-gray-200 max-h-60 overflow-auto">
          <template x-for="project in filteredProjects" :key="project.id">
              <label class="flex items-center px-3 py-2 hover:bg-gray-50 cursor-pointer"
                     :style="{ paddingLeft: (project.depth * 16 + 12) + 'px' }">
                  <input type="checkbox" :value="project.id"
                         :checked="isSelected(project.id)"
                         @change="toggleProject(project)"
                         class="rounded border-gray-300 text-indigo-600">
                  <span class="ml-2 text-sm text-gray-700" x-text="project.name"></span>
              </label>
          </template>
      </div>

      {# Include children toggle #}
      <label x-show="selectedProjects.length > 0" class="flex items-center mt-2">
          <input type="checkbox" name="include_project_children"
                 class="rounded border-gray-300 text-indigo-600">
          <span class="ml-2 text-sm text-gray-600">Include sub-project tasks</span>
      </label>
  </div>
  ```

- [ ] **4.7.5** Create filter-manager.js
  ```javascript
  // assets/js/filter-manager.js

  export class FilterManager {
      constructor(formElement, options = {}) {
          this.form = formElement;
          this.taskList = options.taskList;
          this.apiEndpoint = options.apiEndpoint || '/api/v1/tasks';

          this.init();
      }

      init() {
          this.form.addEventListener('submit', (e) => {
              e.preventDefault();
              this.applyFilters();
          });

          // Auto-apply on change for checkboxes
          this.form.querySelectorAll('input[type="checkbox"]').forEach(input => {
              input.addEventListener('change', () => this.applyFilters());
          });
      }

      collectFilters() {
          const formData = new FormData(this.form);
          const params = new URLSearchParams();

          for (const [key, value] of formData.entries()) {
              if (value) {
                  params.append(key, value);
              }
          }

          return params;
      }

      async applyFilters() {
          const params = this.collectFilters();

          // Update URL
          const url = new URL(window.location);
          url.search = params.toString();
          history.pushState({}, '', url);

          // Fetch filtered tasks
          try {
              const response = await fetch(`${this.apiEndpoint}?${params}`);
              const data = await response.json();

              if (data.success) {
                  this.updateTaskList(data.data);
                  this.updateActiveFilters(params);
              }
          } catch (error) {
              console.error('Filter error:', error);
          }
      }

      removeFilter(key) {
          const input = this.form.querySelector(`[name="${key}"]`);
          if (input) {
              if (input.type === 'checkbox') {
                  input.checked = false;
              } else {
                  input.value = '';
              }
          }
          this.applyFilters();
      }

      clearAllFilters() {
          this.form.reset();
          this.applyFilters();
      }
  }

  // Alpine.js component for filters
  document.addEventListener('alpine:init', () => {
      Alpine.data('filterPanel', () => ({
          expanded: false,
          activeFilters: [],

          init() {
              this.updateActiveFilters();
          },

          updateActiveFilters() {
              const params = new URLSearchParams(window.location.search);
              this.activeFilters = [];

              params.forEach((value, key) => {
                  if (value && key !== 'page') {
                      this.activeFilters.push({
                          key,
                          label: this.formatLabel(key),
                          value: this.formatValue(key, value),
                      });
                  }
              });
          },

          formatLabel(key) {
              return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
          },

          formatValue(key, value) {
              // Custom formatting per field type
              return value;
          },
      }));
  });
  ```

### Completion Criteria
- [ ] Filter panel renders all options
- [ ] Project filter shows hierarchy and include-children option
- [ ] Filters update task list dynamically (AJAX)
- [ ] Active filters displayed as removable chips
- [ ] Sort dropdown functional
- [ ] URL updated with filter state

### Files to Create
```
templates/components/filter-panel.html.twig (new)
templates/components/active-filters.html.twig (new)
templates/components/sort-dropdown.html.twig (new)
templates/components/project-select.html.twig (new)
templates/components/tag-select.html.twig (new)
assets/js/filter-manager.js (new)
```

---

## Sub-Phase 4.8: Saved Filters (Custom Views)

### Objective
Allow users to save filter combinations as custom views.

### Tasks

- [ ] **4.8.1** Create SavedFilter entity
  ```php
  // src/Entity/SavedFilter.php

  #[ORM\Entity(repositoryClass: SavedFilterRepository::class)]
  #[ORM\Table(name: 'saved_filters')]
  #[ORM\Index(columns: ['owner_id', 'position'], name: 'idx_saved_filters_owner_position')]
  class SavedFilter implements UserOwnedInterface
  {
      #[ORM\Id]
      #[ORM\Column(type: 'uuid', unique: true)]
      private string $id;

      #[ORM\ManyToOne(targetEntity: User::class)]
      #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
      private User $owner;

      #[ORM\Column(length: 100)]
      #[Assert\NotBlank]
      #[Assert\Length(max: 100)]
      private string $name;

      #[ORM\Column(length: 50, nullable: true)]
      #[Assert\Regex(pattern: '/^[a-zA-Z0-9_-]*$/', message: 'Icon must be alphanumeric')]
      private ?string $icon = null;

      #[ORM\Column(length: 7, nullable: true)]
      #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Invalid hex color')]
      private ?string $color = null;

      #[ORM\Column(type: 'json')]
      private array $filters = [];

      #[ORM\Column(type: 'integer')]
      private int $position = 0;

      #[ORM\Column(type: 'datetime_immutable')]
      private \DateTimeImmutable $createdAt;

      public function __construct()
      {
          $this->id = Uuid::uuid4()->toString();
          $this->createdAt = new \DateTimeImmutable();
      }

      // UserOwnedInterface implementation
      public function getOwner(): User
      {
          return $this->owner;
      }

      // ... getters and setters
  }
  ```

- [ ] **4.8.2** Create SavedFilterRepository
  ```php
  // src/Repository/SavedFilterRepository.php

  class SavedFilterRepository extends ServiceEntityRepository
  {
      public function findByOwner(User $owner): array
      {
          return $this->createQueryBuilder('sf')
              ->where('sf.owner = :owner')
              ->setParameter('owner', $owner)
              ->orderBy('sf.position', 'ASC')
              ->getQuery()
              ->getResult();
      }

      public function findOneByOwnerAndId(User $owner, string $id): ?SavedFilter
      {
          return $this->createQueryBuilder('sf')
              ->where('sf.owner = :owner')
              ->andWhere('sf.id = :id')
              ->setParameter('owner', $owner)
              ->setParameter('id', $id)
              ->getQuery()
              ->getOneOrNullResult();
      }
  }
  ```

- [ ] **4.8.3** Create SavedFilter DTOs
  ```php
  // src/DTO/CreateSavedFilterRequest.php

  final readonly class CreateSavedFilterRequest
  {
      public function __construct(
          #[Assert\NotBlank]
          #[Assert\Length(max: 100)]
          public string $name,

          #[Assert\Regex(pattern: '/^[a-zA-Z0-9_-]*$/')]
          public ?string $icon = null,

          #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/')]
          public ?string $color = null,

          public array $filters = [],
      ) {}

      public static function fromArray(array $data): self
      {
          return new self(
              name: $data['name'] ?? '',
              icon: $data['icon'] ?? null,
              color: $data['color'] ?? null,
              filters: $data['filters'] ?? [],
          );
      }
  }

  // src/DTO/SavedFilterResponse.php

  final readonly class SavedFilterResponse
  {
      public static function fromEntity(SavedFilter $filter): array
      {
          return [
              'id' => $filter->getId(),
              'name' => $filter->getName(),
              'icon' => $filter->getIcon(),
              'color' => $filter->getColor(),
              'filters' => $filter->getFilters(),
              'position' => $filter->getPosition(),
              'createdAt' => $filter->getCreatedAt()->format(\DateTimeInterface::RFC3339),
          ];
      }
  }
  ```

- [ ] **4.8.4** Create SavedFilterController
  ```php
  // src/Controller/Api/SavedFilterController.php

  #[Route('/api/v1/saved-filters', name: 'api_saved_filters_')]
  #[IsGranted('IS_AUTHENTICATED_FULLY')]
  final class SavedFilterController extends AbstractController
  {
      #[Route('', name: 'list', methods: ['GET'])]
      public function list(): JsonResponse
      {
          $filters = $this->savedFilterRepository->findByOwner($this->getUser());

          return $this->json([
              'success' => true,
              'data' => array_map(
                  fn(SavedFilter $f) => SavedFilterResponse::fromEntity($f),
                  $filters
              ),
          ]);
      }

      #[Route('', name: 'create', methods: ['POST'])]
      public function create(Request $request): JsonResponse
      {
          $dto = CreateSavedFilterRequest::fromArray($request->toArray());
          $this->validator->validate($dto);

          $filter = new SavedFilter();
          $filter->setOwner($this->getUser());
          $filter->setName($dto->name);
          $filter->setIcon($dto->icon);
          $filter->setColor($dto->color);
          $filter->setFilters($dto->filters);

          // Set position to end
          $maxPosition = $this->savedFilterRepository->getMaxPosition($this->getUser());
          $filter->setPosition($maxPosition + 1);

          $this->entityManager->persist($filter);
          $this->entityManager->flush();

          return $this->json([
              'success' => true,
              'data' => SavedFilterResponse::fromEntity($filter),
          ], Response::HTTP_CREATED);
      }

      #[Route('/{id}', name: 'show', methods: ['GET'])]
      public function show(string $id): JsonResponse
      {
          $filter = $this->findOwnedFilter($id);

          return $this->json([
              'success' => true,
              'data' => SavedFilterResponse::fromEntity($filter),
          ]);
      }

      #[Route('/{id}', name: 'update', methods: ['PATCH'])]
      public function update(Request $request, string $id): JsonResponse
      {
          $filter = $this->findOwnedFilter($id);
          $data = $request->toArray();

          if (isset($data['name'])) $filter->setName($data['name']);
          if (isset($data['icon'])) $filter->setIcon($data['icon']);
          if (isset($data['color'])) $filter->setColor($data['color']);
          if (isset($data['filters'])) $filter->setFilters($data['filters']);

          $this->entityManager->flush();

          return $this->json([
              'success' => true,
              'data' => SavedFilterResponse::fromEntity($filter),
          ]);
      }

      #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
      public function delete(string $id): JsonResponse
      {
          $filter = $this->findOwnedFilter($id);

          $this->entityManager->remove($filter);
          $this->entityManager->flush();

          return $this->json([
              'success' => true,
              'message' => 'Saved filter deleted',
          ]);
      }

      #[Route('/{id}/apply', name: 'apply', methods: ['GET'])]
      public function apply(string $id, Request $request): JsonResponse
      {
          $filter = $this->findOwnedFilter($id);
          $user = $this->getUser();

          // Validate that referenced projects/tags still belong to user
          $criteria = $filter->getFilters();
          if (!empty($criteria['projectId'])) {
              $this->validateProjectOwnership($user, (array) $criteria['projectId']);
          }

          // Build filter request from saved filters
          $filterRequest = TaskFilterRequest::fromArray($criteria);

          // Apply to task query
          $qb = $this->taskRepository->createFilteredQueryBuilder($user);
          $this->taskRepository->applyFilters($qb, $filterRequest->toArray());

          $result = $this->paginationHelper->paginate(
              $qb,
              $request->query->getInt('page', 1),
              $request->query->getInt('limit', 20)
          );

          return $this->json([
              'success' => true,
              'data' => TaskListResponse::fromTasks($result->items, $result->meta),
              'meta' => [
                  'savedFilter' => SavedFilterResponse::fromEntity($filter),
              ],
          ]);
      }

      private function findOwnedFilter(string $id): SavedFilter
      {
          $filter = $this->savedFilterRepository->findOneByOwnerAndId(
              $this->getUser(),
              $id
          );

          if ($filter === null) {
              throw new SavedFilterNotFoundException($id);
          }

          return $filter;
      }
  }
  ```

- [ ] **4.8.5** Create database migration
  ```php
  // migrations/VersionXXX_CreateSavedFilters.php

  public function up(Schema $schema): void
  {
      $this->addSql('CREATE TABLE saved_filters (
          id UUID NOT NULL,
          owner_id UUID NOT NULL,
          name VARCHAR(100) NOT NULL,
          icon VARCHAR(50) DEFAULT NULL,
          color VARCHAR(7) DEFAULT NULL,
          filters JSON NOT NULL,
          position INT NOT NULL DEFAULT 0,
          created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
          PRIMARY KEY(id),
          CONSTRAINT FK_saved_filters_owner FOREIGN KEY (owner_id)
              REFERENCES users (id) ON DELETE CASCADE
      )');

      $this->addSql('CREATE INDEX idx_saved_filters_owner_position ON saved_filters (owner_id, position)');

      $this->addSql('COMMENT ON COLUMN saved_filters.id IS \'(DC2Type:uuid)\'');
      $this->addSql('COMMENT ON COLUMN saved_filters.owner_id IS \'(DC2Type:uuid)\'');
      $this->addSql('COMMENT ON COLUMN saved_filters.created_at IS \'(DC2Type:datetime_immutable)\'');
  }
  ```

- [ ] **4.8.6** Add saved filters to sidebar
  ```twig
  {# In templates/layout/sidebar.html.twig or equivalent #}

  {# Standard views #}
  <nav class="space-y-1">
      <a href="{{ path('app_tasks') }}" class="...">
          <svg><!-- inbox icon --></svg> Inbox
      </a>
      <a href="{{ path('app_tasks_today') }}" class="...">
          <svg><!-- calendar-day icon --></svg> Today
      </a>
      <a href="{{ path('app_tasks_upcoming') }}" class="...">
          <svg><!-- calendar icon --></svg> Upcoming
      </a>
  </nav>

  {# Saved filters #}
  {% if savedFilters|length > 0 %}
  <div class="mt-6">
      <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-3 mb-2">
          Saved Filters
      </h3>
      <nav class="space-y-1">
          {% for filter in savedFilters %}
              <a href="{{ path('api_saved_filters_apply', {id: filter.id}) }}"
                 class="flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 hover:bg-gray-100">
                  {% if filter.icon %}
                      <span class="mr-2">{{ filter.icon }}</span>
                  {% endif %}
                  <span {% if filter.color %}style="color: {{ filter.color }}"{% endif %}>
                      {{ filter.name }}
                  </span>
              </a>
          {% endfor %}
      </nav>
  </div>
  {% endif %}
  ```

### Completion Criteria
- [ ] Saved filters entity uses UUID (not BIGINT)
- [ ] Saved filters persist in database
- [ ] User ownership enforced on all operations
- [ ] Saved filters appear in sidebar
- [ ] Clicking saved filter applies its filters
- [ ] Can edit and delete saved filters

### Files to Create
```
src/Entity/SavedFilter.php (new)
src/Repository/SavedFilterRepository.php (new)
src/Controller/Api/SavedFilterController.php (new)
src/DTO/CreateSavedFilterRequest.php (new)
src/DTO/UpdateSavedFilterRequest.php (new)
src/DTO/SavedFilterResponse.php (new)
src/Exception/SavedFilterNotFoundException.php (new)
migrations/VersionXXX_CreateSavedFilters.php (new)
tests/Functional/Api/SavedFilterApiTest.php (new)
```

---

## Sub-Phase 4.9: View Tests

### Objective
Create comprehensive tests for all views and filters.

### Tests Required

```
tests/Functional/Api/
├── TodayViewApiTest.php
│   - testTodayReturnsTasksDueToday()
│   - testTodayIncludesOverdueTasks()
│   - testTodayExcludesCompletedTasks()
│   - testTodayExcludesFutureTasks()
│   - testTodayUserIsolation()
│   - testTodayPagination()
│   - testTodayDefaultSort()
│   - testTodaySortOverride()
│   - testTodayWithAdditionalFilters()
│
├── UpcomingViewApiTest.php
│   - testUpcomingGroupsByPeriod()
│   - testUpcomingExcludesOverdue()
│   - testUpcomingExcludesNoDateTasks()
│   - testUpcomingUserIsolation()
│   - testUpcomingRespectsStartOfWeekSunday()
│   - testUpcomingRespectsStartOfWeekMonday()
│
├── OverdueViewApiTest.php
│   - testOverdueReturnsCorrectTasks()
│   - testOverdueUserIsolation()
│   - testOverdueSeverityCalculation()
│   - testOverdueSortByOverdueDays()
│
├── NoDateViewApiTest.php
│   - testNoDateReturnsTasksWithoutDueDate()
│   - testNoDateExcludesCompletedByDefault()
│   - testNoDateUserIsolation()
│
├── TaskFilterApiTest.php
│   - testFilterByMultipleStatuses()
│   - testFilterByProjectWithChildren()
│   - testFilterByProjectWithoutChildren()
│   - testFilterRespectsShowChildrenTasksSetting()
│   - testFilterByMultipleTagsAny()
│   - testFilterByMultipleTagsAll()
│   - testFilterByPriorityRange()
│   - testCombinedFilters()
│   - testFilterInputValidation()
│
├── TaskSortApiTest.php
│   - testSortByDueDateAsc()
│   - testSortByDueDateDesc()
│   - testSortByPriority()
│   - testSortWithNullsLast()
│   - testInvalidSortFieldRejected()
│
├── SavedFilterApiTest.php
│   - testCreateSavedFilter()
│   - testListSavedFiltersUserIsolation()
│   - testApplySavedFilter()
│   - testUpdateSavedFilterOwnershipCheck()
│   - testDeleteSavedFilterOwnershipCheck()
│   - testSavedFilterWithInvalidProjectReference()
│
└── TimezoneEdgeCasesTest.php
    - testTodayViewAtMidnightBoundary()
    - testUpcomingGroupingDuringDSTTransition()
    - testOverdueCalculationWithDueTimeSet()

tests/Unit/Service/
├── TaskGroupingServiceTest.php
│   - testGroupByTimePeriodWithSundayStart()
│   - testGroupByTimePeriodWithMondayStart()
│   - testGroupByTimePeriodAcrossMonthBoundary()
│   - testEmptyTaskArray()
│
└── OverdueServiceTest.php
    - testCalculateSeverityLow()
    - testCalculateSeverityMedium()
    - testCalculateSeverityHigh()
    - testGetOverdueDays()
```

---

## Phase 4 Deliverables Checklist

At the end of Phase 4, the following should be complete:

### Core Views
- [ ] Today view showing due today + overdue (with isOverdue, overdueDays fields)
- [ ] Upcoming view with time period grouping (respects start_of_week)
- [ ] Overdue view with severity indicators (low/medium/high)
- [ ] No-date view showing tasks without due dates
- [ ] Completed view showing completed tasks (added from original plan)

### Standard API Features (All Endpoints)
- [ ] Pagination (page, limit, total, totalPages)
- [ ] Sorting with defaults and overrides (sort, sort_order)
- [ ] User ownership enforced on all queries

### Filtering
- [ ] Multiple status values (array)
- [ ] Priority range (min/max)
- [ ] Project filtering with hierarchy (include_project_children)
- [ ] Tag match (AND/OR) logic
- [ ] Date range filters
- [ ] Search

### UI
- [ ] Filter UI panel with collapsible sections
- [ ] Project select with hierarchy display
- [ ] Active filters displayed as removable chips
- [ ] Sort dropdown
- [ ] Dynamic filtering via AJAX

### Saved Filters
- [ ] SavedFilter entity (UUID primary key)
- [ ] CRUD endpoints with ownership enforcement
- [ ] Saved filters appear in sidebar

### Testing
- [ ] 90%+ coverage for filter/sort services
- [ ] User isolation verified for all endpoints
- [ ] Timezone edge cases tested

---

## Appendix: Error Codes for Phase 4

```php
// Add to existing error code constants

const ERROR_INVALID_FILTER = 'INVALID_FILTER';
const ERROR_INVALID_SORT_FIELD = 'INVALID_SORT_FIELD';
const ERROR_INVALID_SORT_ORDER = 'INVALID_SORT_ORDER';
const ERROR_SAVED_FILTER_NOT_FOUND = 'SAVED_FILTER_NOT_FOUND';
const ERROR_FILTER_LIMIT_EXCEEDED = 'FILTER_LIMIT_EXCEEDED';
const ERROR_INVALID_TAG_MATCH = 'INVALID_TAG_MATCH';
```

# Phase 4 Implementation Review Plan

**Review Date:** 2026-01-24
**Scope:** Views & Filtering Implementation Review
**Status:** Pre-Implementation Review & Plan Update

---

## Executive Summary

This document reviews the Phase 4 plan (Views & Filtering) in light of:
1. Current implementation state (what's already built)
2. Architecture patterns established in Phases 1-3
3. Security findings from Phase 3 review
4. Missed items and new considerations

**Key Findings:**
- Significant filtering infrastructure already exists in `TaskRepository`
- The original plan needs updates to align with established DTO/Service patterns
- Several security patterns from Phase 3 must be applied consistently
- Some planned features are partially implemented but need completion
- The SavedFilter entity uses BIGINT in the plan but should use UUID (per architecture)

---

## Table of Contents

1. [Current Implementation State](#1-current-implementation-state)
2. [Architecture Alignment Updates](#2-architecture-alignment-updates)
3. [Security Considerations from Phase 3](#3-security-considerations-from-phase-3)
4. [Updated Sub-Phase Plans](#4-updated-sub-phase-plans)
5. [New Considerations & Missed Items](#5-new-considerations--missed-items)
6. [Test Coverage Requirements](#6-test-coverage-requirements)
7. [Implementation Priority](#7-implementation-priority)
8. [Files to Create/Modify](#8-files-to-createmodify)

---

## 1. Current Implementation State

### 1.1 Already Implemented

**TaskRepository Query Methods:**
| Method | Status | Notes |
|--------|--------|-------|
| `findByOwner()` | ✅ Complete | Basic owner-scoped queries |
| `createFilteredQueryBuilder()` | ✅ Complete | Comprehensive filter support |
| `findOverdueByOwner()` | ✅ Complete | Returns tasks with dueDate < today |
| `findDueSoonByOwner()` | ✅ Complete | Returns tasks in next N days |
| `findByProjectWithChildren()` | ✅ Complete | Respects project hierarchy |
| `search()` | ✅ Complete | PostgreSQL full-text search |
| `reorderTasks()` | ✅ Complete | Manual position-based reordering |

**Existing Filter Support in `createFilteredQueryBuilder()`:**
- Status filtering (single value)
- Priority filtering (exact match)
- Project filtering (single project)
- Due date range filtering (dueBefore, dueAfter)
- Tag filtering (array of IDs)
- Search (LIKE-based on title/description)

**API Endpoints:**
| Endpoint | Status | Notes |
|----------|--------|-------|
| `GET /api/v1/tasks` | ✅ Complete | List with basic filters |
| `GET /api/v1/tasks/{id}` | ✅ Complete | Single task |
| `PATCH /api/v1/tasks/{id}` | ✅ Complete | Update with undo |
| `PATCH /api/v1/tasks/{id}/status` | ✅ Complete | Status change |
| `PATCH /api/v1/tasks/{id}/reschedule` | ✅ Complete | Date change |
| `PATCH /api/v1/tasks/reorder` | ✅ Complete | Reorder tasks |

**DTOs:**
- `TaskResponse` - includes project, tags, undoToken
- `TaskListResponse` - paginated list with meta
- `CreateTaskRequest` / `UpdateTaskRequest` - input validation

### 1.2 Missing for Phase 4

**Specialized View Endpoints:**
- ❌ `GET /api/v1/tasks/today` - Today view (overdue + today)
- ❌ `GET /api/v1/tasks/upcoming` - Upcoming view with grouping
- ❌ `GET /api/v1/tasks/overdue` - Dedicated overdue view
- ❌ `GET /api/v1/tasks/no-date` - Tasks without due dates

**Repository Methods:**
- ❌ `findTodayTasks()` - combined today + overdue
- ❌ `findUpcomingTasks()` - future due dates only
- ❌ `findTasksWithoutDueDate()` - null due_date
- ❌ Proper `PaginatedResult` objects

**Filter Enhancements Needed:**
- ❌ Multiple status filter support
- ❌ Priority range (min/max)
- ❌ Tag match logic (AND/OR)
- ❌ Project hierarchy filter with `include_project_children`
- ❌ Sorting parameter support
- ❌ Include expansion parameter

**Services:**
- ❌ `TaskFilterBuilder` / `TaskFilterCriteria`
- ❌ `TaskSortBuilder` / `SortCriteria`
- ❌ `TaskGroupingService`
- ❌ `OverdueService`

**SavedFilter Feature:**
- ❌ `SavedFilter` entity
- ❌ `SavedFilterRepository`
- ❌ `SavedFilterController`
- ❌ CRUD endpoints

**View Templates:**
- ❌ `templates/task/today.html.twig`
- ❌ `templates/task/upcoming.html.twig`
- ❌ `templates/task/overdue.html.twig`
- ❌ `templates/task/no-date.html.twig`
- ❌ `templates/components/filter-panel.html.twig`
- ❌ `templates/components/active-filters.html.twig`
- ❌ `templates/components/sort-dropdown.html.twig`

---

## 2. Architecture Alignment Updates

### 2.1 Entity ID Type Correction

**Original Plan Error:** SavedFilter entity uses `BIGINT` for ID.

**Correction Required:** All entities use UUID primary keys per architecture.

```diff
// src/Entity/SavedFilter.php
- id: BIGINT
+ id: UUID (like all other entities)
```

### 2.2 DTO Pattern Alignment

The existing codebase uses a consistent DTO pattern:

```php
// Request DTOs use static fromArray() factory
$dto = CreateTaskRequest::fromArray($data);

// Response DTOs use static fromEntity() factory
$response = TaskResponse::fromTask($task);
```

**New DTOs should follow this pattern:**

```php
// src/DTO/TaskFilterRequest.php
final readonly class TaskFilterRequest {
    public static function fromArray(array $data): self
}

// src/DTO/TodayViewResponse.php
final readonly class TodayViewResponse {
    public static function fromTasks(array $tasks, array $meta): self
}
```

### 2.3 Response Format Alignment

**Current meta format (from `PaginationHelper`):**
```php
[
    'total' => int,
    'page' => int,
    'limit' => int,
    'totalPages' => int,
]
```

**Original plan specifies different names:**
```php
[
    'current_page' => int,
    'per_page' => int,
    'total' => int,
    'total_pages' => int,
]
```

**Decision:** Align with existing implementation (camelCase per DEVIATIONS.md):
```php
[
    'total' => int,
    'page' => int,          // Keep existing
    'perPage' => int,       // Rename from 'limit'
    'totalPages' => int,
]
```

### 2.4 Include Expansion Pattern

The original plan describes `?include=project,tags,subtasks` but current implementation always includes project and tags in `TaskResponse::fromTask()`.

**Recommended approach:**
1. Keep current behavior (always include project/tags) - minimal overhead
2. Add subtasks expansion parameter for on-demand loading
3. Avoid breaking changes to existing API consumers

### 2.5 Sorting Parameter Standardization

**Current:** No sorting parameters in API
**Planned:** `sort` and `sort_order` parameters

**Implementation approach:**
```php
// Extract sort parameters
$sort = $request->query->get('sort', 'position');
$sortOrder = $request->query->get('sort_order', 'asc');

// Validate sort field
$allowedSorts = ['due_date', 'priority', 'created_at', 'updated_at', 'title', 'position'];
if (!in_array($sort, $allowedSorts, true)) {
    throw ValidationException::forField('sort', 'Invalid sort field');
}
```

---

## 3. Security Considerations from Phase 3

### 3.1 Critical Security Patterns to Apply

Based on Phase 3 review findings, these security patterns MUST be applied in Phase 4:

**3.1.1 Owner Validation in All Queries**
```php
// ALWAYS start with owner filter
$qb = $this->createQueryBuilder('t')
    ->where('t.owner = :owner')
    ->setParameter('owner', $user);
```

**3.1.2 Parent/Related Entity Ownership Validation**
When filtering by project, validate project ownership:
```php
// DON'T
$project = $this->projectRepository->find($projectId);

// DO
$project = $this->projectRepository->findOneByOwnerAndId($user, $projectId);
if ($project === null) {
    throw ProjectNotFoundException::create($projectId);
}
```

**3.1.3 Cache Invalidation on Mutations**
Any saved filter CRUD operations must invalidate relevant caches.

**3.1.4 Input Validation Bounds**
```php
// Limit array sizes
if (count($projectIds) > 100) {
    throw ValidationException::forField('project_id', 'Maximum 100 projects allowed');
}

// Limit date ranges
$maxDateRange = new \DateInterval('P1Y'); // 1 year max
```

### 3.2 SavedFilter Security Requirements

**Ownership Enforcement:**
- All SavedFilter queries MUST be scoped by `user_id`
- SavedFilter CRUD operations MUST validate ownership
- SavedFilter cannot reference other users' projects/tags by ID

**Filter Content Validation:**
```php
// When applying a saved filter, re-validate that referenced entities
// still belong to the user (projects may have been deleted/transferred)
public function applySavedFilter(User $user, SavedFilter $filter): void
{
    $criteria = $filter->getFilters();

    if (isset($criteria['project_id'])) {
        // Verify projects still belong to user
        $this->validateProjectOwnership($user, $criteria['project_id']);
    }
}
```

---

## 4. Updated Sub-Phase Plans

### 4.1 Today View Implementation (Updated)

**Changes from original:**
1. Use existing `TaskResponse` DTO instead of custom format
2. Add `isOverdue` and `overdueDays` computed fields to response
3. Use `TodayViewResponse` DTO for specialized meta

**Updated Tasks:**

- [ ] **4.1.1** Create Today repository method
  ```php
  // src/Repository/TaskRepository.php

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

      // Apply additional filters
      $this->applyFilters($qb, $filters);

      // Apply sorting (default: overdue first, then priority)
      // ...

      return $qb;
  }
  ```

- [ ] **4.1.2** Create TodayViewResponse DTO
  ```php
  // src/DTO/TodayViewResponse.php

  final readonly class TodayViewResponse {
      public function __construct(
          public array $items,
          public array $meta,  // Includes overdueCount, todayCount
      ) {}
  }
  ```

- [ ] **4.1.3** Create Today API endpoint
  ```php
  #[Route('/today', name: 'today', methods: ['GET'])]
  public function today(Request $request): JsonResponse
  ```

- [ ] **4.1.4** Extend TaskResponse with computed fields
  ```php
  // Add to TaskResponse::fromTask()
  public readonly bool $isOverdue,
  public readonly ?int $overdueDays,
  ```

- [ ] **4.1.5** Create Today view template
  - Reference UI-DESIGN-SYSTEM.md for overdue styling
  - Use existing task card component

### 4.2 Upcoming View Implementation (Updated)

**Changes from original:**
1. TaskGroupingService should be lightweight (grouping in controller/service, not repository)
2. User timezone handling must use the established pattern
3. Group boundaries respect `start_of_week` preference

**Updated Tasks:**

- [ ] **4.2.1** Create Upcoming repository method
  ```php
  public function findUpcomingTasksQueryBuilder(
      User $owner,
      array $filters = []
  ): QueryBuilder {
      $today = new \DateTimeImmutable('today');

      return $this->createQueryBuilder('t')
          ->where('t.owner = :owner')
          ->andWhere('t.dueDate > :today')
          ->andWhere('t.status != :completed')
          ->setParameter('owner', $owner)
          ->setParameter('today', $today)
          ->setParameter('completed', Task::STATUS_COMPLETED)
          ->orderBy('t.dueDate', 'ASC');
  }
  ```

- [ ] **4.2.2** Create TaskGroupingService
  ```php
  // src/Service/TaskGroupingService.php

  final class TaskGroupingService {
      /**
       * Groups tasks by time period.
       *
       * @param Task[] $tasks
       * @return array<string, Task[]>
       */
      public function groupByTimePeriod(
          array $tasks,
          string $timezone,
          int $startOfWeek = 0
      ): array {
          // today, tomorrow, this_week, next_week, this_month, later
      }
  }
  ```

- [ ] **4.2.3** Create Upcoming API endpoint with grouping toggle
  ```php
  // ?grouped=true returns grouped structure
  // ?grouped=false returns flat paginated list
  ```

- [ ] **4.2.4** Create UpcomingViewResponse DTO
  ```php
  // src/DTO/UpcomingViewResponse.php

  // Supports both grouped and flat formats
  ```

- [ ] **4.2.5** Create Upcoming view template

### 4.3 Overdue View Implementation (Updated)

**Updated Tasks:**

- [ ] **4.3.1** Create OverdueService
  ```php
  // src/Service/OverdueService.php

  final class OverdueService {
      public function calculateSeverity(\DateTimeInterface $dueDate): string
      {
          $days = $this->getOverdueDays($dueDate);
          return match(true) {
              $days <= 2 => 'low',
              $days <= 7 => 'medium',
              default => 'high',
          };
      }

      public function getOverdueDays(\DateTimeInterface $dueDate): int
      {
          $today = new \DateTimeImmutable('today');
          return $today->diff($dueDate)->days;
      }
  }
  ```

- [ ] **4.3.2** Overdue repository method already exists (`findOverdueByOwner`)
  - Enhance to return QueryBuilder for pagination
  - Add severity sorting option

- [ ] **4.3.3** Create Overdue API endpoint
  ```php
  #[Route('/overdue', name: 'overdue', methods: ['GET'])]
  ```

- [ ] **4.3.4** Create Overdue view template with severity styling

### 4.4 No-Date View Implementation (Updated)

- [ ] **4.4.1** Create No-Date repository method
  ```php
  public function findTasksWithoutDueDateQueryBuilder(User $owner): QueryBuilder
  {
      return $this->createQueryBuilder('t')
          ->where('t.owner = :owner')
          ->andWhere('t.dueDate IS NULL')
          ->andWhere('t.status != :completed')
          ->setParameter('owner', $owner)
          ->setParameter('completed', Task::STATUS_COMPLETED)
          ->orderBy('t.priority', 'DESC')
          ->addOrderBy('t.createdAt', 'DESC');
  }
  ```

- [ ] **4.4.2** Create No-Date API endpoint
- [ ] **4.4.3** Create No-Date view template

### 4.5 Filter System (Updated)

**Architecture Decision:** Keep filtering logic in repository but create helper classes for request parsing.

- [ ] **4.5.1** Create TaskFilterRequest DTO
  ```php
  // src/DTO/TaskFilterRequest.php

  final readonly class TaskFilterRequest {
      public function __construct(
          public ?array $status = null,
          public ?array $projectId = null,
          public ?bool $includeProjectChildren = null,
          public ?array $tagIds = null,
          public ?string $tagMatch = 'any', // 'any' or 'all'
          public ?int $priorityMin = null,
          public ?int $priorityMax = null,
          public ?string $dueBefore = null,
          public ?string $dueAfter = null,
          public ?string $search = null,
          public ?bool $includeArchivedProjects = false,
      ) {}

      public static function fromRequest(Request $request): self
      {
          // Parse query parameters
      }
  }
  ```

- [ ] **4.5.2** Enhance `createFilteredQueryBuilder()` to accept TaskFilterRequest
  ```php
  public function createFilteredQueryBuilder(
      User $owner,
      TaskFilterRequest $filters
  ): QueryBuilder
  ```

- [ ] **4.5.3** Implement tag match logic (AND/OR)
  ```php
  // tag_match=any (default): task has ANY of the specified tags
  // tag_match=all: task has ALL of the specified tags

  if ($filters->tagMatch === 'all') {
      // Use subquery approach for AND logic
      foreach ($filters->tagIds as $tagId) {
          $qb->andWhere(
              $qb->expr()->exists(
                  // Subquery checking tag relationship
              )
          );
      }
  }
  ```

- [ ] **4.5.4** Implement project hierarchy filter
  ```php
  // When filtering by project with include_project_children:
  // 1. Get descendant project IDs
  // 2. Include in project filter

  // Respect project's show_children_tasks setting as default
  $includeChildren = $filters->includeProjectChildren
      ?? $project->getShowChildrenTasks();
  ```

- [ ] **4.5.5** Implement priority range filter
  ```php
  if ($filters->priorityMin !== null) {
      $qb->andWhere('t.priority >= :priorityMin')
          ->setParameter('priorityMin', $filters->priorityMin);
  }
  if ($filters->priorityMax !== null) {
      $qb->andWhere('t.priority <= :priorityMax')
          ->setParameter('priorityMax', $filters->priorityMax);
  }
  ```

### 4.6 Sorting System (Updated)

- [ ] **4.6.1** Create TaskSortRequest DTO
  ```php
  // src/DTO/TaskSortRequest.php

  final readonly class TaskSortRequest {
      public const ALLOWED_FIELDS = [
          'due_date', 'priority', 'created_at', 'updated_at',
          'completed_at', 'title', 'position',
      ];

      public function __construct(
          public string $field = 'position',
          public string $order = 'asc',
      ) {
          if (!in_array($field, self::ALLOWED_FIELDS, true)) {
              throw new \InvalidArgumentException("Invalid sort field: $field");
          }
          if (!in_array($order, ['asc', 'desc'], true)) {
              throw new \InvalidArgumentException("Invalid sort order: $order");
          }
      }
  }
  ```

- [ ] **4.6.2** Apply sorting in QueryBuilder
  ```php
  private function applySorting(QueryBuilder $qb, TaskSortRequest $sort): void
  {
      $fieldMap = [
          'due_date' => 't.dueDate',
          'priority' => 't.priority',
          'created_at' => 't.createdAt',
          // ...
      ];

      $field = $fieldMap[$sort->field];
      $order = strtoupper($sort->order);

      // Handle null values for due_date
      if ($sort->field === 'due_date') {
          $qb->addOrderBy('CASE WHEN t.dueDate IS NULL THEN 1 ELSE 0 END', 'ASC');
      }

      $qb->addOrderBy($field, $order);
  }
  ```

### 4.7 Filter UI Components (Updated)

- [ ] **4.7.1** Create filter-panel.html.twig component
  - Follow UI-DESIGN-SYSTEM.md specifications
  - Alpine.js for interactivity
  - Collapsible panel with active filter count badge

- [ ] **4.7.2** Create active-filters.html.twig component
  - Display active filters as removable chips
  - "Clear all" action

- [ ] **4.7.3** Create sort-dropdown.html.twig component
  - Dropdown with sort options
  - Current sort indicator

- [ ] **4.7.4** Create filter-manager.js
  ```javascript
  // assets/js/filter-manager.js

  export class FilterManager {
      constructor(formElement) {
          this.form = formElement;
          this.init();
      }

      init() {
          // Collect filter values
          // Build query string
          // Update URL and fetch results
      }

      applyFilters() {
          const params = this.collectFilters();
          const url = new URL(window.location);
          // Update URL params and fetch
      }
  }
  ```

### 4.8 Saved Filters (Updated)

**Entity Correction:** Use UUID, not BIGINT.

- [ ] **4.8.1** Create SavedFilter entity
  ```php
  // src/Entity/SavedFilter.php

  #[ORM\Entity(repositoryClass: SavedFilterRepository::class)]
  #[ORM\Table(name: 'saved_filters')]
  class SavedFilter implements UserOwnedInterface
  {
      #[ORM\Id]
      #[ORM\Column(type: 'uuid', unique: true)]
      private string $id;

      #[ORM\ManyToOne(targetEntity: User::class)]
      #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
      private User $owner;

      #[ORM\Column(length: 100)]
      private string $name;

      #[ORM\Column(length: 50, nullable: true)]
      private ?string $icon = null;

      #[ORM\Column(length: 7, nullable: true)]
      #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/')]
      private ?string $color = null;

      #[ORM\Column(type: 'json')]
      private array $filters = [];

      #[ORM\Column(type: 'integer')]
      private int $position = 0;

      #[ORM\Column(type: 'datetime_immutable')]
      private \DateTimeImmutable $createdAt;
  }
  ```

- [ ] **4.8.2** Create migration for saved_filters table

- [ ] **4.8.3** Create SavedFilterRepository with ownership-scoped queries

- [ ] **4.8.4** Create SavedFilter DTOs
  - `CreateSavedFilterRequest`
  - `UpdateSavedFilterRequest`
  - `SavedFilterResponse`

- [ ] **4.8.5** Create SavedFilterController
  ```php
  #[Route('/api/v1/saved-filters', name: 'api_saved_filters_')]
  #[IsGranted('IS_AUTHENTICATED_FULLY')]
  final class SavedFilterController extends AbstractController
  {
      #[Route('', methods: ['GET'])]
      public function list(): JsonResponse

      #[Route('', methods: ['POST'])]
      public function create(Request $request): JsonResponse

      #[Route('/{id}', methods: ['GET'])]
      public function show(string $id): JsonResponse

      #[Route('/{id}', methods: ['PATCH'])]
      public function update(Request $request, string $id): JsonResponse

      #[Route('/{id}', methods: ['DELETE'])]
      public function delete(string $id): JsonResponse

      #[Route('/{id}/apply', methods: ['GET'])]
      public function apply(string $id): JsonResponse
  }
  ```

- [ ] **4.8.6** Add saved filters to sidebar template

---

## 5. New Considerations & Missed Items

### 5.1 Items Missing from Original Plan

**5.1.1 Completed Task History View**
The plan doesn't include a dedicated "Completed" view which is commonly needed:
```php
GET /api/v1/tasks/completed
// Filter: status=completed, sorted by completed_at DESC
```

**5.1.2 Bulk Reschedule for Overdue Tasks**
The plan mentions "Reschedule all to tomorrow" bulk action but doesn't specify the endpoint:
```php
POST /api/v1/tasks/overdue/reschedule-all
{
    "due_date": "tomorrow"
}
```

**5.1.3 Filter Presets for Web UI**
Common filter presets that should be available without creating SavedFilters:
- High Priority (priority >= 3)
- Due This Week
- Unassigned (no project)

**5.1.4 Keyboard Shortcuts for Views**
Plan doesn't specify keyboard shortcuts for switching between views.

### 5.2 Timezone Considerations

**User Timezone Storage:**
```php
// User settings should include timezone
$user->getSettings()['timezone'] ?? 'UTC'
```

**Date Comparison Logic:**
All date comparisons (today, overdue, upcoming) must be performed in user's timezone:
```php
$userTimezone = new \DateTimeZone($user->getSettings()['timezone'] ?? 'UTC');
$today = new \DateTimeImmutable('today', $userTimezone);
```

### 5.3 Performance Considerations

**5.3.1 Index Requirements**
Verify these composite indexes exist for Phase 4 queries:
```sql
CREATE INDEX idx_tasks_owner_status_due ON tasks(owner_id, status, due_date);
CREATE INDEX idx_tasks_owner_due_priority ON tasks(owner_id, due_date, priority);
CREATE INDEX idx_tasks_owner_project_status ON tasks(owner_id, project_id, status);
```

**5.3.2 Eager Loading**
Specialized views should eager-load project and tags:
```php
$qb->leftJoin('t.project', 'p')
   ->addSelect('p')
   ->leftJoin('t.tags', 'tag')
   ->addSelect('tag');
```

**5.3.3 Count Queries**
View endpoints should use separate COUNT queries for meta:
```php
// Avoid loading all entities just to count
$countQb = clone $queryBuilder;
$countQb->select('COUNT(DISTINCT t.id)');
$total = (int) $countQb->getQuery()->getSingleScalarResult();
```

### 5.4 API Consistency Updates

**5.4.1 Parameter Naming**
Standardize on snake_case for query parameters (REST convention):
- `project_id` (not `projectId`)
- `due_before` (not `dueBefore`)
- `tag_ids` (not `tagIds`)
- `sort_order` (not `sortOrder`)

**Note:** This differs from response body (camelCase per DEVIATIONS.md).

**5.4.2 Error Codes for Phase 4**
Add these error codes:
```php
const ERROR_INVALID_FILTER = 'INVALID_FILTER';
const ERROR_INVALID_SORT = 'INVALID_SORT';
const ERROR_SAVED_FILTER_NOT_FOUND = 'SAVED_FILTER_NOT_FOUND';
const ERROR_FILTER_LIMIT_EXCEEDED = 'FILTER_LIMIT_EXCEEDED';
```

---

## 6. Test Coverage Requirements

### 6.1 Unit Tests

```
tests/Unit/Service/
├── TaskGroupingServiceTest.php
├── OverdueServiceTest.php
└── TaskFilterBuilderTest.php (if created)

tests/Unit/DTO/
├── TaskFilterRequestTest.php
├── TaskSortRequestTest.php
├── TodayViewResponseTest.php
└── SavedFilterRequestTest.php
```

**Key Test Cases:**

```php
// TaskGroupingServiceTest
- testGroupByTimePeriodWithSundayStart()
- testGroupByTimePeriodWithMondayStart()
- testGroupByTimePeriodAcrossMonthBoundary()
- testGroupByTimePeriodDuringDSTTransition()
- testEmptyTaskArray()

// OverdueServiceTest
- testCalculateSeverityLow() // 1-2 days
- testCalculateSeverityMedium() // 3-7 days
- testCalculateSeverityHigh() // 7+ days
- testGetOverdueDays()
```

### 6.2 Functional Tests

```
tests/Functional/Api/
├── TodayViewApiTest.php
├── UpcomingViewApiTest.php
├── OverdueViewApiTest.php
├── NoDateViewApiTest.php
├── TaskFilterApiTest.php
├── TaskSortApiTest.php
├── SavedFilterApiTest.php
└── TimezoneEdgeCasesTest.php
```

**Key Test Patterns:**

```php
// Every view endpoint must test:
- testReturnsCorrectTasks()
- testUserIsolation()  // Cannot see other users' tasks
- testPagination()
- testPaginationMeta()
- testWithAdditionalFilters()
- testDefaultSort()
- testSortOverride()
- testIncludeExpansions() // if supported

// SavedFilterApiTest must test:
- testCreateSavedFilter()
- testListOnlyOwnFilters()
- testApplySavedFilter()
- testUpdateSavedFilterOwnershipCheck()
- testDeleteSavedFilterOwnershipCheck()
- testSavedFilterWithInvalidProjectReference()
```

### 6.3 Coverage Requirements

| Component | Required Coverage |
|-----------|-------------------|
| Specialized view services | 90%+ |
| Filter/Sort DTOs | 100% |
| Repository view methods | 85%+ |
| SavedFilter CRUD | 90%+ |
| API endpoints | 80%+ |

---

## 7. Implementation Priority

### Phase 4.A: Core Views (Must Have)

| Priority | Task | Effort | Dependencies |
|----------|------|--------|--------------|
| 1 | Today view endpoint + template | Medium | None |
| 2 | Overdue view endpoint + template | Low | OverdueService |
| 3 | No-Date view endpoint + template | Low | None |
| 4 | Upcoming view endpoint + template | High | TaskGroupingService |

### Phase 4.B: Filter/Sort System (Must Have)

| Priority | Task | Effort | Dependencies |
|----------|------|--------|--------------|
| 5 | TaskFilterRequest DTO | Low | None |
| 6 | Enhanced filtering in repository | Medium | TaskFilterRequest |
| 7 | TaskSortRequest DTO | Low | None |
| 8 | Sorting in repository | Low | TaskSortRequest |

### Phase 4.C: UI Components (Should Have)

| Priority | Task | Effort | Dependencies |
|----------|------|--------|--------------|
| 9 | Filter panel component | Medium | Alpine.js |
| 10 | Active filters chips | Low | Filter panel |
| 11 | Sort dropdown | Low | None |
| 12 | Filter manager JS | Medium | Filter panel |

### Phase 4.D: Saved Filters (Nice to Have)

| Priority | Task | Effort | Dependencies |
|----------|------|--------|--------------|
| 13 | SavedFilter entity + migration | Medium | None |
| 14 | SavedFilter CRUD endpoints | Medium | Entity |
| 15 | Saved filters in sidebar | Low | CRUD |

---

## 8. Files to Create/Modify

### New Files

```
src/DTO/
├── TaskFilterRequest.php
├── TaskSortRequest.php
├── TodayViewResponse.php
├── UpcomingViewResponse.php
├── OverdueViewResponse.php
├── CreateSavedFilterRequest.php
├── UpdateSavedFilterRequest.php
└── SavedFilterResponse.php

src/Service/
├── TaskGroupingService.php
└── OverdueService.php

src/Entity/
└── SavedFilter.php

src/Repository/
└── SavedFilterRepository.php

src/Controller/Api/
├── TaskViewController.php (or extend TaskController)
└── SavedFilterController.php

templates/task/
├── today.html.twig
├── upcoming.html.twig
├── overdue.html.twig
└── no-date.html.twig

templates/components/
├── filter-panel.html.twig
├── active-filters.html.twig
└── sort-dropdown.html.twig

assets/js/
└── filter-manager.js

migrations/
└── VersionXXX_CreateSavedFilters.php

tests/Functional/Api/
├── TodayViewApiTest.php
├── UpcomingViewApiTest.php
├── OverdueViewApiTest.php
├── NoDateViewApiTest.php
├── TaskFilterApiTest.php
├── TaskSortApiTest.php
└── SavedFilterApiTest.php

tests/Unit/Service/
├── TaskGroupingServiceTest.php
└── OverdueServiceTest.php
```

### Files to Modify

```
src/Repository/TaskRepository.php
  - Add findTodayTasksQueryBuilder()
  - Add findUpcomingTasksQueryBuilder()
  - Add findNoDateTasksQueryBuilder()
  - Enhance createFilteredQueryBuilder() for new filters
  - Add sorting support

src/Controller/Api/TaskController.php
  - Add sort/filter parameters to list()
  - (Or create separate TaskViewController)

src/DTO/TaskResponse.php
  - Add isOverdue, overdueDays computed fields

src/DTO/TaskListResponse.php
  - Support additional meta fields (counts)

templates/layout/sidebar.html.twig (or equivalent)
  - Add saved filters section

config/routes.yaml (or annotations)
  - New view endpoints
  - SavedFilter endpoints
```

---

## 9. Conclusion

The Phase 4 plan is comprehensive but requires updates to:

1. **Align with established patterns:** Use UUIDs, existing DTO patterns, camelCase responses
2. **Apply security lessons from Phase 3:** Owner validation, input bounds, cache invalidation
3. **Leverage existing infrastructure:** Filter framework in TaskRepository, PaginationHelper
4. **Add missing items:** Completed view, bulk reschedule, keyboard shortcuts

**Recommended Approach:**
1. Start with core view endpoints (Today, Overdue, No-Date) - these are simpler
2. Implement Upcoming view with grouping service
3. Enhance filter/sort system incrementally
4. Add SavedFilters as final feature if time permits

**Estimated Effort:**
- Core views: 3-4 focused work sessions
- Filter/Sort enhancements: 2-3 sessions
- UI components: 2-3 sessions
- SavedFilters: 2 sessions
- Tests: 2-3 sessions

---

*This review plan was generated from comprehensive analysis of the Phase 4 original plan, current codebase state, Phase 3 review findings, and architectural patterns established in Phases 1-3.*

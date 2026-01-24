# Phase 7: Search & Undo System (Revised)

## Overview
**Duration**: Week 7
**Goal**: Complete the search implementation with dedicated endpoint and UI, and finalize remaining undo system gaps including recurring task undo handling.

## Revision History
- **2026-01-24**: Revised to reflect actual implementation state and architecture evolution

## Current Implementation Status

### Already Implemented (from earlier phases)

**Undo System Core** (85% complete):
- [x] `UndoService` with Redis storage and 60-second TTL
- [x] `UndoToken` immutable value object with serialization
- [x] Atomic token consumption via Lua script (prevents race conditions)
- [x] `TaskUndoService` for task-specific undo operations
- [x] `ProjectUndoService` for project-specific undo operations
- [x] `TaskStateService` for task state serialization (includes recurrence fields)
- [x] `ProjectStateService` for project state serialization
- [x] Undo endpoints: `POST /api/v1/tasks/undo/{token}`, `POST /api/v1/projects/undo/{token}`
- [x] Undo tokens returned from all mutation operations
- [x] `InvalidUndoTokenException` with appropriate error codes
- [x] Unit and functional tests for undo operations
- [x] Recurrence fields (isRecurring, recurrenceRule, recurrenceType, recurrenceEndDate) in state serialization

**Search Infrastructure** (60% complete):
- [x] `search_vector` TSVECTOR column on tasks table
- [x] GIN index `idx_tasks_search_vector` for performance
- [x] Database triggers for auto-updating search vectors (INSERT/UPDATE)
- [x] `TaskRepository::search()` with PostgreSQL @@ operator and ts_rank()
- [x] LIKE-based search in `createAdvancedFilteredQueryBuilder()`
- [x] Autocomplete endpoints for projects and tags
- [x] `search` parameter support on `GET /api/v1/tasks`

**Recurring Tasks (Phase 5)** - Complete:
- [x] Task entity has isRecurring, recurrenceRule, recurrenceType, recurrenceEndDate fields
- [x] `RecurrenceRuleParser` for parsing natural language recurrence rules
- [x] `NextDateCalculator` for calculating next occurrence dates
- [x] `TaskService::changeStatus()` creates next task on recurring task completion
- [x] `TaskStatusResult` DTO returns nextTask when created
- [x] `TaskService::completeForever()` for stopping recurrence

### Remaining Work

**Search System**:
- [ ] Dedicated `/api/v1/search` endpoint with multi-type results
- [ ] Search result highlighting with `ts_headline()`
- [ ] Search UI with dropdown, keyboard navigation
- [ ] Advanced search operators (optional)

**Undo System Gaps**:
- [ ] Toast notification UI with countdown timer
- [ ] Undo button integration in task/project actions
- [ ] **Recurring task completion undo**: Delete auto-created next task when undoing completion
- [ ] Missing `dueTime` field in TaskStateService serialization

---

## Prerequisites
- Phases 1-5 completed
- Redis configured and working
- Database search_vector trigger created
- Recurring tasks functional

---

## Core Design Decisions

### Search Filter Combination Order
```
FTS AND FILTER COMBINATION RULE:

When combining full-text search with other filters, the processing order is:

1. FTS filtering MUST apply FIRST
   - Narrow to documents matching the search query
   - This leverages the GIN index efficiently

2. Standard filters narrow the result set
   - status, project_id, tags, priority, date ranges
   - Applied as additional WHERE clauses

3. Ordering by FTS rank
   - Results ordered by ts_rank() relevance
   - Additional sort fields can be secondary

RATIONALE:
- FTS with GIN index is most selective
- Applying FTS first prevents full table scans
- Filters on indexed columns further reduce results
- Rank ordering makes results useful

EXAMPLE:
SELECT * FROM tasks
WHERE search_vector @@ plainto_tsquery('english', 'meeting')  -- FTS first
  AND owner_id = :ownerId                                      -- Always required
  AND project_id = :projectId                                  -- Additional filter
  AND status = 'pending'                                       -- Additional filter
ORDER BY ts_rank(search_vector, plainto_tsquery('english', 'meeting')) DESC
LIMIT 20;
```

### Search Performance Requirements
```
SEARCH ENDPOINT PERFORMANCE:

The search endpoint should be lightweight enough to handle
multiple requests per second per user (for typeahead/autocomplete).

Target performance:
- < 50ms for simple queries
- < 100ms for filtered queries
- < 200ms for complex multi-type searches

Achieved through:
- GIN index on search_vector
- Debounced frontend requests (300ms)
- Efficient query construction
- Pagination limiting result size
```

### Undo Previous State Definition
```
UNDO PREVIOUS STATE FIELDS:

When storing previous state for undo operations, include these fields:

TASK UPDATE UNDO - Include:
- id (for lookup)
- title
- description
- status
- project_id
- priority
- due_date
- due_time (NEEDED - add to TaskStateService)
- tags (array of tag IDs)
- position
- completedAt
- isRecurring
- recurrenceRule
- recurrenceType
- recurrenceEndDate
- originalTaskId

TASK UPDATE UNDO - Exclude (auto-regenerated):
- search_vector (regenerated by trigger)
- updated_at (set on save)

TASK DELETE UNDO - Include ALL fields:
- All fields from update plus:
- created_at

TASK STATUS CHANGE UNDO - Include:
- status
- completedAt
- nextTaskId (NEEDED - when recurring task creates next instance)

PROJECT ARCHIVE UNDO - Include:
- id
- is_archived (previous value)
- archivedAt
```

### Recurring Task Undo Handling
```
RECURRING TASK COMPLETION UNDO:

When a recurring task is completed, a next task instance is auto-created.
When undoing the completion:

1. Restore the original task's status to previous value (pending/in_progress)
2. Clear the completedAt timestamp
3. DELETE the auto-created next task

SAFETY CHECKS before deleting next task:
- Verify the next task still exists
- Verify the next task hasn't been completed (if completed, don't delete)
- Verify the next task hasn't been significantly modified (same title, still pending)

If any check fails, still restore the original task but log a warning
and don't delete the next task (user may have already worked with it).

IMPLEMENTATION:
- Store nextTaskId in undo token's additional_data or previousState
- On undo, check if nextTask should be deleted
- Use soft validation (don't fail undo if next task cleanup isn't possible)
```

---

## Sub-Phase 7.1: PostgreSQL Full-Text Search Setup

### Status: COMPLETE

The following has already been implemented:

- [x] **7.1.1** Search vector column exists on tasks table
- [x] **7.1.2** Database triggers auto-update search_vector on INSERT/UPDATE
- [x] **7.1.3** GIN index created (`idx_tasks_search_vector`)
- [x] **7.1.4** Search vector uses weighted configuration (title: A, description: B)

### Verification
```sql
-- Verify trigger exists
SELECT tgname, tgenabled
FROM pg_trigger
WHERE tgname LIKE 'tasks_search%';

-- Verify GIN index exists
SELECT indexname FROM pg_indexes
WHERE tablename = 'tasks' AND indexname LIKE '%search%';
```

### Files (Already Created)
```
migrations/Version20240101000006.php  -- search_vector column + GIN index
migrations/Version20240101000007.php  -- triggers for auto-update
```

---

## Sub-Phase 7.2: Search Repository Methods

### Status: PARTIALLY COMPLETE

### Already Implemented

- [x] **7.2.1** Basic search with FTS ranking
  ```php
  // src/Repository/TaskRepository.php (lines 265-298)
  public function search(User $owner, string $query): array
  ```
  - Uses native SQL with `@@ plainto_tsquery()`
  - Orders by `ts_rank()` relevance
  - Injected `$searchLocale` configuration parameter

- [x] **7.2.2** LIKE-based search in filter builder
  - Implemented in `createAdvancedFilteredQueryBuilder()`
  - Case-insensitive matching on title and description

### Remaining Tasks

- [ ] **7.2.3** Implement search with highlights
  ```php
  /**
   * Search tasks with highlighted match snippets.
   *
   * @return array<array{task: Task, titleHighlight: string, descriptionHighlight: string, rank: float}>
   */
  public function searchWithHighlights(User $owner, string $query, int $limit = 20): array
  {
      $sql = "
          SELECT t.id,
              ts_headline(:locale, t.title, q,
                  'StartSel=<mark>, StopSel=</mark>, MaxWords=50') as title_highlight,
              ts_headline(:locale, COALESCE(t.description, ''), q,
                  'StartSel=<mark>, StopSel=</mark>, MaxWords=100') as description_highlight,
              ts_rank(t.search_vector, q) as rank
          FROM tasks t, plainto_tsquery(:locale, :query) q
          WHERE t.search_vector @@ q
            AND t.owner_id = :ownerId
          ORDER BY rank DESC
          LIMIT :limit
      ";

      $results = $this->getEntityManager()
          ->getConnection()
          ->executeQuery($sql, [
              'locale' => $this->searchLocale,
              'query' => $query,
              'ownerId' => $owner->getId(),
              'limit' => $limit,
          ])
          ->fetchAllAssociative();

      // Hydrate Task entities and combine with highlights
      return $this->hydrateSearchResults($results);
  }
  ```

- [ ] **7.2.4** Implement combined FTS + filter search
  ```php
  /**
   * Search with filters following FTS-first approach.
   * Uses the efficient GIN index, then applies additional filters.
   */
  public function searchWithFilters(
      User $owner,
      string $query,
      TaskFilterRequest $filters,
      int $limit = 20
  ): array
  {
      // Build native SQL with FTS as primary filter
      $sql = "
          SELECT t.id, ts_rank(t.search_vector, plainto_tsquery(:locale, :query)) as rank
          FROM tasks t
          WHERE t.owner_id = :ownerId
            AND t.search_vector @@ plainto_tsquery(:locale, :query)
      ";

      $params = [
          'locale' => $this->searchLocale,
          'query' => $query,
          'ownerId' => $owner->getId(),
      ];

      // Apply additional filters
      if ($filters->status !== null) {
          $sql .= " AND t.status = :status";
          $params['status'] = $filters->status;
      }

      if ($filters->projectIds !== null && count($filters->projectIds) > 0) {
          $sql .= " AND t.project_id IN (:projectIds)";
          $params['projectIds'] = $filters->projectIds;
      }

      // ... additional filter conditions

      $sql .= " ORDER BY rank DESC LIMIT :limit";
      $params['limit'] = $limit;

      // Execute and hydrate
      return $this->executeSearchQuery($sql, $params);
  }
  ```

### Completion Criteria
- [x] Basic search returns ranked results
- [ ] Highlights generated with `ts_headline()`
- [ ] Filters combinable with FTS (FTS-first approach)

### Files to Update
```
src/Repository/TaskRepository.php
src/Repository/ProjectRepository.php (for project search)
```

---

## Sub-Phase 7.3: Search API Endpoint

### Status: NOT STARTED

### Objective
Create a dedicated search endpoint that searches across multiple entity types.

### Tasks

- [ ] **7.3.1** Create SearchController
  ```php
  // src/Controller/Api/SearchController.php

  #[Route('/api/v1/search', name: 'api_search_')]
  #[IsGranted('IS_AUTHENTICATED_FULLY')]
  final class SearchController extends AbstractController
  {
      public function __construct(
          private readonly SearchService $searchService,
          private readonly ResponseFormatter $responseFormatter,
      ) {}

      /**
       * Global search across tasks and projects.
       *
       * GET /api/v1/search?q={query}
       *
       * Query params:
       * - q: string (required, min 2 chars)
       * - type: 'all'|'tasks'|'projects' (default: all)
       * - highlight: bool (default: true)
       * - include_completed: bool (default: false)
       * - limit: int (default: 20, max: 50)
       */
      #[Route('', name: 'global', methods: ['GET'])]
      public function search(Request $request): JsonResponse
      {
          /** @var User $user */
          $user = $this->getUser();

          $query = $request->query->getString('q', '');
          if (strlen($query) < 2) {
              throw ValidationException::forField('q', 'Search query must be at least 2 characters');
          }

          $criteria = SearchCriteria::fromRequest($request);
          $result = $this->searchService->search($user, $query, $criteria);

          return $this->responseFormatter->success($result->toArray());
      }
  }
  ```

- [ ] **7.3.2** Create SearchService
  ```php
  // src/Service/SearchService.php

  final class SearchService
  {
      public function __construct(
          private readonly TaskRepository $taskRepository,
          private readonly ProjectRepository $projectRepository,
      ) {}

      public function search(User $user, string $query, SearchCriteria $criteria): SearchResult
      {
          $startTime = microtime(true);

          $results = [
              'tasks' => [],
              'projects' => [],
          ];

          if ($criteria->includesTasks()) {
              $results['tasks'] = $this->searchTasks($user, $query, $criteria);
          }

          if ($criteria->includesProjects()) {
              $results['projects'] = $this->searchProjects($user, $query, $criteria);
          }

          $searchTimeMs = (microtime(true) - $startTime) * 1000;

          return new SearchResult(
              tasks: $results['tasks'],
              projects: $results['projects'],
              searchTimeMs: $searchTimeMs,
              query: $query,
          );
      }
  }
  ```

- [ ] **7.3.3** Create DTOs
  ```php
  // src/DTO/SearchCriteria.php
  final class SearchCriteria
  {
      public function __construct(
          public readonly string $type = 'all',
          public readonly bool $highlight = true,
          public readonly bool $includeCompleted = false,
          public readonly int $limit = 20,
      ) {}

      public static function fromRequest(Request $request): self
      {
          return new self(
              type: $request->query->getString('type', 'all'),
              highlight: $request->query->getBoolean('highlight', true),
              includeCompleted: $request->query->getBoolean('include_completed', false),
              limit: min((int) $request->query->get('limit', '20'), 50),
          );
      }

      public function includesTasks(): bool
      {
          return in_array($this->type, ['all', 'tasks'], true);
      }

      public function includesProjects(): bool
      {
          return in_array($this->type, ['all', 'projects'], true);
      }
  }

  // src/DTO/SearchResult.php
  final class SearchResult
  {
      public function __construct(
          public readonly array $tasks,
          public readonly array $projects,
          public readonly float $searchTimeMs,
          public readonly string $query,
      ) {}

      public function toArray(): array
      {
          return [
              'tasks' => $this->tasks,
              'projects' => $this->projects,
              'meta' => [
                  'query' => $this->query,
                  'taskCount' => count($this->tasks),
                  'projectCount' => count($this->projects),
                  'total' => count($this->tasks) + count($this->projects),
                  'searchTimeMs' => round($this->searchTimeMs, 2),
              ],
          ];
      }
  }
  ```

- [ ] **7.3.4** Define response format with highlights
  ```json
  {
    "success": true,
    "data": {
      "tasks": [
        {
          "id": "uuid",
          "title": "Review meeting notes",
          "titleHighlight": "Review <mark>meeting</mark> notes",
          "descriptionHighlight": "...prepare for <mark>meeting</mark>...",
          "rank": 0.95,
          "status": "pending",
          "isRecurring": true,
          "project": { "id": "uuid", "name": "Work" }
        }
      ],
      "projects": [
        {
          "id": "uuid",
          "name": "Work Meetings",
          "nameHighlight": "Work <mark>Meeting</mark>s",
          "rank": 0.80
        }
      ],
      "meta": {
        "query": "meeting",
        "taskCount": 5,
        "projectCount": 1,
        "total": 6,
        "searchTimeMs": 15.42
      }
    }
  }
  ```

### Completion Criteria
- [ ] Search endpoint returns multi-type results
- [ ] Highlights included when requested
- [ ] Performance metrics in response
- [ ] Validation for minimum query length

### Files to Create
```
src/Controller/Api/SearchController.php
src/Service/SearchService.php
src/DTO/SearchCriteria.php
src/DTO/SearchResult.php
src/DTO/SearchResultItem.php
```

---

## Sub-Phase 7.4: Task Filter Search Integration

### Status: COMPLETE

The existing implementation already supports search via the task list endpoint:

```
GET /api/v1/tasks?search={query}
```

- [x] `search` parameter in `TaskFilterRequest` DTO
- [x] LIKE-based search in `createAdvancedFilteredQueryBuilder()`
- [x] Combined with all other filter parameters

### Enhancement (Optional)
Consider upgrading from LIKE-based search to FTS-based search in the task list endpoint for better relevance ranking:

```php
// In TaskRepository::createAdvancedFilteredQueryBuilder()
// Replace LIKE with FTS when search is provided:

if ($filterRequest->search !== null && $filterRequest->search !== '') {
    // Use FTS instead of LIKE for better ranking
    $qb->andWhere("t.searchVector @@ plainto_tsquery('english', :search)")
       ->setParameter('search', $filterRequest->search)
       ->addOrderBy("ts_rank(t.searchVector, plainto_tsquery('english', :search))", 'DESC');
}
```

---

## Sub-Phase 7.5: Search UI Implementation

### Status: NOT STARTED

### Objective
Create a global search interface with dropdown results.

### Tasks

- [ ] **7.5.1** Create search component template
  ```twig
  {# templates/components/search.html.twig #}

  <div x-data="searchComponent()"
       @keydown.window.slash.prevent="focusSearch()"
       class="relative w-full max-w-md">

      {# Search Input #}
      <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>

          <input type="text"
                 x-ref="searchInput"
                 x-model="query"
                 @input.debounce.300ms="performSearch()"
                 @focus="showResults = results.length > 0"
                 @keydown.arrow-down.prevent="navigateDown()"
                 @keydown.arrow-up.prevent="navigateUp()"
                 @keydown.enter.prevent="selectCurrent()"
                 @keydown.escape="hideResults()"
                 placeholder="Search tasks..."
                 class="w-full rounded-md border-gray-300 shadow-sm text-sm pl-10 pr-12
                        focus:border-indigo-500 focus:ring-indigo-500">

          <span class="absolute right-3 top-1/2 -translate-y-1/2
                       text-xs text-gray-400 bg-gray-100 rounded px-1.5 py-0.5">/</span>
      </div>

      {# Results Dropdown #}
      <div x-show="showResults && (results.tasks.length > 0 || results.projects.length > 0)"
           x-transition:enter="transition ease-out duration-100"
           x-transition:enter-start="opacity-0 scale-95"
           x-transition:enter-end="opacity-100 scale-100"
           x-transition:leave="transition ease-in duration-75"
           x-transition:leave-start="opacity-100 scale-100"
           x-transition:leave-end="opacity-0 scale-95"
           @click.away="hideResults()"
           class="absolute z-20 mt-2 w-full rounded-md bg-white shadow-lg
                  ring-1 ring-black ring-opacity-5 max-h-96 overflow-y-auto">

          {# Tasks Section #}
          <template x-if="results.tasks.length > 0">
              <div>
                  <div class="px-4 py-2 bg-gray-50 text-xs font-semibold text-gray-500
                              uppercase tracking-wider sticky top-0">
                      Tasks <span class="text-gray-400 font-normal lowercase"
                                  x-text="'(' + results.tasks.length + ')'"></span>
                  </div>
                  <template x-for="(task, index) in results.tasks" :key="task.id">
                      <a :href="'/tasks/' + task.id"
                         :class="{'bg-indigo-50': selectedIndex === index}"
                         @mouseenter="selectedIndex = index"
                         class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-100
                                transition-colors cursor-pointer">
                          <div class="flex items-center gap-2">
                              <div class="text-sm font-medium text-gray-900 truncate flex-1"
                                   x-html="task.titleHighlight || task.title"></div>
                              <template x-if="task.isRecurring">
                                  <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                  </svg>
                              </template>
                          </div>
                          <div class="text-xs text-gray-500 mt-0.5"
                               x-text="task.project?.name || 'No project'"></div>
                      </a>
                  </template>
              </div>
          </template>

          {# Projects Section #}
          <template x-if="results.projects.length > 0">
              <div>
                  <div class="px-4 py-2 bg-gray-50 text-xs font-semibold text-gray-500
                              uppercase tracking-wider sticky top-0">
                      Projects <span class="text-gray-400 font-normal lowercase"
                                     x-text="'(' + results.projects.length + ')'"></span>
                  </div>
                  <template x-for="(project, index) in results.projects" :key="project.id">
                      <a :href="'/projects/' + project.id"
                         :class="{'bg-indigo-50': selectedIndex === results.tasks.length + index}"
                         @mouseenter="selectedIndex = results.tasks.length + index"
                         class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-100
                                transition-colors cursor-pointer">
                          <div class="text-sm font-medium text-gray-900"
                               x-html="project.nameHighlight || project.name"></div>
                      </a>
                  </template>
              </div>
          </template>

          {# Empty State #}
          <div x-show="query.length >= 2 && results.tasks.length === 0 && results.projects.length === 0"
               class="py-12 text-center">
              <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <p class="text-sm text-gray-500">No results found</p>
              <p class="text-xs text-gray-400 mt-1">Try different keywords</p>
          </div>
      </div>
  </div>
  ```

- [ ] **7.5.2** Create search JavaScript
  ```javascript
  // assets/js/components/search.js

  function searchComponent() {
      return {
          query: '',
          results: { tasks: [], projects: [] },
          showResults: false,
          selectedIndex: -1,
          loading: false,

          focusSearch() {
              this.$refs.searchInput.focus();
          },

          async performSearch() {
              if (this.query.length < 2) {
                  this.hideResults();
                  return;
              }

              this.loading = true;

              try {
                  const response = await fetch(
                      `/api/v1/search?q=${encodeURIComponent(this.query)}&highlight=true`,
                      {
                          headers: {
                              'Accept': 'application/json',
                              'X-Requested-With': 'XMLHttpRequest'
                          }
                      }
                  );

                  if (!response.ok) throw new Error('Search failed');

                  const data = await response.json();
                  this.results = {
                      tasks: data.data.tasks || [],
                      projects: data.data.projects || []
                  };
                  this.showResults = true;
                  this.selectedIndex = -1;
              } catch (error) {
                  console.error('Search error:', error);
                  this.results = { tasks: [], projects: [] };
              } finally {
                  this.loading = false;
              }
          },

          hideResults() {
              this.showResults = false;
              this.selectedIndex = -1;
          },

          navigateDown() {
              const totalResults = this.results.tasks.length + this.results.projects.length;
              if (totalResults === 0) return;
              this.selectedIndex = Math.min(this.selectedIndex + 1, totalResults - 1);
          },

          navigateUp() {
              this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
          },

          selectCurrent() {
              const totalTasks = this.results.tasks.length;

              if (this.selectedIndex < 0) return;

              if (this.selectedIndex < totalTasks) {
                  window.location.href = `/tasks/${this.results.tasks[this.selectedIndex].id}`;
              } else {
                  const projectIndex = this.selectedIndex - totalTasks;
                  window.location.href = `/projects/${this.results.projects[projectIndex].id}`;
              }
          }
      };
  }
  ```

### Completion Criteria
- [ ] Search input in header/layout
- [ ] Debounced API calls (300ms)
- [ ] Results dropdown with sections
- [ ] Keyboard navigation (arrows, enter, escape)
- [ ] `/` shortcut focuses search
- [ ] Match highlighting with `<mark>` tags
- [ ] Recurring task indicator in search results

### Files to Create
```
templates/components/search.html.twig
assets/js/components/search.js
```

---

## Sub-Phase 7.6: Undo System Implementation

### Status: MOSTLY COMPLETE

### Already Implemented

- [x] **7.6.1** UndoService with Redis storage
  - Location: `src/Service/UndoService.php`
  - 60-second TTL
  - Atomic token consumption via Lua script
  - Key format: `undo:{userId}:{token}`

- [x] **7.6.2** UndoToken value object
  - Location: `src/ValueObject/UndoToken.php`
  - Immutable with serialization support
  - Includes `getRemainingSeconds()` for countdown

- [x] **7.6.3** Entity-specific undo services
  - `TaskUndoService` with `undoDelete()`, `undoUpdate()`
  - `ProjectUndoService` with `undoArchive()`, `undoDelete()`, `undoUpdate()`

- [x] **7.6.4** State serialization services
  - `TaskStateService::serializeTaskState()` - includes all recurrence fields
  - `TaskStateService::serializeStatusState()` - status and completedAt only
  - `ProjectStateService::serializeProjectState()` and `applyStateToProject()`

- [x] **7.6.5** Undo endpoints (entity-specific, not centralized)
  - `POST /api/v1/tasks/undo/{token}` - TaskController::undo()
  - `POST /api/v1/projects/undo/{token}` - ProjectController::undo()

- [x] **7.6.6** Undo tokens returned from mutations
  - TaskService::update(), delete(), changeStatus(), completeForever()
  - ProjectService::update(), delete(), archive()

- [x] **7.6.7** Recurrence fields in state serialization
  - isRecurring, recurrenceRule, recurrenceType, recurrenceEndDate, originalTaskId

### Remaining Tasks

- [ ] **7.6.8** Add missing `dueTime` to TaskStateService
  ```php
  // src/Service/TaskStateService.php
  // In serializeTaskState(), add:
  'dueTime' => $task->getDueTime()?->format('H:i:s'),

  // In Task::restoreFromState(), already handles dueTime if present
  // Just need to add to serialization
  ```

- [ ] **7.6.9** Implement recurring task completion undo with next task deletion

  **Problem**: When a recurring task is completed, `TaskService::changeStatus()` creates a next task.
  The undo token only stores status state, so undoing completion doesn't delete the auto-created next task.

  **Solution**:
  ```php
  // Modify TaskService::changeStatus() to store nextTaskId in undo token

  public function changeStatus(Task $task, string $newStatus): TaskStatusResult
  {
      $this->validationHelper->validateTaskStatus($newStatus);

      // Store previous state for undo
      $previousState = $this->taskStateService->serializeStatusState($task);
      $previousStatus = $task->getStatus();

      $task->setStatus($newStatus);
      $this->entityManager->flush();

      // Handle recurring task completion
      $nextTask = null;
      if ($newStatus === Task::STATUS_COMPLETED
          && $previousStatus !== Task::STATUS_COMPLETED
          && $task->isRecurring()
      ) {
          $nextTask = $this->createNextRecurringInstance($task);

          // ADDED: Store nextTaskId for undo cleanup
          if ($nextTask !== null) {
              $previousState['nextTaskId'] = $nextTask->getId();
          }
      }

      // Create undo token with nextTaskId in state
      $undoToken = $this->taskUndoService->createStatusChangeUndoToken($task, $previousState);

      return new TaskStatusResult($task, $nextTask, $undoToken);
  }
  ```

  ```php
  // Modify TaskUndoService::performUndoUpdate() to handle nextTask deletion

  private function performUndoUpdate(UndoToken $undoToken): Task
  {
      $task = $this->taskRepository->find($undoToken->entityId);

      if ($task === null) {
          throw EntityNotFoundException::task($undoToken->entityId);
      }

      $this->ownershipChecker->checkOwnership($task);

      // ADDED: Handle recurring task completion undo - delete next task if safe
      if (isset($undoToken->previousState['nextTaskId'])) {
          $this->cleanupNextTask($undoToken->previousState['nextTaskId']);
      }

      $this->taskStateService->applyStateToTask($task, $undoToken->previousState);
      $this->entityManager->flush();

      return $task;
  }

  /**
   * Cleans up the auto-created next task when undoing recurring task completion.
   * Only deletes if safe (task exists, still pending, not significantly modified).
   */
  private function cleanupNextTask(string $nextTaskId): void
  {
      $nextTask = $this->taskRepository->find($nextTaskId);

      if ($nextTask === null) {
          // Task already deleted, nothing to do
          return;
      }

      // Safety checks
      if ($nextTask->getStatus() === Task::STATUS_COMPLETED) {
          // User already completed the next task, don't delete
          return;
      }

      // Delete the next task
      $this->entityManager->remove($nextTask);
      // Don't flush yet - let the main undo operation flush
  }
  ```

### Architecture Note

The original plan specified a single `POST /api/v1/undo` endpoint. The actual
implementation uses entity-specific endpoints, which is preferable because:

1. Follows REST conventions (undo is an action on a specific resource type)
2. Allows entity-specific response formatting
3. Clearer API documentation
4. Type-safe return values

**Implemented Pattern**:
```
POST /api/v1/tasks/undo/{token}    -> Returns restored Task
POST /api/v1/projects/undo/{token} -> Returns restored Project with message
```

### Completion Criteria
- [x] Undo tokens stored in Redis with 60s TTL
- [x] Atomic token consumption (one-time use)
- [x] Entity-specific undo endpoints
- [x] All mutation operations return undo tokens
- [x] Recurrence fields in state serialization
- [ ] dueTime included in task state serialization
- [ ] Recurring task completion undo deletes auto-created next task

---

## Sub-Phase 7.7: Undo UI Integration

### Status: NOT STARTED

### Objective
Create toast notifications with undo functionality.

### Tasks

- [ ] **7.7.1** Create toast notification component
  ```twig
  {# templates/components/toast.html.twig #}

  <div x-data="toastManager()"
       @show-toast.window="show($event.detail)"
       class="fixed bottom-4 right-4 z-50 flex flex-col gap-2">

      <template x-for="toast in toasts" :key="toast.id">
          <div x-show="toast.visible"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="translate-y-4 opacity-0"
               x-transition:enter-end="translate-y-0 opacity-100"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-y-0 opacity-100"
               x-transition:leave-end="translate-y-4 opacity-0"
               :class="{
                   'bg-gray-900': toast.type === 'default',
                   'bg-green-900': toast.type === 'success',
                   'bg-red-900': toast.type === 'error'
               }"
               class="text-white rounded-lg shadow-lg px-4 py-3
                      flex items-center gap-4 max-w-md">

              <span class="text-sm flex-1" x-text="toast.message"></span>

              {# Undo button with countdown #}
              <template x-if="toast.undoToken">
                  <button @click="executeUndo(toast)"
                          :disabled="toast.countdown <= 0"
                          class="text-indigo-400 hover:text-indigo-300 font-medium
                                 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                      <span x-text="toast.countdown > 0 ? 'Undo (' + toast.countdown + 's)' : 'Expired'"></span>
                  </button>
              </template>

              {# Close button #}
              <button @click="dismiss(toast.id)"
                      class="text-gray-400 hover:text-gray-200">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                  </svg>
              </button>
          </div>
      </template>
  </div>
  ```

- [ ] **7.7.2** Create toast JavaScript manager
  ```javascript
  // assets/js/components/toast.js

  function toastManager() {
      return {
          toasts: [],
          nextId: 1,

          show({ message, type = 'default', undoToken = null, entityType = null, duration = 5000 }) {
              const id = this.nextId++;
              const toast = {
                  id,
                  message,
                  type,
                  undoToken,
                  entityType,
                  visible: true,
                  countdown: undoToken ? 5 : 0,
                  interval: null
              };

              this.toasts.push(toast);

              // Start countdown for undo toasts
              if (undoToken) {
                  toast.interval = setInterval(() => {
                      toast.countdown--;
                      if (toast.countdown <= 0) {
                          clearInterval(toast.interval);
                      }
                  }, 1000);
              }

              // Auto-dismiss
              setTimeout(() => this.dismiss(id), duration);
          },

          dismiss(id) {
              const toast = this.toasts.find(t => t.id === id);
              if (toast) {
                  toast.visible = false;
                  if (toast.interval) clearInterval(toast.interval);
                  // Remove from array after animation
                  setTimeout(() => {
                      this.toasts = this.toasts.filter(t => t.id !== id);
                  }, 300);
              }
          },

          async executeUndo(toast) {
              if (toast.countdown <= 0 || !toast.undoToken) return;

              const endpoint = toast.entityType === 'project'
                  ? `/api/v1/projects/undo/${toast.undoToken}`
                  : `/api/v1/tasks/undo/${toast.undoToken}`;

              try {
                  const response = await fetch(endpoint, {
                      method: 'POST',
                      headers: {
                          'Accept': 'application/json',
                          'X-Requested-With': 'XMLHttpRequest'
                      }
                  });

                  if (response.ok) {
                      this.dismiss(toast.id);
                      // Show success and refresh
                      this.show({ message: 'Action undone', type: 'success', duration: 3000 });
                      window.location.reload();
                  } else {
                      const data = await response.json();
                      this.show({
                          message: data.error?.message || 'Undo failed',
                          type: 'error',
                          duration: 5000
                      });
                  }
              } catch (error) {
                  this.show({ message: 'Undo failed', type: 'error', duration: 5000 });
              }
          }
      };
  }

  // Helper to show toast from anywhere
  function showToast(options) {
      window.dispatchEvent(new CustomEvent('show-toast', { detail: options }));
  }
  ```

- [ ] **7.7.3** Integrate with task/project actions
  ```javascript
  // Example: After completing a task
  async function completeTask(taskId) {
      const response = await fetch(`/api/v1/tasks/${taskId}/status`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ status: 'completed' })
      });

      const data = await response.json();

      if (data.success && data.data.undoToken) {
          let message = 'Task completed';

          // Show if next task was created for recurring task
          if (data.data.nextTask) {
              message = 'Recurring task completed - next instance created';
          }

          showToast({
              message,
              undoToken: data.data.undoToken,
              entityType: 'task',
              duration: 10000
          });
      }

      refreshTaskList();
  }
  ```

### Completion Criteria
- [ ] Toast notifications appear on undoable actions
- [ ] Countdown timer displays seconds remaining
- [ ] Undo button executes undo API call
- [ ] UI refreshes after successful undo
- [ ] Error handling for expired/invalid tokens
- [ ] Toast is dismissable manually
- [ ] Recurring task completion shows appropriate message

### Files to Create
```
templates/components/toast.html.twig
assets/js/components/toast.js
```

---

## Sub-Phase 7.8: Search & Undo Tests

### Status: PARTIALLY COMPLETE

### Already Implemented

**Undo Tests**:
- [x] `UndoServiceTest` - Token creation, consumption, expiration
- [x] `TaskUndoServiceTest` - Task undo operations, validation
- [x] `ProjectUndoServiceTest` - Project undo operations
- [x] `UndoTokenTest` - Value object serialization
- [x] `TaskApiTest` - Undo endpoint functional tests
- [x] `ProjectApiTest` - Project undo functional tests
- [x] `RecurringTaskApiTest` - Recurring task completion tests

**Search Tests (Basic)**:
- [x] `TaskApiTest::testListTasksFilterBySearch()` - LIKE-based search
- [x] `TaskFilterApiTest::testFilterBySearchTerm()` - Filter integration
- [x] `AutocompleteApiTest` - Prefix search for projects/tags

### Remaining Tests

- [ ] **7.8.1** Search API endpoint tests
  ```php
  // tests/Functional/Api/SearchApiTest.php

  class SearchApiTest extends ApiTestCase
  {
      public function testSearchReturnsMatchingTasks(): void;
      public function testSearchReturnsMatchingProjects(): void;
      public function testSearchRanksResultsByRelevance(): void;
      public function testSearchHighlightsMatches(): void;
      public function testSearchWithTypeFilter(): void;
      public function testSearchExcludesCompletedByDefault(): void;
      public function testSearchIncludesCompletedWhenRequested(): void;
      public function testSearchRequiresMinimumQueryLength(): void;
      public function testSearchEmptyQuery(): void;
      public function testSearchNoResults(): void;
      public function testSearchPerformance(): void; // < 100ms assertion
      public function testSearchRequiresAuthentication(): void;
      public function testSearchOnlyReturnsOwnedEntities(): void;
      public function testSearchIncludesRecurringIndicator(): void;
  }
  ```

- [ ] **7.8.2** FTS repository tests
  ```php
  // tests/Unit/Repository/TaskRepositorySearchTest.php

  class TaskRepositorySearchTest extends KernelTestCase
  {
      public function testSearchUsesFullTextSearch(): void;
      public function testSearchWithHighlightsReturnsMarkupSafeHtml(): void;
      public function testSearchWithFiltersAppliesFtsFirst(): void;
      public function testSearchRankOrdering(): void;
  }
  ```

- [ ] **7.8.3** Recurring task undo tests
  ```php
  // In RecurringTaskApiTest, add:
  public function testUndoRecurringTaskCompletionDeletesNextTask(): void;
  public function testUndoRecurringTaskCompletionWhenNextTaskDeleted(): void;
  public function testUndoRecurringTaskCompletionWhenNextTaskCompleted(): void;
  public function testUndoRecurringTaskCompletionRestoresStatus(): void;
  ```

- [ ] **7.8.4** Additional undo edge case tests
  ```php
  // In TaskUndoServiceTest, add:
  public function testUndoRestoresDueTime(): void;
  public function testUndoRestoresRecurrenceFields(): void;

  // In TaskApiTest, add:
  public function testUndoDeleteRestoresAllFields(): void;
  ```

### Completion Criteria
- [ ] Search endpoint fully tested
- [ ] FTS behavior tested
- [ ] Highlight generation tested
- [ ] Performance assertions included
- [ ] dueTime restoration tested
- [ ] Recurring task undo with next task deletion tested

### Files to Create/Update
```
tests/Functional/Api/SearchApiTest.php (new)
tests/Unit/Repository/TaskRepositorySearchTest.php (new)
tests/Functional/Api/RecurringTaskApiTest.php (update)
tests/Unit/Service/TaskUndoServiceTest.php (update)
tests/Functional/Api/TaskApiTest.php (update)
```

---

## Phase 7 Deliverables Checklist

At the end of Phase 7, the following should be complete:

### Search
- [x] Full-text search index created (GIN)
- [x] Database triggers for search vector updates
- [ ] Dedicated search endpoint (`GET /api/v1/search`)
- [ ] Search returns ranked results with highlights
- [x] Uses PostgreSQL @@ operator
- [x] Prefix search/autocomplete for projects and tags
- [ ] Search UI with dropdown
- [ ] Keyboard navigation (arrows, enter, escape, /)
- [ ] Performance < 100ms

### Undo System
- [x] Undo tokens stored in Redis
- [x] 60-second TTL enforced
- [x] Atomic token consumption (one-time use)
- [x] Entity-specific undo endpoints functional
- [x] All mutation operations return tokens
- [x] Token validated against user
- [x] Recurrence fields in state serialization
- [ ] dueTime included in state serialization
- [ ] Recurring task completion undo deletes auto-created next task

### UI
- [ ] Toast notifications with countdown
- [ ] Undo button functional in UI
- [ ] Error handling for expired/invalid tokens

### Testing
- [x] Undo operations tested
- [x] Recurring task completion tested
- [ ] Search endpoint tested
- [ ] FTS behavior tested
- [ ] Performance assertions
- [ ] Recurring task undo with next task cleanup tested

---

## Implementation Order Recommendation

1. **Sub-Phase 7.6.8**: Add `dueTime` to TaskStateService (quick fix)
2. **Sub-Phase 7.6.9**: Implement recurring task undo with next task deletion
3. **Sub-Phase 7.8.3**: Add recurring task undo tests
4. **Sub-Phase 7.2.3-7.2.4**: Complete repository search methods with highlights
5. **Sub-Phase 7.3**: Create SearchController and SearchService
6. **Sub-Phase 7.8.1-7.8.2**: Add search tests
7. **Sub-Phase 7.5**: Implement search UI
8. **Sub-Phase 7.7**: Implement toast notifications with undo
9. **Sub-Phase 7.8.4**: Add remaining undo tests

This order prioritizes:
1. Critical undo functionality gaps (recurring task cleanup)
2. Backend search completion
3. UI implementation last (allows parallel frontend development)

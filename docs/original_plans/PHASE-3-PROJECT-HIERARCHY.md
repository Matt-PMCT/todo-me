# Phase 3: Project Hierarchy & Archiving

## Overview
**Duration**: Week 3-4  
**Goal**: Implement nested project support with unlimited depth, the show_children_tasks setting, project tree UI, and project archiving functionality.

## Prerequisites
- Phase 1 & 2 completed
- Basic project CRUD functional
- Project hashtag parsing working

---

## Core Design Decisions

### Task Count Semantics
```
TASK COUNT RULES (DIRECT ONLY):

task_count: Number of tasks assigned DIRECTLY to this project only
- Does NOT include tasks from descendant projects
- Includes tasks of any status (pending, in_progress, completed)

pending_task_count: Number of non-completed tasks assigned DIRECTLY to this project
- Does NOT include pending tasks from descendant projects
- status != 'completed'

RATIONALE:
- Direct counts are simpler to compute and cache
- UI can compute rolled-up totals client-side if needed
- Avoids expensive recursive queries on every tree load
- Matches user mental model: "10 tasks in Work" means 10 tasks assigned to Work

EXAMPLE:
Project: Work (3 tasks: 2 pending, 1 completed)
  └── Meetings (5 tasks: 4 pending, 1 completed)
      └── Weekly (2 tasks: 2 pending)

API Response:
Work:      { task_count: 3, pending_task_count: 2 }
Meetings:  { task_count: 5, pending_task_count: 4 }
Weekly:    { task_count: 2, pending_task_count: 2 }
```

### Project Position and Ordering
```
POSITION RULES:

1. Position values:
   - Position is a non-negative integer (0, 1, 2, ...)
   - NULL positions are NOT allowed - normalize to 0 on save
   - Position is scoped to siblings (children of same parent)

2. Ordering:
   - Children are ordered by position ASC, then by id ASC (for stability)
   - Lower position = appears first in list

3. Reordering operations:
   - ALWAYS normalize to gapless sequence after reorder
   - Example: deleting position 1 from [0,1,2] → renumber to [0,1]
   - Example: inserting at position 1 in [0,1,2] → [0,1,2,3]

4. New projects:
   - Default position = MAX(sibling positions) + 1
   - First child of a parent gets position 0

5. Moving projects:
   - When moving to new parent, assign position = MAX(new sibling positions) + 1
   - Or specify explicit position in request
```

### Hierarchy Depth Limit
```
DEPTH POLICY:

- No coded depth limit - unlimited nesting is structurally supported
- Deep nesting (>5 levels) may impact UI usability but is allowed
- UI should handle deep trees gracefully:
  - Collapse by default beyond level 3
  - Horizontal scrolling or truncated breadcrumbs for deep paths
  - Warning shown when creating project at depth > 5
```

---

## Sub-Phase 3.1: Project Hierarchy Database Support

### Objective
Ensure database properly supports hierarchical projects.

### Tasks

- [ ] **3.1.1** Verify Project entity parent relationship
  ```php
  // src/Entity/Project.php
  
  Relationships:
  - parent: ManyToOne self-referencing
  - children: OneToMany self-referencing
  
  /**
   * @ORM\ManyToOne(targetEntity=Project::class, inversedBy="children")
   * @ORM\JoinColumn(onDelete="CASCADE")
   */
  private ?Project $parent = null;
  
  /**
   * @ORM\OneToMany(targetEntity=Project::class, mappedBy="parent")
   * @ORM\OrderBy({"position" = "ASC", "id" = "ASC"})
   */
  private Collection $children;
  ```

- [ ] **3.1.2** Add circular reference prevention
  ```php
  // src/Entity/Project.php
  
  public function setParent(?Project $parent): self
  {
      if ($parent !== null) {
          // Cannot be own parent
          if ($parent->getId() === $this->getId()) {
              throw new ProjectCannotBeOwnParentException();
          }
          // Cannot create circular reference
          if ($this->wouldCreateCircularReference($parent)) {
              throw new ProjectCircularReferenceException();
          }
      }
      $this->parent = $parent;
      return $this;
  }
  
  private function wouldCreateCircularReference(Project $parent): bool
  {
      $current = $parent;
      while ($current !== null) {
          if ($current->getId() === $this->getId()) {
              return true;
          }
          $current = $current->getParent();
      }
      return false;
  }
  ```

- [ ] **3.1.3** Create helper methods for hierarchy
  ```php
  // src/Entity/Project.php
  
  public function getDepth(): int
  public function getAncestors(): array  // From root to direct parent
  public function getPath(): array       // Full path including self
  public function getFullPathString(): string  // "Parent/Child/Grandchild"
  public function isDescendantOf(Project $ancestor): bool
  public function isAncestorOf(Project $descendant): bool
  public function getAllDescendants(): array  // Recursive
  ```

- [ ] **3.1.4** Implement position normalization
  ```php
  // Ensure position is never null
  public function setPosition(?int $position): self
  {
      $this->position = $position ?? 0;
      return $this;
  }
  ```

### Completion Criteria
- [ ] Parent-child relationships work
- [ ] Circular references prevented with specific exceptions
- [ ] Self-parent prevented with specific exception
- [ ] Helper methods functional
- [ ] Position never null (normalized to 0)
- [ ] Cascade delete works (delete parent → children deleted)

### Files to Update/Create
```
src/Entity/Project.php (updated)
src/Exception/ProjectCannotBeOwnParentException.php (new)
src/Exception/ProjectCircularReferenceException.php (new)

tests/Unit/Entity/ProjectTest.php
```

---

## Sub-Phase 3.2: Hierarchy Error Codes

### Objective
Define specific error codes for all hierarchy-related validation failures.

### Tasks

- [ ] **3.2.1** Create hierarchy exception classes
  ```php
  // src/Exception/ProjectCannotBeOwnParentException.php
  class ProjectCannotBeOwnParentException extends DomainException
  {
      public function getErrorCode(): string
      {
          return 'PROJECT_CANNOT_BE_OWN_PARENT';
      }
      
      public function getHttpStatusCode(): int
      {
          return 422; // Unprocessable Entity
      }
  }
  
  // src/Exception/ProjectCircularReferenceException.php
  class ProjectCircularReferenceException extends DomainException
  {
      public function getErrorCode(): string
      {
          return 'PROJECT_CIRCULAR_REFERENCE';
      }
      
      public function getHttpStatusCode(): int
      {
          return 422;
      }
  }
  
  // src/Exception/ProjectMoveToDescendantException.php
  class ProjectMoveToDescendantException extends DomainException
  {
      public function getErrorCode(): string
      {
          return 'PROJECT_MOVE_TO_DESCENDANT';
      }
      
      public function getHttpStatusCode(): int
      {
          return 422;
      }
  }
  
  // src/Exception/ProjectMoveToArchivedException.php
  class ProjectMoveToArchivedException extends DomainException
  {
      public function getErrorCode(): string
      {
          return 'PROJECT_CANNOT_MOVE_TO_ARCHIVED_PARENT';
      }
      
      public function getHttpStatusCode(): int
      {
          return 422;
      }
  }
  
  // src/Exception/ProjectParentNotFoundException.php
  class ProjectParentNotFoundException extends DomainException
  {
      public function getErrorCode(): string
      {
          return 'PROJECT_PARENT_NOT_FOUND';
      }
      
      public function getHttpStatusCode(): int
      {
          return 422;
      }
  }
  
  // src/Exception/ProjectParentNotOwnedException.php
  class ProjectParentNotOwnedException extends DomainException
  {
      public function getErrorCode(): string
      {
          return 'PROJECT_PARENT_NOT_OWNED_BY_USER';
      }
      
      public function getHttpStatusCode(): int
      {
          return 403; // Forbidden
      }
  }
  ```

- [ ] **3.2.2** Update ApiExceptionListener to handle hierarchy exceptions
  ```php
  // Add to ApiExceptionListener exception mapping
  
  ProjectCannotBeOwnParentException::class => [422, 'PROJECT_CANNOT_BE_OWN_PARENT'],
  ProjectCircularReferenceException::class => [422, 'PROJECT_CIRCULAR_REFERENCE'],
  ProjectMoveToDescendantException::class => [422, 'PROJECT_MOVE_TO_DESCENDANT'],
  ProjectMoveToArchivedException::class => [422, 'PROJECT_CANNOT_MOVE_TO_ARCHIVED_PARENT'],
  ProjectParentNotFoundException::class => [422, 'PROJECT_PARENT_NOT_FOUND'],
  ProjectParentNotOwnedException::class => [403, 'PROJECT_PARENT_NOT_OWNED_BY_USER'],
  ```

- [ ] **3.2.3** Document error responses
  ```php
  // Example error response for circular reference:
  HTTP 422 Unprocessable Entity
  {
    "error": {
      "code": "PROJECT_CIRCULAR_REFERENCE",
      "message": "Cannot move project under its own descendant",
      "details": {
        "project_id": 5,
        "attempted_parent_id": 10
      }
    },
    "meta": {
      "timestamp": "2026-01-23T10:30:00Z",
      "request_id": "abc123"
    }
  }
  ```

### Completion Criteria
- [ ] All hierarchy exceptions created with proper error codes
- [ ] ApiExceptionListener maps exceptions to correct HTTP status
- [ ] Error responses include helpful details

### Files to Create
```
src/Exception/
├── ProjectCannotBeOwnParentException.php
├── ProjectCircularReferenceException.php
├── ProjectMoveToDescendantException.php
├── ProjectMoveToArchivedException.php
├── ProjectParentNotFoundException.php
└── ProjectParentNotOwnedException.php

src/EventListener/ApiExceptionListener.php (updated)
```

---

## Sub-Phase 3.3: Project Repository Hierarchy Queries

### Objective
Implement repository methods for efficient hierarchy queries.

### Tasks

- [ ] **3.3.1** Create tree retrieval method
  ```php
  // src/Repository/ProjectRepository.php
  
  /**
   * Get all projects as a tree structure
   * @return array<Project> Root-level projects with children loaded
   */
  public function getTreeByUser(User $user, bool $includeArchived = false): array
  {
      // Eager load all projects in one query
      // Build tree in memory
  }
  ```

- [ ] **3.3.2** Create descendant retrieval method
  ```php
  /**
   * Get all descendant project IDs for a project
   * Used for task queries with include_children
   */
  public function getDescendantIds(Project $project): array
  
  // Using recursive CTE for efficiency:
  WITH RECURSIVE descendants AS (
      SELECT id FROM projects WHERE id = :projectId
      UNION ALL
      SELECT p.id FROM projects p
      JOIN descendants d ON p.parent_id = d.id
  )
  SELECT id FROM descendants;
  ```

- [ ] **3.3.3** Create ancestor retrieval method
  ```php
  /**
   * Get all ancestor project IDs
   * Used for breadcrumb navigation
   */
  public function getAncestorIds(Project $project): array
  ```

- [ ] **3.3.4** Optimize with single query loading
  ```php
  /**
   * Load all user projects with task counts in one query
   * 
   * TASK COUNT SEMANTICS:
   * - task_count: direct tasks only (any status)
   * - pending_task_count: direct tasks with status != 'completed'
   */
  public function getTreeWithTaskCounts(User $user, bool $includeArchived = false): array
  {
      // SELECT p.*, 
      //   COUNT(t.id) as task_count,
      //   COUNT(CASE WHEN t.status != 'completed' THEN 1 END) as pending_task_count
      // FROM projects p
      // LEFT JOIN tasks t ON t.project_id = p.id
      // WHERE p.user_id = :userId
      //   AND (p.is_archived = false OR :includeArchived = true)
      // GROUP BY p.id
      // ORDER BY p.parent_id NULLS FIRST, p.position ASC, p.id ASC
  }
  ```

- [ ] **3.3.5** Implement position normalization on siblings
  ```php
  /**
   * Renumber positions to be gapless (0, 1, 2, ...)
   * Called after reorder, delete, or move operations
   */
  public function normalizePositions(User $user, ?int $parentId): void
  {
      // Get all siblings ordered by position, id
      // Reassign positions as 0, 1, 2, ...
  }
  ```

### Completion Criteria
- [ ] Tree retrieval returns proper hierarchy
- [ ] Descendant query efficient (uses CTE)
- [ ] Task counts are direct only (not rolled up)
- [ ] No N+1 queries
- [ ] Position normalization maintains gapless sequences

### Files to Update
```
src/Repository/ProjectRepository.php (updated)

tests/Unit/Repository/ProjectRepositoryTest.php
```

---

## Sub-Phase 3.4: Show Children Tasks Setting

### Objective
Implement the `show_children_tasks` setting that controls whether parent projects display tasks from descendants.

### Tasks

- [ ] **3.4.1** Add setting to Project entity
  ```php
  // src/Entity/Project.php
  
  /**
   * @ORM\Column(type="boolean", options={"default": true})
   */
  private bool $showChildrenTasks = true;
  
  public function getShowChildrenTasks(): bool
  public function setShowChildrenTasks(bool $show): self
  ```

- [ ] **3.4.2** Update TaskRepository for hierarchy-aware queries
  ```php
  // src/Repository/TaskRepository.php
  
  public function findByProject(
      Project $project, 
      bool $includeChildren = null,  // null = use project setting
      bool $includeArchivedProjects = false
  ): array {
      $includeChildren ??= $project->getShowChildrenTasks();
      
      if ($includeChildren) {
          $projectIds = $this->projectRepository->getDescendantIds($project);
          $projectIds[] = $project->getId();
          
          if (!$includeArchivedProjects) {
              // Filter out archived project IDs
              $projectIds = $this->filterOutArchivedProjectIds($projectIds);
          }
          
          // Query: WHERE project_id IN (:projectIds)
      } else {
          // Query: WHERE project_id = :projectId
      }
  }
  ```

- [ ] **3.4.3** Update API to support include_children override
  ```php
  GET /api/v1/projects/{id}/tasks?include_children=true
  GET /api/v1/projects/{id}/tasks?include_children=false
  GET /api/v1/projects/{id}/tasks  // Uses project.show_children_tasks
  ```

- [ ] **3.4.4** Define include_archived_projects interaction
  ```php
  INCLUDE_ARCHIVED_PROJECTS BEHAVIOR:
  
  GET /api/v1/projects/{id}/tasks?include_archived_projects=true
  
  This parameter controls whether tasks from ARCHIVED DESCENDANT PROJECTS
  are included when querying with include_children=true.
  
  It does NOT override show_children_tasks - it works alongside it:
  
  | show_children_tasks | include_archived_projects | Result |
  |---------------------|---------------------------|--------|
  | true                | false (default)           | Tasks from active descendants only |
  | true                | true                      | Tasks from ALL descendants (including archived) |
  | false               | false                     | Direct tasks only |
  | false               | true                      | Direct tasks only (no descendants queried) |
  
  Note: The project being queried (id in URL) is always included
  regardless of its archived status.
  ```

- [ ] **3.4.5** Create project settings update endpoint
  ```php
  PATCH /api/v1/projects/{id}/settings
  {
      "show_children_tasks": false
  }
  ```

### Completion Criteria
- [ ] Setting stored per project
- [ ] Task queries respect setting
- [ ] API override parameter works
- [ ] include_archived_projects interaction documented and tested
- [ ] Default is true (show children)

### Files to Update
```
src/Entity/Project.php (updated)
src/Repository/TaskRepository.php (updated)
src/Controller/Api/ProjectController.php (updated)

tests/Functional/Api/ProjectTasksApiTest.php
```

---

## Sub-Phase 3.5: Project Tree API Endpoint

### Objective
Create API endpoint that returns hierarchical project structure.

### Tasks

- [ ] **3.5.1** Create tree endpoint
  ```php
  GET /api/v1/projects/tree
  
  Query params:
  - include_archived: boolean (default: false)
  - include_task_counts: boolean (default: true)
  
  Response:
  {
    "data": [
      {
        "id": 1,
        "name": "Work",
        "color": "#3498db",
        "icon": "💼",
        "position": 0,
        "is_archived": false,
        "show_children_tasks": true,
        "task_count": 15,          // Direct tasks only
        "pending_task_count": 10,  // Direct pending tasks only
        "depth": 0,
        "children": [
          {
            "id": 5,
            "name": "Meetings",
            "color": "#e74c3c",
            "icon": null,
            "position": 0,
            "is_archived": false,
            "show_children_tasks": true,
            "task_count": 5,
            "pending_task_count": 3,
            "depth": 1,
            "children": []
          }
        ]
      },
      {
        "id": 2,
        "name": "Personal",
        "color": "#2ecc71",
        "position": 1,
        ...
      }
    ]
  }
  ```

- [ ] **3.5.2** Create ProjectTreeTransformer
  ```php
  // src/Transformer/ProjectTreeTransformer.php
  
  public function transformTree(array $projects): array
  {
      // Build nested structure from flat list
      // Include computed fields (task_count, pending_task_count)
      // Order children by position ASC, id ASC
  }
  ```

- [ ] **3.5.3** Implement caching for tree with proper key structure
  ```php
  // src/Service/ProjectCacheService.php
  
  /**
   * CACHE KEY STRUCTURE:
   * 
   * Cache is stored per-user with variant flags to ensure correctness
   * when toggling options.
   * 
   * Key format: project_tree:{userId}:{includeArchived}:{includeTaskCounts}
   * 
   * Examples:
   * - project_tree:42:0:1 (user 42, no archived, with counts)
   * - project_tree:42:1:1 (user 42, with archived, with counts)
   * - project_tree:42:0:0 (user 42, no archived, no counts)
   */
  public function getCacheKey(int $userId, bool $includeArchived, bool $includeTaskCounts): string
  {
      return sprintf('project_tree:%d:%d:%d', 
          $userId,
          (int) $includeArchived,
          (int) $includeTaskCounts
      );
  }
  
  /**
   * Invalidate ALL tree cache variants for a user.
   * Called on project create/update/delete/archive/unarchive.
   */
  public function invalidateUserTreeCache(int $userId): void
  {
      // Delete all 4 possible variants:
      // project_tree:{userId}:0:0
      // project_tree:{userId}:0:1
      // project_tree:{userId}:1:0
      // project_tree:{userId}:1:1
  }
  
  /**
   * Invalidate task count variants only.
   * Called on task create/update/delete.
   */
  public function invalidateUserTaskCountCache(int $userId): void
  {
      // Delete only variants with task counts:
      // project_tree:{userId}:0:1
      // project_tree:{userId}:1:1
  }
  
  // TTL: 5 minutes
  ```

### Completion Criteria
- [ ] Tree endpoint returns nested structure
- [ ] Task counts are direct only (per semantics above)
- [ ] Children ordered by position ASC, id ASC
- [ ] Archived projects excluded by default
- [ ] Cache key incorporates user_id, include_archived, include_task_counts
- [ ] Caching reduces database queries

### Files to Create/Update
```
src/Controller/Api/ProjectController.php (updated)
src/Transformer/ProjectTreeTransformer.php (new)
src/Service/ProjectCacheService.php (new)

tests/Functional/Api/ProjectTreeApiTest.php
```

---

## Sub-Phase 3.6: Project CRUD with Hierarchy

### Objective
Update project CRUD operations to properly handle hierarchy.

### Tasks

- [ ] **3.6.1** Update project creation with parent
  ```php
  POST /api/v1/projects
  {
    "name": "Meetings",
    "parent_id": 1,
    "color": "#e74c3c"
  }
  
  Validation:
  - parent_id must exist → PROJECT_PARENT_NOT_FOUND (422)
  - parent_id must belong to user → PROJECT_PARENT_NOT_OWNED_BY_USER (403)
  - parent cannot be archived → PROJECT_CANNOT_MOVE_TO_ARCHIVED_PARENT (422)
  
  Position assignment:
  - New project gets position = MAX(sibling positions) + 1
  - First child gets position = 0
  ```

- [ ] **3.6.2** Implement project move (change parent)
  ```php
  PATCH /api/v1/projects/{id}
  {
    "parent_id": 5  // Move under project 5
  }
  
  // Or move to root level
  {
    "parent_id": null
  }
  
  Validation:
  - Cannot set self as parent → PROJECT_CANNOT_BE_OWN_PARENT (422)
  - Cannot create circular reference → PROJECT_CIRCULAR_REFERENCE (422)
  - Cannot move to own descendant → PROJECT_MOVE_TO_DESCENDANT (422)
  - Cannot move to archived project → PROJECT_CANNOT_MOVE_TO_ARCHIVED_PARENT (422)
  - Parent must exist → PROJECT_PARENT_NOT_FOUND (422)
  - Parent must belong to user → PROJECT_PARENT_NOT_OWNED_BY_USER (403)
  
  Post-move actions:
  - Normalize positions in old parent's children
  - Assign new position in new parent (MAX + 1)
  ```

- [ ] **3.6.3** Implement project reordering
  ```php
  PATCH /api/v1/projects/{id}/position
  {
    "position": 2,
    "parent_id": null  // Optional: move and reorder simultaneously
  }
  
  // Or batch reorder
  POST /api/v1/projects/reorder
  {
    "order": [
      { "id": 1, "position": 0 },
      { "id": 2, "position": 1 },
      { "id": 3, "position": 2 }
    ]
  }
  
  Post-reorder: Normalize positions to gapless sequence
  ```

- [ ] **3.6.4** Handle project deletion with children
  ```php
  DELETE /api/v1/projects/{id}
  
  Behavior:
  - By default: Archives project (sets is_archived = true)
  - Children remain (their parent still points to archived project)
  - Tasks remain with their project_id
  
  DELETE /api/v1/projects/{id}?permanent=true
  - Hard deletes project
  - Cascades to children (all deleted)
  - Tasks have project_id set to NULL
  
  Post-delete: Normalize sibling positions
  ```

### Completion Criteria
- [ ] Projects can be created with parent
- [ ] Projects can be moved in hierarchy
- [ ] All validation errors use correct error codes
- [ ] Position/ordering works with gapless normalization
- [ ] Circular references prevented
- [ ] Delete behavior correct

### Files to Update
```
src/Controller/Api/ProjectController.php (updated)
src/Service/ProjectService.php (updated)

tests/Functional/Api/ProjectHierarchyApiTest.php
```

---

## Sub-Phase 3.7: Project Archiving System

### Objective
Implement project archiving as alternative to deletion.

### Tasks

- [ ] **3.7.1** Create archive/unarchive endpoints
  ```php
  POST /api/v1/projects/{id}/archive
  
  Response:
  {
    "data": {
      "id": 5,
      "name": "Old Project",
      "is_archived": true,
      "archived_at": "2026-01-23T10:30:00Z"
    },
    "undo_token": "undo_xyz123"
  }
  
  POST /api/v1/projects/{id}/unarchive
  ```

- [ ] **3.7.2** Add archived_at timestamp
  ```php
  // src/Entity/Project.php
  
  /**
   * @ORM\Column(type="datetime_immutable", nullable=true)
   */
  private ?DateTimeImmutable $archivedAt = null;
  ```

- [ ] **3.7.3** Define behavior for children of archived projects
  ```php
  CHILDREN OF ARCHIVED PROJECTS:
  
  When a project is archived:
  - Children remain pointing to the archived parent
  - Children can remain UNARCHIVED (mixed state is allowed)
  - Unarchived children appear in normal project tree
  - Archived parent is shown in breadcrumbs with visual indicator
  
  Editing children of archived parents:
  - Allowed: Edit name, color, settings, etc.
  - Allowed: Add tasks to the child project
  - Allowed: Archive the child (independent of parent)
  
  Moving children of archived parents:
  - Allowed: Move child to different parent
  - Allowed: Move child to root level
  - After move, child's old archived parent is unchanged
  
  Cannot:
  - Move a project UNDER an archived parent (error: PROJECT_CANNOT_MOVE_TO_ARCHIVED_PARENT)
  - Create new project UNDER an archived parent (same error)
  ```

- [ ] **3.7.4** Define breadcrumb behavior with archived ancestors
  ```php
  BREADCRUMB / PATH DISPLAY:
  
  When displaying project path (e.g., "Work / Meetings / Weekly"):
  
  1. Always include archived ancestors in the path
     - They are part of the logical hierarchy
     - Path: "Work / [Archived] Meetings / Weekly"
  
  2. Visual distinction for archived ancestors:
     - Gray text or strikethrough styling
     - [Archived] badge/icon
     - CSS class: .project-archived-ancestor
  
  3. Clicking archived ancestor in breadcrumb:
     - In normal view: Navigate to archived projects list, highlight that project
     - In archive view: Navigate to that project's detail
  
  4. API response includes full path regardless of archive status:
     GET /api/v1/projects/{id}
     {
       "data": {
         "id": 10,
         "name": "Weekly",
         "path": [
           { "id": 1, "name": "Work", "is_archived": false },
           { "id": 5, "name": "Meetings", "is_archived": true },
           { "id": 10, "name": "Weekly", "is_archived": false }
         ]
       }
     }
  ```

- [ ] **3.7.5** Create archived projects list endpoint
  ```php
  GET /api/v1/projects/archived
  
  Response:
  {
    "data": [
      {
        "id": 5,
        "name": "Old Project",
        "is_archived": true,
        "archived_at": "2026-01-23T10:30:00Z",
        "task_count": 10,
        "path": [
          { "id": 1, "name": "Work", "is_archived": false }
        ]
      }
    ]
  }
  ```

- [ ] **3.7.6** Update task queries to handle archived projects
  ```php
  // Tasks in archived projects still appear in:
  // - Today view (if due today)
  // - Overdue view
  // - Search results (see search behavior below)
  
  // Tasks in archived projects do NOT appear in:
  // - ALL tasks view (unless explicitly included)
  // - Project's own task list when accessed from archive UI
  //   shows the tasks, but they're visually muted
  
  GET /api/v1/tasks?include_archived_projects=true
  ```

- [ ] **3.7.7** Define search behavior with archived projects
  ```php
  SEARCH AND ARCHIVED PROJECTS:
  
  GET /api/v1/search?q=meeting
  
  Default behavior:
  - Tasks in archived projects ARE included in search results
  - Archived projects themselves are NOT included in search results
  
  With parameter:
  GET /api/v1/search?q=meeting&include_archived_projects=true
  - Tasks in archived projects: included
  - Archived projects: included in results
  
  GET /api/v1/search?q=meeting&include_archived_projects=false
  - Tasks in archived projects: excluded
  - Archived projects: excluded
  
  RATIONALE:
  - Users often need to find old tasks even if project is archived
  - But archived projects cluttering project search is less useful
  ```

- [ ] **3.7.8** Implement cascade archive
  ```php
  // When archiving parent project:
  // Option 1: Archive all descendants (cascade)
  // Option 2: Move children to grandparent
  
  POST /api/v1/projects/{id}/archive?cascade=true
  POST /api/v1/projects/{id}/archive?promote_children=true
  ```

### Completion Criteria
- [ ] Archive endpoint functional
- [ ] Unarchive endpoint functional
- [ ] Children of archived projects behave correctly
- [ ] Breadcrumbs show archived ancestors with visual indicator
- [ ] Archived projects list accessible
- [ ] Tasks in archived projects still queryable
- [ ] Search behavior documented and implemented
- [ ] Undo token provided for archive

### Files to Update/Create
```
src/Entity/Project.php (updated)
src/Controller/Api/ProjectController.php (updated)
src/Service/ProjectService.php (updated)

tests/Functional/Api/ProjectArchiveApiTest.php
```

---

## Sub-Phase 3.8: Project Tree UI

### Objective
Create the frontend UI for displaying and interacting with project hierarchy.

### Tasks

- [ ] **3.8.1** Create project tree component
  ```twig
  {# templates/components/project-tree.html.twig #}

  Structure:
  - Collapsible tree nodes
  - Indentation for hierarchy (max visual depth handling)
  - Task count badges (pending count)
  - Color indicators
  - Drag handle for reordering
  - Archived ancestor styling (gray/strikethrough)

  {# PROJECT TREE VISUAL SPECIFICATIONS (per UI-DESIGN-SYSTEM.md): #}
  {# - Container: py-2 space-y-1 #}
  {# - Node item: flex items-center px-2 py-1.5 rounded-md cursor-pointer #}
  {#   hover:bg-gray-50 transition-colors duration-150 #}
  {# - Indentation: pl-4 (16px) per nesting level #}
  {# - Collapse/expand icon: w-4 h-4 text-gray-400, chevron-right/chevron-down #}
  {#   Use x-show with x-transition:rotate for smooth rotation #}
  {# - Project color indicator: w-3 h-3 rounded-full mr-2 inline-block #}
  {# - Project name: text-sm text-gray-700, truncate for overflow #}
  {# - Task count: text-xs text-gray-500 ml-auto (parenthetical after name) #}
  {# - Hover state: bg-gray-50 rounded-md #}
  {# - Active/selected state: bg-indigo-50 text-indigo-700 font-medium #}
  {# - Archived projects: text-gray-400 italic, with archive icon (w-4 h-4) #}
  {# - Drag handle: w-4 h-4 text-gray-300 hover:text-gray-500, grip-vertical icon #}
  {#   opacity-0 group-hover:opacity-100 transition-opacity #}
  ```

- [ ] **3.8.2** Implement collapsible tree with JavaScript
  ```javascript
  // assets/js/project-tree.js
  
  Features:
  - Click to expand/collapse
  - Remember collapsed state (localStorage)
  - Smooth animation
  - Keyboard navigation
  - Auto-collapse beyond depth 3 by default
  ```

- [ ] **3.8.3** Implement drag-and-drop reordering
  ```javascript
  // Using native HTML5 drag and drop or library
  
  Features:
  - Drag to reorder within same level
  - Drag to change parent (nest under another project)
  - Visual indicators for drop targets
  - Prevent drop on archived projects
  - API call on drop to persist order
  ```

- [ ] **3.8.4** Create project context menu
  ```javascript
  // Right-click or ... menu
  
  Options:
  - Add sub-project
  - Edit project
  - Archive project
  - Change color
  - Delete project
  ```

- [ ] **3.8.5** Integrate into sidebar
  ```twig
  {# templates/partials/sidebar.html.twig #}
  
  - Fixed views (Today, Upcoming, Overdue)
  - Separator
  - Project tree
  - Separator
  - Archived projects link
  - Tags section
  ```

- [ ] **3.8.6** Create archived projects view
  ```twig
  {# templates/project/archived.html.twig #}
  
  - List of archived projects
  - Unarchive button
  - Permanent delete button (with confirmation)
  - Task count
  ```

### Completion Criteria
- [ ] Tree renders with proper indentation
- [ ] Deep trees handled gracefully (collapse, scroll)
- [ ] Expand/collapse works
- [ ] Drag-and-drop reorders projects
- [ ] Cannot drop on archived projects
- [ ] Context menu functional
- [ ] Archived projects viewable

### Files to Create
```
templates/components/
├── project-tree.html.twig
└── project-tree-node.html.twig

templates/project/
└── archived.html.twig

templates/partials/
└── sidebar.html.twig

assets/js/
├── project-tree.js
└── drag-drop.js

assets/css/
└── project-tree.css
```

---

## Sub-Phase 3.9: Project Hierarchy Tests

### Objective
Comprehensive test coverage for hierarchy operations.

### Tasks

- [ ] **3.9.1** Unit tests for Project entity
  ```php
  // tests/Unit/Entity/ProjectTest.php
  
  Tests:
  - testSetParent()
  - testSetSelfAsParentThrowsException()
  - testCircularReferenceDetection()
  - testMoveToDescendantThrowsException()
  - testGetDepth()
  - testGetAncestors()
  - testGetPath()
  - testIsDescendantOf()
  - testIsAncestorOf()
  - testGetAllDescendants()
  - testPositionNeverNull()
  ```

- [ ] **3.9.2** Repository tests
  ```php
  // tests/Unit/Repository/ProjectRepositoryTest.php
  
  Tests:
  - testGetTreeByUser()
  - testGetTreeExcludesArchivedByDefault()
  - testGetTreeIncludesArchivedWhenRequested()
  - testGetDescendantIds()
  - testGetAncestorIds()
  - testGetTreeWithTaskCounts()
  - testTaskCountsAreDirectOnly()
  - testTreePerformance() // Verify single query
  - testNormalizePositions()
  ```

- [ ] **3.9.3** API tests for hierarchy
  ```php
  // tests/Functional/Api/ProjectHierarchyApiTest.php
  
  Tests:
  - testCreateProjectWithParent()
  - testCreateProjectUnderArchivedParentFails()
  - testMoveProjectToNewParent()
  - testMoveProjectToRoot()
  - testMoveProjectToSelfFails()
  - testMoveProjectToDescendantFails()
  - testMoveProjectToArchivedParentFails()
  - testMoveProjectToOtherUsersProjectFails()
  - testCircularReferenceRejected()
  - testReorderProjects()
  - testPositionsAreGapless()
  - testShowChildrenTasksSetting()
  - testGetProjectTasksWithChildren()
  - testGetProjectTasksWithoutChildren()
  - testIncludeArchivedProjectsParameter()
  
  Error code tests:
  - testCannotBeOwnParentErrorCode()
  - testCircularReferenceErrorCode()
  - testMoveToDescendantErrorCode()
  - testMoveToArchivedErrorCode()
  - testParentNotFoundErrorCode()
  - testParentNotOwnedErrorCode()
  ```

- [ ] **3.9.4** API tests for archiving
  ```php
  // tests/Functional/Api/ProjectArchiveApiTest.php
  
  Tests:
  - testArchiveProject()
  - testUnarchiveProject()
  - testArchiveCascade()
  - testArchiveWithPromoteChildren()
  - testTasksInArchivedProjectStillQueryable()
  - testArchivedProjectsExcludedByDefault()
  - testUndoArchive()
  - testChildrenOfArchivedProjectCanBeEdited()
  - testChildrenOfArchivedProjectCanBeMoved()
  - testBreadcrumbsIncludeArchivedAncestors()
  - testSearchIncludesTasksFromArchivedProjects()
  - testSearchExcludesArchivedProjectsByDefault()
  ```

- [ ] **3.9.5** Cache tests
  ```php
  // tests/Unit/Service/ProjectCacheServiceTest.php
  
  Tests:
  - testCacheKeyIncludesAllVariants()
  - testInvalidateUserTreeCacheClearsAllVariants()
  - testInvalidateTaskCountCacheClearsOnlyCountVariants()
  - testDifferentFlagsUseDifferentCacheKeys()
  ```

### Completion Criteria
- [ ] All hierarchy operations tested
- [ ] All error codes tested
- [ ] Edge cases covered (deep nesting, many children)
- [ ] Cache key variants tested
- [ ] Performance tests pass
- [ ] Archive tests complete

### Files to Create
```
tests/Unit/Entity/ProjectTest.php
tests/Unit/Repository/ProjectRepositoryTest.php
tests/Unit/Service/ProjectCacheServiceTest.php
tests/Functional/Api/ProjectHierarchyApiTest.php
tests/Functional/Api/ProjectArchiveApiTest.php
```

---

## Phase 3 Deliverables Checklist

At the end of Phase 3, the following should be complete:

### Core Hierarchy
- [ ] Projects support unlimited nesting depth
- [ ] Circular references prevented with specific error codes
- [ ] Self-parent prevented with specific error code
- [ ] Deep nesting noted as UI consideration (no coded limit)

### Task Counts
- [ ] task_count = direct tasks only (any status)
- [ ] pending_task_count = direct non-completed tasks only

### Ordering
- [ ] Children ordered by position ASC, id ASC
- [ ] Positions normalized to gapless sequences
- [ ] NULL positions not allowed (normalized to 0)

### Settings
- [ ] show_children_tasks setting functional
- [ ] include_archived_projects interaction documented and working

### Archiving
- [ ] Archive/unarchive functional
- [ ] Children of archived projects can be edited/moved
- [ ] Cannot move/create under archived parent
- [ ] Breadcrumbs include archived ancestors with visual indicator
- [ ] Search includes tasks from archived projects by default

### Caching
- [ ] Cache key includes user_id, include_archived, include_task_counts
- [ ] Proper invalidation on project and task changes

### Error Codes
- [ ] PROJECT_CANNOT_BE_OWN_PARENT (422)
- [ ] PROJECT_CIRCULAR_REFERENCE (422)
- [ ] PROJECT_MOVE_TO_DESCENDANT (422)
- [ ] PROJECT_CANNOT_MOVE_TO_ARCHIVED_PARENT (422)
- [ ] PROJECT_PARENT_NOT_FOUND (422)
- [ ] PROJECT_PARENT_NOT_OWNED_BY_USER (403)

### UI
- [ ] Project tree UI with collapse/expand
- [ ] Drag-and-drop reordering in UI
- [ ] Deep trees handled gracefully

### Testing
- [ ] All hierarchy tests passing
- [ ] All error code tests passing
- [ ] Performance acceptable with deep trees

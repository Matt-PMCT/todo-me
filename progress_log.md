# Progress Log

## Recording Instructions

For each work session, log:
- **Date**: YYYY-MM-DD
- **Phase/Sub-phase**: e.g., "1.2 - Database Schema"
- **Tasks completed**: Brief list of task IDs completed (e.g., 1.2.1, 1.2.2)
- **Files created/modified**: Key files only
- **Tests added**: Count or key test names
- **Blockers/Notes**: Any issues or decisions made

Keep entries brief. Use task IDs from phase docs for reference.

---

## Log Entries

### 2026-01-23

**Phase**: 1 - Core Foundation
**Status**: ✅ COMPLETE

**Completed**:
- [x] 1.1 Project Initialization
  - Docker Compose (PHP 8.2-fpm, PostgreSQL 15, Redis 7, Nginx)
  - Symfony 7 skeleton with all packages
  - Environment configuration (.env, .env.test)
- [x] 1.2 Database Schema
  - Entities: User, Project, Task, Tag (all with UUID primary keys)
  - 7 migrations including full-text search vector + trigger
  - Repositories with pagination support
- [x] 1.3 User Authentication
  - ApiTokenAuthenticator (Bearer + X-API-Key support)
  - TokenGenerator, UserService
  - AuthController (/api/v1/auth/*)
  - Security configuration with stateless API firewall
- [x] 1.4 API Infrastructure
  - RequestIdListener (X-Request-ID tracking)
  - ApiRateLimitSubscriber (100 req/min anonymous, 1000 authenticated)
  - ResponseFormatter (consistent JSON responses)
  - ApiExceptionListener (error code mapping)
  - ApiLogger (structured logging with request ID)
- [x] 1.5 Validation Helpers
  - ValidationHelper service
  - Custom exceptions: InvalidStatusException, InvalidPriorityException, ValidationException, EntityNotFoundException, UnauthorizedException, ForbiddenException
  - OwnershipChecker service
  - UserOwnedInterface
- [x] 1.6 Task CRUD
  - TaskService with full undo support
  - TaskController (8 endpoints)
  - DTOs: CreateTaskRequest, UpdateTaskRequest, TaskResponse, TaskListResponse
  - PaginationHelper
- [x] 1.7 Project CRUD
  - ProjectService with archive/unarchive + undo support
  - ProjectController (8 endpoints)
  - DTOs: CreateProjectRequest, UpdateProjectRequest, ProjectResponse, ProjectListResponse
- [x] 1.8 Redis/Undo Setup
  - RedisService wrapper
  - UndoService with 60s TTL tokens
  - UndoToken value object
  - UndoAction enum
- [x] 1.9 Simple Task List UI
  - Twig templates with Tailwind CSS + Alpine.js
  - Login/Register/Task list views
  - Web SecurityController, TaskListController, HomeController
  - Form types: LoginType, RegisterType, QuickTaskType

**Tests Added**:
- 134 functional API tests (Auth, Task, Project, RateLimit)
- 316 unit tests (Services, Entities, ValueObjects)
- Total: 450 tests

**Key Files Created**:
- `docker/docker-compose.yml`, `docker/php/Dockerfile`, `docker/nginx/default.conf`
- `src/Entity/` (4 entities)
- `src/Controller/Api/` (Auth, Task, Project controllers)
- `src/Controller/Web/` (Security, TaskList, Home controllers)
- `src/Service/` (12 services)
- `src/DTO/` (12 DTOs)
- `migrations/` (7 migrations)
- `templates/` (5 templates)
- `tests/Functional/Api/` (4 test classes)
- `tests/Unit/` (9 test classes)

**Notes**:
- Used parallel sub-agents for waves 2-4 and 6
- All entities implement UserOwnedInterface for ownership checking
- Undo tokens stored in Redis with 60-second TTL
- Rate limiting: 100 req/min (anon), 1000 req/min (authenticated)

---

### 2026-01-24

**Phase**: 7 - Search & Undo System Enhancements
**Status**: ✅ COMPLETE

**Completed**:
- [x] 7.1 TaskRepository Highlight/Prefix Search
  - `searchWithHighlights()` using `ts_headline()` and `ts_rank()`
  - `prefixSearch()` for autocomplete with ILIKE
- [x] 7.2 ProjectRepository & TagRepository Prefix Search
  - Verified existing `searchByNamePrefix()` and `searchByPrefix()` methods
- [x] 7.3 Search API Enhancements
  - `highlight` query parameter (default: true)
  - `titleHighlight`, `descriptionHighlight`, `rank` fields in results
  - `meta.searchTimeMs` timing metric using `hrtime(true)`
- [x] 7.4 FTS-First Filter Integration
  - TaskRepository `createAdvancedFilteredQueryBuilder()` now uses native SQL FTS
  - `/api/v1/tasks?search=` uses full-text search instead of LIKE
- [x] 7.5 Generic Undo Endpoint
  - New `UndoController` at `POST /api/v1/undo`
  - Routes to TaskUndoService or ProjectUndoService based on entityType
- [x] 7.6 Recurring Task Undo
  - TaskService stores `_generatedNextTaskId` when completing recurring tasks
  - TaskUndoService deletes auto-generated next task if still pending
- [x] 7.7 Search UI Component
  - New `search-input.html.twig` with Alpine.js
  - `search.js` with "/" global shortcut, debounced API calls, keyboard navigation
- [x] 7.8 Toast Undo UI
  - `showWithUndo()` method with countdown timer
  - Undo button that calls `POST /api/v1/undo`

**Tests Added**:
- 7 new SearchApiTest tests (highlights, rank, timing, prefix search)
- 13 new UndoApiTest tests (generic undo, recurring undo, validation)
- Total: 33 tests, 243 assertions for Phase 7

**Key Files Created**:
- `src/Controller/Api/UndoController.php`
- `templates/components/search-input.html.twig`
- `assets/js/search.js`
- `tests/Functional/Api/UndoApiTest.php`

**Key Files Modified**:
- `src/Repository/TaskRepository.php` (highlight search, FTS filters)
- `src/Service/SearchService.php` (highlights, timing)
- `src/DTO/SearchResponse.php`, `SearchRequest.php` (highlight fields)
- `src/Service/TaskService.php` (store nextTaskId for recurring)
- `src/Service/TaskUndoService.php` (recurring undo logic)
- `assets/js/toast.js` (undo support, countdown)
- `templates/base.html.twig` (search component, undo button)

**Notes**:
- Used parallel sub-agents for all 4 implementation waves
- FTS integration uses native SQL to get matching IDs, then IN clause with QueryBuilder
- Recurring undo only deletes next task if still pending (not completed/modified)

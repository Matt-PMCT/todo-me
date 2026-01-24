# Phase 6: API Development - Updated Review Plan

## Overview
**Original Goal**: Finalize the REST API with API Platform integration, comprehensive documentation, batch operations, rate limiting, and AI-agent friendly features.

**Revised Goal**: Complete remaining API features (batch operations, search, project tree), add OpenAPI documentation, and create AI-agent documentation. The core API infrastructure (rate limiting, auth, error handling, response formatting) is already complete.

## Architecture Evolution Notes

This phase plan has been updated to reflect the actual implementation approach taken:

### Decision: Custom Controllers Instead of API Platform
The original plan called for API Platform 3.x integration. Instead, custom controllers were implemented with:
- `ResponseFormatter` service for consistent JSON responses
- `ApiExceptionListener` with `ExceptionMapperRegistry` for error handling
- Manual endpoint definitions with full control over behavior

**Rationale**: Custom controllers provide more flexibility for specialized endpoints like natural language parsing and allow precise control over response format without API Platform's conventions.

### Current Response Format
```json
{
  "success": true,
  "data": {...},
  "error": null,
  "meta": {
    "requestId": "uuid-v4",
    "timestamp": "2026-01-24T10:30:00.123+00:00"
  }
}
```

**Note**: Uses `requestId` (camelCase) per `docs/DEVIATIONS.md` for JSON consistency.

---

## Implementation Status Summary

| Sub-Phase | Status | Notes |
|-----------|--------|-------|
| 6.1: API Platform | **REPLACED** | Custom controllers used instead |
| 6.2: Custom Endpoints | **90% Complete** | Missing project tree, global search |
| 6.3: Batch Operations | **10% Complete** | Only project reorder implemented |
| 6.4: Rate Limiting | **100% Complete** | Fully implemented |
| 6.5: Authentication | **100% Complete** | Fully implemented |
| 6.6: Response Headers | **100% Complete** | Fully implemented |
| 6.7: OpenAPI Docs | **Not Started** | Need NelmioApiDocBundle |
| 6.8: AI Agent Docs | **Not Started** | Documentation files needed |
| 6.9: API Testing | **80% Complete** | Need batch + search tests |

---

## Sub-Phase 6.1: API Infrastructure (COMPLETED)

**Status: REPLACED/COMPLETED**

Instead of API Platform, a custom infrastructure was built:

### Completed Components

- [x] **6.1.1** ResponseFormatter service (`src/Service/ResponseFormatter.php`)
  - `success()`, `error()`, `paginated()`, `created()`, `noContent()` methods
  - Consistent response structure across all endpoints
  - Includes requestId and timestamp in meta

- [x] **6.1.2** Exception handling with mapper pattern
  - `ApiExceptionListener` catches all API exceptions
  - `ExceptionMapperRegistry` with priority-based mapper selection
  - Domain mappers: ValidationException, InvalidStatus, InvalidPriority, EntityNotFound, Forbidden, Unauthorized
  - Symfony mappers: HttpException, AccessDenied, Authentication
  - Fallback: ServerErrorMapper

- [x] **6.1.3** Request ID tracking (`src/EventListener/RequestIdListener.php`)
  - Validates incoming `X-Request-ID` headers
  - Generates UUIDv4 if not provided
  - Adds to response headers

- [x] **6.1.4** API logging (`src/Service/ApiLogger.php`)
  - Structured logging with request ID context
  - Request/response logging with duration
  - Safe email hashing for privacy

### Files Implemented
```
src/Service/ResponseFormatter.php
src/Service/ApiLogger.php
src/EventListener/RequestIdListener.php
src/EventListener/ApiExceptionListener.php
src/EventListener/ExceptionMapper/ExceptionMapperRegistry.php
src/EventListener/ExceptionMapper/ExceptionMapperInterface.php
src/EventListener/ExceptionMapper/Domain/*.php
src/EventListener/ExceptionMapper/Symfony/*.php
src/EventListener/ExceptionMapper/Fallback/ServerErrorMapper.php
```

---

## Sub-Phase 6.2: Custom API Endpoints (MOSTLY COMPLETE)

**Status: 90% Complete**

### Completed Endpoints

- [x] **6.2.1** Specialized task view endpoints
  ```
  GET  /api/v1/tasks/today      - Tasks due today + overdue
  GET  /api/v1/tasks/upcoming   - Tasks in next N days (default: 7)
  GET  /api/v1/tasks/overdue    - Past-due tasks with severity
  GET  /api/v1/tasks/no-date    - Tasks without due date
  GET  /api/v1/tasks/completed  - Recently completed tasks
  ```

- [x] **6.2.2** Status update endpoint
  ```
  PATCH /api/v1/tasks/{id}/status
  - Returns undo token
  - Proper error handling (INVALID_STATUS, NOT_FOUND, PERMISSION_DENIED)
  ```

- [x] **6.2.3** Reschedule endpoint
  ```
  PATCH /api/v1/tasks/{id}/reschedule
  - Supports ISO dates and natural language
  - Returns undo token
  ```

- [x] **6.2.4** Autocomplete endpoints
  ```
  GET /api/v1/autocomplete/projects?q={prefix}
  GET /api/v1/autocomplete/tags?q={prefix}
  ```

- [x] **6.2.5** Natural language parsing endpoint
  ```
  POST /api/v1/parse
  - Preview parsing without creating task
  ```

### Remaining Tasks

- [ ] **6.2.6** Create project tree endpoint
  ```php
  GET /api/v1/projects/tree

  Query params:
  - include_archived: bool (default: false)
  - include_task_counts: bool (default: true)

  Response:
  {
    "success": true,
    "data": [
      {
        "id": "uuid",
        "name": "Work",
        "color": "#3B82F6",
        "children": [...],
        "taskCount": 15,
        "pendingTaskCount": 10
      }
    ]
  }
  ```

  **Implementation Notes**:
  - Use existing `ProjectTreeTransformer`
  - Add task count aggregation
  - Cache tree structure in Redis

- [ ] **6.2.7** Create global search endpoint
  ```php
  GET /api/v1/search?q={query}

  Query params:
  - q: string (required, min 2 chars)
  - type: tasks|projects|tags|all (default: all)
  - page: int (default: 1)
  - limit: int (default: 20, max: 100)

  Response:
  {
    "success": true,
    "data": {
      "tasks": [...],
      "projects": [...],
      "tags": [...]
    },
    "meta": {
      "query": "meeting",
      "counts": {
        "tasks": 5,
        "projects": 2,
        "tags": 1
      }
    }
  }
  ```

  **Implementation Notes**:
  - Use PostgreSQL full-text search on tasks (search_vector column)
  - Simple ILIKE search on projects and tags
  - Create `SearchController` and `SearchService`

### Files to Create/Update
```
src/Controller/Api/ProjectController.php  (add tree endpoint)
src/Controller/Api/SearchController.php   (new)
src/Service/SearchService.php              (new)
```

---

## Sub-Phase 6.3: Batch Operations (MOSTLY NOT STARTED)

**Status: 10% Complete**

### Completed
- [x] Project batch reorder (`PATCH /api/v1/projects/reorder`)
- [x] `BatchSizeLimitExceededException` for size validation

### Remaining Tasks

- [ ] **6.3.1** Create task batch endpoint
  ```php
  POST /api/v1/tasks/batch

  SUPPORTED OPERATIONS:
  - create: Create a new task
  - update: Update an existing task
  - delete: Delete a task
  - complete: Mark task as completed
  - reschedule: Change task due date

  Request:
  {
    "operations": [
      {"action": "create", "data": {"title": "New task", "due_date": "tomorrow"}},
      {"action": "update", "id": "uuid", "data": {"priority": 3}},
      {"action": "delete", "id": "uuid"},
      {"action": "complete", "id": "uuid"},
      {"action": "reschedule", "id": "uuid", "data": {"due_date": "next Monday"}}
    ]
  }
  ```

- [ ] **6.3.2** Implement BatchOperationService
  ```php
  // src/Service/BatchOperationService.php

  class BatchOperationService
  {
      public function execute(User $user, array $operations, bool $atomic = false): BatchResult
      {
          // Sequential execution
          // Partial success by default
          // Atomic mode with rollback on failure
      }
  }
  ```

- [ ] **6.3.3** Define batch response format
  ```php
  Response (partial success - HTTP 200):
  {
    "success": true,
    "data": {
      "results": [
        {"index": 0, "action": "create", "success": true, "data": {...}},
        {"index": 1, "action": "delete", "success": false, "error": {"code": "RESOURCE_NOT_FOUND", "message": "Task not found"}}
      ]
    },
    "meta": {
      "total": 2,
      "successful": 1,
      "failed": 1,
      "undoToken": "batch_undo_xyz",
      "undoExpiresIn": 60
    }
  }

  Response (atomic failure - HTTP 422):
  {
    "success": false,
    "error": {
      "code": "BATCH_ATOMIC_FAILED",
      "message": "Batch operation failed atomically",
      "details": {
        "failedIndex": 1,
        "failedAction": "delete",
        "reason": "Task not found"
      }
    }
  }
  ```

- [ ] **6.3.4** Implement batch undo
  - Store all operations in single undo token in Redis
  - Undo reverses ALL successful operations atomically

- [ ] **6.3.5** Add batch validation
  - Maximum 100 operations per batch
  - Validate required fields per action type
  - Return detailed validation errors

### Files to Create
```
src/Controller/Api/BatchController.php
src/Service/BatchOperationService.php
src/DTO/Request/BatchOperationsRequest.php
src/DTO/Response/BatchResult.php
src/DTO/Response/BatchOperationResult.php
src/Exception/BatchFailedException.php
tests/Functional/Api/BatchApiTest.php
```

---

## Sub-Phase 6.4: Rate Limiting (COMPLETED)

**Status: 100% Complete**

### Completed Components

- [x] **6.4.1** Rate limiter configuration (`config/packages/rate_limiter.yaml`)
  - `login`: 5 attempts/min (brute force protection)
  - `api`: 1000 req/hour authenticated, 100/hour anonymous
  - `registration`: 10 attempts/hour

- [x] **6.4.2** Rate limit subscriber (`src/EventSubscriber/ApiRateLimitSubscriber.php`)
  - Token-based limiting for authenticated requests
  - IP-based limiting for anonymous requests
  - Proper header generation

- [x] **6.4.3** Rate limit headers on all responses
  ```
  X-RateLimit-Limit: 1000
  X-RateLimit-Remaining: 995
  X-RateLimit-Reset: 1706009400
  Retry-After: 3600 (on 429)
  ```

- [x] **6.4.4** Rate limit tests (`tests/Functional/Api/RateLimitApiTest.php`)

### Files Implemented
```
config/packages/rate_limiter.yaml
config/packages/framework.yaml (rate limiter factories)
src/EventSubscriber/ApiRateLimitSubscriber.php
tests/Functional/Api/RateLimitApiTest.php
```

---

## Sub-Phase 6.5: Authentication (COMPLETED)

**Status: 100% Complete**

### Completed Components

- [x] **6.5.1** API Token Authenticator (`src/Security/ApiTokenAuthenticator.php`)
  - Supports `Authorization: Bearer {token}`
  - Supports `X-API-Key: {token}` (alternative)
  - Token expiry validation
  - Generic error messages (prevents enumeration)

- [x] **6.5.2** Auth Controller (`src/Controller/Api/AuthController.php`)
  ```
  POST /api/v1/auth/register  - User registration
  POST /api/v1/auth/token     - Login / token generation
  POST /api/v1/auth/revoke    - Token revocation
  POST /api/v1/auth/refresh   - Token refresh (7-day window)
  GET  /api/v1/auth/me        - Get current user
  ```

- [x] **6.5.3** Security configuration (`config/packages/security.yaml`)
  - API firewall marked as stateless
  - Public routes configured
  - Custom authenticator registered

- [x] **6.5.4** Auth tests (`tests/Functional/Api/AuthApiTest.php`)

### Files Implemented
```
src/Security/ApiTokenAuthenticator.php
src/Controller/Api/AuthController.php
config/packages/security.yaml
tests/Functional/Api/AuthApiTest.php
tests/Unit/Security/ApiTokenAuthenticatorTest.php
```

---

## Sub-Phase 6.6: API Response Headers (COMPLETED)

**Status: 100% Complete**

### Completed Components

- [x] **6.6.1** Request ID on all responses
  - `X-Request-ID` header
  - UUID validation for incoming headers
  - Auto-generation if not provided

- [x] **6.6.2** Timestamp in meta
  - RFC3339 extended format
  - Included in all responses via ResponseFormatter

- [x] **6.6.3** CORS configuration (`config/packages/nelmio_cors.yaml`)
  ```yaml
  allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
  allow_methods: [GET, OPTIONS, POST, PUT, PATCH, DELETE]
  allow_headers: [Content-Type, Authorization, X-API-Key]
  expose_headers: [Link, X-RateLimit-Remaining, X-Request-ID]
  max_age: 3600
  ```

- [x] **6.6.4** CORS tests (`tests/Functional/Security/CorsConfigurationTest.php`)

### Files Implemented
```
src/EventListener/RequestIdListener.php
src/Service/ResponseFormatter.php
config/packages/nelmio_cors.yaml
docs/CORS-CONFIGURATION.md
tests/Functional/Security/CorsConfigurationTest.php
```

---

## Sub-Phase 6.7: OpenAPI Documentation (NOT STARTED)

**Status: Not Started**

### Recommended Approach

Since API Platform is not used, implement OpenAPI documentation using **NelmioApiDocBundle** with PHP 8 Attributes.

### Tasks

- [ ] **6.7.1** Install and configure NelmioApiDocBundle
  ```bash
  composer require nelmio/api-doc-bundle
  ```

  ```yaml
  # config/packages/nelmio_api_doc.yaml
  nelmio_api_doc:
    documentation:
      info:
        title: 'Todo-Me API'
        description: 'AI-accessible todo list application API'
        version: '1.0.0'
      servers:
        - url: 'http://localhost:8000'
          description: 'Development'
      components:
        securitySchemes:
          bearerAuth:
            type: http
            scheme: bearer
            bearerFormat: 'API Token'
          apiKeyAuth:
            type: apiKey
            in: header
            name: X-API-Key
      security:
        - bearerAuth: []
        - apiKeyAuth: []
    areas:
      default:
        path_patterns:
          - ^/api/v1
  ```

- [ ] **6.7.2** Add OpenAPI attributes to controllers
  ```php
  use OpenApi\Attributes as OA;

  #[OA\Tag(name: 'Tasks', description: 'Task management operations')]
  class TaskController extends AbstractController
  {
      #[Route('/api/v1/tasks', methods: ['GET'])]
      #[OA\Get(
          summary: 'List tasks',
          description: 'Returns paginated list of tasks with optional filters'
      )]
      #[OA\Parameter(
          name: 'status',
          in: 'query',
          description: 'Filter by status',
          schema: new OA\Schema(type: 'string', enum: ['pending', 'in_progress', 'completed'])
      )]
      #[OA\Response(
          response: 200,
          description: 'List of tasks',
          content: new OA\JsonContent(ref: '#/components/schemas/TaskListResponse')
      )]
      public function list(): JsonResponse { ... }
  }
  ```

- [ ] **6.7.3** Define shared schemas
  ```php
  // src/OpenApi/Schemas.php

  #[OA\Schema(schema: 'Task')]
  class TaskSchema
  {
      #[OA\Property(type: 'string', format: 'uuid')]
      public string $id;

      #[OA\Property(type: 'string', example: 'Review document')]
      public string $title;

      #[OA\Property(type: 'string', enum: ['pending', 'in_progress', 'completed'])]
      public string $status;

      #[OA\Property(type: 'integer', minimum: 0, maximum: 4)]
      public int $priority;
      // ...
  }

  #[OA\Schema(schema: 'ApiError')]
  class ApiErrorSchema
  {
      #[OA\Property(type: 'boolean', example: false)]
      public bool $success;

      #[OA\Property(ref: '#/components/schemas/ErrorDetails')]
      public object $error;

      #[OA\Property(ref: '#/components/schemas/Meta')]
      public object $meta;
  }
  ```

- [ ] **6.7.4** Configure routes for Swagger UI
  ```yaml
  # config/routes/nelmio_api_doc.yaml
  app.swagger_ui:
    path: /api/v1/docs
    methods: GET
    defaults: { _controller: nelmio_api_doc.controller.swagger_ui }

  app.swagger:
    path: /api/v1/docs.json
    methods: GET
    defaults: { _controller: nelmio_api_doc.controller.swagger }
  ```

- [ ] **6.7.5** Verify documentation accessible
  ```
  Swagger UI: /api/v1/docs
  OpenAPI JSON: /api/v1/docs.json
  ```

### Files to Create
```
config/packages/nelmio_api_doc.yaml
config/routes/nelmio_api_doc.yaml
src/OpenApi/Schemas.php
src/Controller/Api/*Controller.php (add OA attributes)
```

---

## Sub-Phase 6.8: AI Agent Documentation (NOT STARTED)

**Status: Not Started**

### Tasks

- [ ] **6.8.1** Create API quickstart guide
  ```markdown
  # docs/api/quickstart.md

  # API Quickstart for AI Agents

  ## Authentication
  POST /api/v1/auth/token
  {"email": "...", "password": "..."}
  Returns: {"data": {"token": "...", "expiresAt": "..."}}

  Use token in subsequent requests:
  Authorization: Bearer {token}

  ## Quick Reference
  - Create task: POST /api/v1/tasks
  - List tasks: GET /api/v1/tasks
  - Today's tasks: GET /api/v1/tasks/today
  - Complete task: PATCH /api/v1/tasks/{id}/status
  ```

- [ ] **6.8.2** Create structured vs natural language guide
  ```markdown
  # docs/api/structured-vs-natural.md

  ## When to Use Structured Input (Recommended for AI)
  - Building automated workflows
  - Maximum reliability required
  - Known project/tag IDs

  ## When to Use Natural Language
  - Passing through user input directly
  - Quick prototyping
  ```

- [ ] **6.8.3** Create use cases documentation
  ```markdown
  # docs/api/use-cases.md

  ## Use Case: Get Today's High Priority Tasks
  GET /api/v1/tasks/today?priority_min=3&sort=priority&direction=desc

  ## Use Case: Reschedule All Overdue Tasks
  1. GET /api/v1/tasks/overdue
  2. POST /api/v1/tasks/batch (with reschedule operations)
  ```

- [ ] **6.8.4** Create error code reference
  ```markdown
  # docs/api/errors.md

  | Code | HTTP | Meaning | Action |
  |------|------|---------|--------|
  | VALIDATION_ERROR | 422 | Field validation failed | Check details |
  | RESOURCE_NOT_FOUND | 404 | Entity doesn't exist | Verify ID |
  | INVALID_STATUS | 422 | Bad status value | Use: pending, in_progress, completed |
  | INVALID_PRIORITY | 422 | Priority out of range | Use: 0-4 |
  | RATE_LIMIT_EXCEEDED | 429 | Too many requests | Wait for Retry-After |
  ```

- [ ] **6.8.5** Create complete API reference
  ```markdown
  # docs/api/reference.md

  ## Tasks
  | Method | Endpoint | Description |
  |--------|----------|-------------|
  | GET | /api/v1/tasks | List tasks with filters |
  | POST | /api/v1/tasks | Create task |
  ...
  ```

### Files to Create
```
docs/api/
├── quickstart.md
├── structured-vs-natural.md
├── use-cases.md
├── errors.md
├── reference.md
└── filters.md
```

---

## Sub-Phase 6.9: API Testing (MOSTLY COMPLETE)

**Status: 80% Complete**

### Completed Tests
- [x] Task CRUD tests (`TaskApiTest.php`)
- [x] Task view tests (`TaskViewApiTest.php`)
- [x] Task filter tests (`TaskFilterApiTest.php`)
- [x] Project CRUD tests (`ProjectApiTest.php`)
- [x] Project hierarchy tests (`ProjectHierarchyApiTest.php`)
- [x] Project archive tests (`ProjectArchiveApiTest.php`)
- [x] Auth tests (`AuthApiTest.php`)
- [x] Rate limit tests (`RateLimitApiTest.php`)
- [x] Saved filter tests (`SavedFilterApiTest.php`)
- [x] Natural language parsing tests (`NaturalLanguageTaskTest.php`, `ParseApiTest.php`)
- [x] Autocomplete tests (`AutocompleteApiTest.php`)
- [x] Authorization edge cases (`AuthorizationEdgeCasesTest.php`)
- [x] Validation edge cases (`ValidationEdgeCasesTest.php`)

### Remaining Tests

- [ ] **6.9.1** Batch operation tests
  ```php
  // tests/Functional/Api/BatchApiTest.php

  public function testBatchCreate(): void
  public function testBatchUpdate(): void
  public function testBatchDelete(): void
  public function testBatchComplete(): void
  public function testBatchReschedule(): void
  public function testBatchMixedOperations(): void
  public function testBatchPartialFailure(): void
  public function testBatchAtomicSuccess(): void
  public function testBatchAtomicRollback(): void
  public function testBatchUndo(): void
  public function testBatchLimitExceeded(): void
  ```

- [ ] **6.9.2** Search endpoint tests
  ```php
  // tests/Functional/Api/SearchApiTest.php

  public function testSearchTasks(): void
  public function testSearchProjects(): void
  public function testSearchAll(): void
  public function testSearchMinQueryLength(): void
  public function testSearchPagination(): void
  public function testSearchHighlighting(): void
  ```

- [ ] **6.9.3** Project tree tests
  ```php
  // Add to ProjectApiTest.php or new file

  public function testGetProjectTree(): void
  public function testProjectTreeWithTaskCounts(): void
  public function testProjectTreeExcludesArchived(): void
  public function testProjectTreeIncludesArchived(): void
  ```

### Files to Create
```
tests/Functional/Api/BatchApiTest.php
tests/Functional/Api/SearchApiTest.php
```

---

## Phase 6 Updated Deliverables Checklist

### API Infrastructure (Complete)
- [x] Response format consistent across all endpoints
- [x] Error handling with exception mappers
- [x] Request ID tracking
- [x] API logging

### Endpoints
- [x] All CRUD endpoints (tasks, projects, tags, saved-filters)
- [x] Specialized view endpoints (today, upcoming, overdue, no-date, completed)
- [x] Status update and reschedule endpoints
- [x] Autocomplete endpoints
- [x] Parse preview endpoint
- [ ] Project tree endpoint
- [ ] Global search endpoint
- [ ] Task batch operations endpoint

### Batch Operations
- [x] Project reorder batch
- [ ] Task batch (create, update, delete, complete, reschedule)
- [ ] Atomic mode with rollback
- [ ] Batch undo support

### Rate Limiting (Complete)
- [x] Rate limiting enforced per token (authenticated)
- [x] Rate limiting enforced per IP (anonymous)
- [x] Login rate limiting for brute force protection
- [x] Headers on all responses

### Authentication (Complete)
- [x] Token generation and validation
- [x] Token refresh mechanism
- [x] Token revocation
- [x] Dual header support (Bearer + X-API-Key)

### Headers (Complete)
- [x] Request ID on all responses
- [x] Rate limit headers
- [x] CORS properly configured

### Documentation
- [ ] OpenAPI 3.0 with NelmioApiDocBundle
- [ ] Swagger UI at /api/v1/docs
- [ ] All endpoints documented with examples
- [ ] Error schemas defined

### AI Agent Documentation
- [ ] Quickstart guide
- [ ] Structured vs natural language guide
- [ ] Use cases with examples
- [ ] Error code reference
- [ ] Complete API reference

### Testing
- [x] Core API tests (>80% coverage)
- [ ] Batch operation tests
- [ ] Search endpoint tests
- [ ] Project tree tests

---

## Priority Order for Remaining Work

1. **High Priority** - Core functionality gaps:
   - Project tree endpoint
   - Global search endpoint
   - Task batch operations

2. **Medium Priority** - Documentation for AI agents:
   - OpenAPI documentation with Swagger UI
   - AI quickstart guide
   - Error code reference

3. **Lower Priority** - Nice to have:
   - Full AI documentation suite
   - Additional edge case tests

---

## Estimated Remaining Effort

| Item | Complexity | Estimated Tasks |
|------|------------|-----------------|
| Project tree endpoint | Low | 1-2 hours |
| Global search endpoint | Medium | 3-4 hours |
| Task batch operations | High | 6-8 hours |
| OpenAPI documentation | Medium | 4-6 hours |
| AI documentation | Low | 2-3 hours |
| Additional tests | Medium | 3-4 hours |

**Total Estimated**: 19-27 hours of development work

---

## Notes on Deviations from Original Plan

1. **API Platform replaced with custom controllers**: Provides more flexibility for specialized endpoints and natural language parsing.

2. **Error codes use SCREAMING_SNAKE_CASE**: e.g., `VALIDATION_ERROR`, `RESOURCE_NOT_FOUND` (consistent with existing implementation).

3. **Response uses `requestId` not `request_id`**: Per `docs/DEVIATIONS.md` for JSON field consistency.

4. **UUIDs used instead of integers for IDs**: All entity IDs are UUIDs, not integers as shown in original plan examples.

5. **Undo tokens stored in Redis**: 60-second TTL, includes undoExpiresIn in meta.

6. **Pagination uses `page` and `limit`**: Not `page` and `per_page` as originally planned.

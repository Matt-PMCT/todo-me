# Phase 6: API Development

## Overview
**Duration**: Week 6
**Goal**: Finalize the REST API with comprehensive documentation, batch operations, rate limiting, and AI-agent friendly features.

## Prerequisites
- Phases 1-5 completed
- All core functionality working
- Basic API endpoints operational

---

## Implementation Status Summary

| Sub-Phase | Status | Notes |
|-----------|--------|-------|
| 6.1: API Infrastructure | **COMPLETE** | Custom controllers (not API Platform) |
| 6.2: Custom Endpoints | **90% Complete** | Missing project tree, global search |
| 6.3: Batch Operations | **10% Complete** | Only project reorder implemented |
| 6.4: Rate Limiting | **COMPLETE** | Fully implemented |
| 6.5: Authentication | **COMPLETE** | Fully implemented |
| 6.6: Response Headers | **COMPLETE** | Fully implemented |
| 6.7: OpenAPI Docs | **Not Started** | Need NelmioApiDocBundle |
| 6.8: AI Agent Docs | **Not Started** | Documentation files needed |
| 6.9: API Testing | **80% Complete** | Need batch + search tests |

---

## Architecture Notes

### Decision: Custom Controllers Instead of API Platform

The original plan called for API Platform 3.x integration. Instead, custom controllers were implemented with:
- `ResponseFormatter` service for consistent JSON responses
- `ApiExceptionListener` with `ExceptionMapperRegistry` for error handling
- Manual endpoint definitions with full control over behavior

**Rationale**: Custom controllers provide more flexibility for specialized endpoints like natural language parsing and allow precise control over response format.

### Actual Response Format
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

**Note**: Uses `requestId` (camelCase) per `docs/DEVIATIONS.md` for JSON field consistency.

---

## Core Design Decisions

### Standard Error Format
```php
ALL API errors use this consistent format:

{
  "success": false,
  "data": null,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": {
      // Optional field-specific errors or additional context
      "field_name": ["Specific error message"],
      "retryAfter": 3600
    }
  },
  "meta": {
    "timestamp": "2026-01-23T10:30:00Z",
    "requestId": "uuid-v4"
  }
}

STANDARD ERROR CODES (as implemented):
- VALIDATION_ERROR: 422 - Validation failed (details contains field errors)
- AUTHENTICATION_REQUIRED: 401 - Authentication required
- INVALID_TOKEN: 401 - Invalid or expired token
- INVALID_CREDENTIALS: 401 - Wrong username/password
- RESOURCE_NOT_FOUND: 404 - Resource not found
- DUPLICATE_RESOURCE: 409 - Resource already exists
- RATE_LIMIT_EXCEEDED: 429 - Rate limit exceeded
- PERMISSION_DENIED: 403 - Permission denied
- INVALID_STATUS: 422 - Invalid status value
- INVALID_PRIORITY: 422 - Invalid priority value (not 0-4)
- INVALID_RECURRENCE: 422 - Invalid recurrence pattern
- BATCH_SIZE_LIMIT_EXCEEDED: 400 - Too many batch operations
```

### Standard Response Headers
```
GUARANTEED HEADERS ON ALL API RESPONSES:

Request tracking:
- X-Request-ID: uuid-v4 (echoed from request or generated)

Rate limiting:
- X-RateLimit-Limit: 1000
- X-RateLimit-Remaining: 995
- X-RateLimit-Reset: 1706009400 (Unix timestamp)

Content:
- Content-Type: application/json

CORS:
- Access-Control-Allow-Origin: (configured origin)
- Access-Control-Expose-Headers: X-RateLimit-*, X-Request-ID, Link
```

---

## Sub-Phase 6.1: API Infrastructure (COMPLETE)

### Status: COMPLETE (Architecture Changed)

Instead of API Platform, a custom infrastructure was built providing equivalent functionality.

### Completed Components

- [x] **6.1.1** ResponseFormatter service (`src/Service/ResponseFormatter.php`)
  - `success()`, `error()`, `paginated()`, `created()`, `noContent()` methods
  - Consistent response structure across all endpoints
  - Includes requestId and timestamp in meta

- [x] **6.1.2** Exception handling with mapper pattern
  - `ApiExceptionListener` catches all API exceptions
  - `ExceptionMapperRegistry` with priority-based mapper selection
  - Domain mappers: ValidationException, InvalidStatus, InvalidPriority, InvalidRecurrence, EntityNotFound, Forbidden, Unauthorized, InvalidUndoToken
  - Symfony mappers: HttpException, AccessDenied, Authentication, ValidationFailed
  - Fallback: ServerErrorMapper

- [x] **6.1.3** Request ID tracking (`src/EventListener/RequestIdListener.php`)
  - Validates incoming `X-Request-ID` headers (UUID format)
  - Generates UUIDv4 if not provided
  - Adds to response headers

- [x] **6.1.4** API logging (`src/Service/ApiLogger.php`)
  - Structured logging with request ID context
  - Request/response logging with duration
  - Safe email hashing for privacy

- [x] **6.1.5** API versioning prefix
  - All endpoints under `/api/v1/` namespace
  - Configured in route definitions

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

## Sub-Phase 6.2: Custom API Endpoints (90% COMPLETE)

### Objective
Implement specialized endpoints that go beyond basic CRUD with full documentation of query parameters and error conditions.

### Completed Tasks

- [x] **6.2.1** Specialized task view endpoints
  ```
  GET  /api/v1/tasks/today      - Tasks due today + overdue
  GET  /api/v1/tasks/upcoming   - Tasks in next N days (default: 7)
  GET  /api/v1/tasks/overdue    - Past-due tasks with severity levels
  GET  /api/v1/tasks/no-date    - Tasks without due date
  GET  /api/v1/tasks/completed  - Recently completed tasks
  ```

- [x] **6.2.2** Status update endpoint with error handling
  ```
  PATCH /api/v1/tasks/{id}/status
  - Returns undo token in meta
  - For recurring tasks, returns nextTask in response
  - Proper error handling (INVALID_STATUS, RESOURCE_NOT_FOUND, PERMISSION_DENIED)
  ```

- [x] **6.2.3** Reschedule endpoint with natural language support
  ```
  PATCH /api/v1/tasks/{id}/reschedule
  - Supports ISO dates and natural language
  - Returns undo token
  ```

- [x] **6.2.4** Complete-forever endpoint for recurring tasks
  ```
  POST /api/v1/tasks/{id}/complete-forever
  - Marks recurring task complete without creating next occurrence
  - Validates task is actually recurring
  ```

- [x] **6.2.5** Autocomplete endpoints
  ```
  GET /api/v1/autocomplete/projects?q={prefix}
  GET /api/v1/autocomplete/tags?q={prefix}
  ```

- [x] **6.2.6** Natural language parsing preview
  ```
  POST /api/v1/parse
  - Preview parsing without creating task
  ```

### Remaining Tasks

- [ ] **6.2.7** Create project tree endpoint
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
  - Use existing `ProjectTreeTransformer` in `src/Transformer/`
  - Add task count aggregation to tree nodes
  - Consider caching tree structure in Redis

- [ ] **6.2.8** Create global search endpoint
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
  - Use PostgreSQL full-text search on tasks (search_vector column exists)
  - Simple ILIKE search on projects and tags
  - Create `SearchController` and `SearchService`

### Files to Create/Update
```
src/Controller/Api/ProjectController.php  (add tree endpoint)
src/Controller/Api/SearchController.php   (new)
src/Service/SearchService.php              (new)
```

---

## Sub-Phase 6.3: Batch Operations (10% COMPLETE)

### Objective
Implement batch operations for efficient bulk updates with clear execution semantics.

### Completed Tasks

- [x] **6.3.0** Project batch reorder
  - `PATCH /api/v1/projects/reorder` exists
  - `BatchSizeLimitExceededException` for size validation (max 1000)

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
      {"action": "create", "data": {"title": "New task", "dueDate": "tomorrow"}},
      {"action": "update", "id": "uuid", "data": {"priority": 3}},
      {"action": "delete", "id": "uuid"},
      {"action": "complete", "id": "uuid"},
      {"action": "reschedule", "id": "uuid", "data": {"dueDate": "next Monday"}}
    ]
  }
  ```

- [ ] **6.3.2** Define execution semantics
  ```
  EXECUTION ORDER AND TRANSACTION BEHAVIOR:

  1. Operations execute SEQUENTIALLY in array order
     - This ensures predictable behavior
     - Earlier operations can create resources used by later ones

  2. PARTIAL SUCCESS is allowed (default)
     - Each operation succeeds or fails independently
     - Failed operations do not roll back successful ones

  3. ATOMIC OPTION available
     - ?atomic=true → All-or-nothing (rollback on any failure)
     - Default: false (partial success allowed)
  ```

- [ ] **6.3.3** Implement BatchOperationService
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

- [ ] **6.3.4** Define batch response format
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
  ```

- [ ] **6.3.5** Implement batch undo
  - Store all operations in single undo token in Redis
  - Undo reverses ALL successful operations atomically

- [ ] **6.3.6** Add batch validation
  - Maximum 100 operations per batch (task batch)
  - Validate required fields per action type

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

## Sub-Phase 6.4: Rate Limiting (COMPLETE)

### Status: COMPLETE

### Completed Tasks

- [x] **6.4.1** Rate limiter configuration (`config/packages/rate_limiter.yaml`)
  ```yaml
  framework:
    rate_limiter:
      login:
        policy: 'sliding_window'
        limit: 5
        interval: '1 minute'
      api:
        policy: 'sliding_window'
        limit: 1000
        interval: '1 hour'
      registration:
        policy: 'sliding_window'
        limit: 10
        interval: '1 hour'
  ```

- [x] **6.4.2** Rate limit subscriber (`src/EventSubscriber/ApiRateLimitSubscriber.php`)
  - Token-based limiting for authenticated requests (1000/hour)
  - IP-based limiting for anonymous requests (100/hour)
  - Login rate limiting (5 attempts/minute)

- [x] **6.4.3** Rate limit headers on all responses
  ```
  X-RateLimit-Limit: 1000
  X-RateLimit-Remaining: 995
  X-RateLimit-Reset: 1706009400
  Retry-After: 3600 (on 429 responses)
  ```

### Files Implemented
```
config/packages/rate_limiter.yaml
config/packages/framework.yaml (rate limiter factories)
src/EventSubscriber/ApiRateLimitSubscriber.php
```

---

## Sub-Phase 6.5: Authentication (COMPLETE)

### Status: COMPLETE

### Completed Tasks

- [x] **6.5.1** API Token Authenticator (`src/Security/ApiTokenAuthenticator.php`)
  - Supports `Authorization: Bearer {token}`
  - Supports `X-API-Key: {token}` (alternative header)
  - Token expiry validation
  - Generic error messages (prevents token enumeration)

- [x] **6.5.2** Auth Controller endpoints
  ```
  POST /api/v1/auth/register  - User registration (rate limited)
  POST /api/v1/auth/token     - Login / token generation (rate limited)
  POST /api/v1/auth/revoke    - Token revocation
  POST /api/v1/auth/refresh   - Token refresh (7-day window)
  GET  /api/v1/auth/me        - Get current user info
  ```

- [x] **6.5.3** Security configuration (`config/packages/security.yaml`)
  - API firewall marked as stateless
  - Public routes: register, token, refresh
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

## Sub-Phase 6.6: API Response Headers (COMPLETE)

### Status: COMPLETE

### Completed Tasks

- [x] **6.6.1** Request ID on all responses
  - `X-Request-ID` header generated/echoed
  - UUID format validation for incoming headers
  - `RequestIdListener` with high priority

- [x] **6.6.2** Timestamp in meta
  - RFC3339 extended format
  - Included in all responses via ResponseFormatter

- [x] **6.6.3** CORS configuration (`config/packages/nelmio_cors.yaml`)
  ```yaml
  nelmio_cors:
    defaults:
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

### Status: Not Started

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
      #[OA\Get(summary: 'List tasks', description: 'Returns paginated list of tasks')]
      #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'in_progress', 'completed']))]
      #[OA\Response(response: 200, description: 'List of tasks')]
      public function list(): JsonResponse { ... }
  }
  ```

- [ ] **6.7.3** Define shared schemas in `src/OpenApi/Schemas.php`

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

### Files to Create
```
config/packages/nelmio_api_doc.yaml
config/routes/nelmio_api_doc.yaml
src/OpenApi/Schemas.php
src/Controller/Api/*Controller.php (add OA attributes)
```

---

## Sub-Phase 6.8: AI Agent Documentation (NOT STARTED)

### Status: Not Started

### Tasks

- [ ] **6.8.1** Create API quickstart guide (`docs/api/quickstart.md`)
- [ ] **6.8.2** Create structured vs natural language guide (`docs/api/structured-vs-natural.md`)
- [ ] **6.8.3** Create use cases documentation (`docs/api/use-cases.md`)
- [ ] **6.8.4** Create error code reference (`docs/api/errors.md`)
- [ ] **6.8.5** Create complete API reference (`docs/api/reference.md`)
- [ ] **6.8.6** Create filter examples (`docs/api/filters.md`)

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

## Sub-Phase 6.9: API Testing (80% COMPLETE)

### Status: 80% Complete

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
- [x] Recurring task tests (`RecurringTaskApiTest.php`)

### Remaining Tests

- [ ] **6.9.1** Batch operation tests (`tests/Functional/Api/BatchApiTest.php`)
  ```php
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

- [ ] **6.9.2** Search endpoint tests (`tests/Functional/Api/SearchApiTest.php`)
  ```php
  public function testSearchTasks(): void
  public function testSearchProjects(): void
  public function testSearchAll(): void
  public function testSearchMinQueryLength(): void
  public function testSearchPagination(): void
  ```

- [ ] **6.9.3** Project tree tests
  ```php
  public function testGetProjectTree(): void
  public function testProjectTreeWithTaskCounts(): void
  public function testProjectTreeExcludesArchived(): void
  ```

### Files to Create
```
tests/Functional/Api/BatchApiTest.php
tests/Functional/Api/SearchApiTest.php
```

---

## Phase 6 Deliverables Checklist

### API Infrastructure (COMPLETE)
- [x] Response format consistent across all endpoints
- [x] Error handling with exception mappers
- [x] Request ID tracking
- [x] API logging

### Endpoints
- [x] All CRUD endpoints (tasks, projects, tags, saved-filters)
- [x] Specialized view endpoints (today, upcoming, overdue, no-date, completed)
- [x] Status update and reschedule endpoints
- [x] Complete-forever for recurring tasks
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

### Rate Limiting (COMPLETE)
- [x] Rate limiting enforced per token (authenticated)
- [x] Rate limiting enforced per IP (anonymous)
- [x] Login rate limiting (brute force protection)
- [x] Headers on all responses

### Authentication (COMPLETE)
- [x] Token generation and validation
- [x] Token refresh mechanism
- [x] Token revocation
- [x] Dual header support (Bearer + X-API-Key)

### Headers (COMPLETE)
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
- [x] Recurring task tests
- [ ] Batch operation tests
- [ ] Search endpoint tests
- [ ] Project tree tests

---

## Remaining Work Priority

### High Priority (Core functionality gaps)
1. Project tree endpoint
2. Global search endpoint
3. Task batch operations

### Medium Priority (Documentation)
4. OpenAPI documentation with Swagger UI
5. AI quickstart guide
6. Error code reference

### Lower Priority (Nice to have)
7. Full AI documentation suite
8. Additional edge case tests

---

## Estimated Remaining Effort

| Item | Complexity |
|------|------------|
| Project tree endpoint | Low |
| Global search endpoint | Medium |
| Task batch operations | High |
| OpenAPI documentation | Medium |
| AI documentation | Low |
| Additional tests | Medium |

# Phase 6: API Development

## Overview
**Duration**: Week 6  
**Goal**: Finalize the REST API with API Platform integration, comprehensive documentation, batch operations, rate limiting, and AI-agent friendly features.

## Prerequisites
- Phases 1-5 completed
- All core functionality working
- Basic API endpoints operational

---

## Core Design Decisions

### Standard Error Format
```php
ALL API errors MUST use this consistent format:

{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": {
      // Optional field-specific errors or additional context
      "field_name": ["Specific error message"],
      "retry_after": 3600
    }
  },
  "meta": {
    "timestamp": "2026-01-23T10:30:00Z",
    "request_id": "uuid-v4"
  }
}

STANDARD ERROR CODES:
- ERROR_VALIDATION: 422 - Validation failed (details contains field errors)
- ERROR_AUTH_REQUIRED: 401 - Authentication required
- ERROR_INVALID_TOKEN: 401 - Invalid or expired token
- ERROR_INVALID_CREDENTIALS: 401 - Wrong username/password
- ERROR_NOT_FOUND: 404 - Resource not found
- ERROR_DUPLICATE: 409 - Resource already exists
- ERROR_RATE_LIMIT: 429 - Rate limit exceeded
- ERROR_PERMISSION: 403 - Permission denied
- ERROR_INVALID_STATUS: 422 - Invalid status value
- ERROR_INVALID_PRIORITY: 422 - Invalid priority value (not 0-4)
- ERROR_INVALID_RECURRENCE: 422 - Invalid recurrence pattern
- ERROR_BATCH_LIMIT: 400 - Too many batch operations
- ERROR_AMBIGUOUS_DATE: 422 - Could not parse natural language date
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
- Access-Control-Allow-Origin: * (or specific origins)
- Access-Control-Expose-Headers: X-RateLimit-*, X-Request-ID
```

### Include Parameter (Eager Loading)
```
ALL task-listing endpoints support the `include` parameter:

Supported expansions:
- include=project     → Include project object
- include=tags        → Include tags array
- include=subtasks    → Include subtasks array
- include=user        → Include user object (admin only)

Multiple expansions:
- include=project,tags,subtasks

Implementation at API Platform level:
- Use serialization groups
- Eager load relationships to avoid N+1
- Include parameter parsed by custom StateProvider
```

---

## Sub-Phase 6.1: API Platform Configuration

### Objective
Configure API Platform 3.x for production-ready API documentation and standards.

### Tasks

- [ ] **6.1.1** Configure API Platform
  ```yaml
  # config/packages/api_platform.yaml
  api_platform:
    title: 'Todo List API'
    description: 'AI-accessible todo list application API'
    version: '1.0.0'
    
    defaults:
      pagination_items_per_page: 20
      pagination_maximum_items_per_page: 100
      
    formats:
      json:
        mime_types: ['application/json']
    
    docs_formats:
      json: true
      jsonld: false
      html: true
    
    swagger_ui:
      enabled: true
      
    redoc:
      enabled: true
      
    # Error handling at API Platform level
    exception_to_status:
      Symfony\Component\Security\Core\Exception\AccessDeniedException: 403
      App\Exception\ValidationException: 422
      App\Exception\InvalidStatusException: 422
      App\Exception\InvalidPriorityException: 422
      App\Exception\InvalidRecurrenceException: 422
      App\Exception\ResourceNotFoundException: 404
  ```

- [ ] **6.1.2** Configure resource classes with include support
  ```php
  // src/Entity/Task.php
  
  #[ApiResource(
      operations: [
          new GetCollection(
              uriTemplate: '/tasks',
              paginationEnabled: true
          ),
          new Get(uriTemplate: '/tasks/{id}'),
          new Post(uriTemplate: '/tasks'),
          new Put(uriTemplate: '/tasks/{id}'),
          new Patch(uriTemplate: '/tasks/{id}'),
          new Delete(uriTemplate: '/tasks/{id}'),
      ],
      normalizationContext: ['groups' => ['task:read']],
      denormalizationContext: ['groups' => ['task:write']],
      extraProperties: [
          'include_supported' => ['project', 'tags', 'subtasks']
      ]
  )]
  ```

- [ ] **6.1.3** Configure serialization groups
  ```php
  // src/Entity/Task.php
  
  #[Groups(['task:read'])]
  private int $id;
  
  #[Groups(['task:read', 'task:write'])]
  private string $title;
  
  #[Groups(['task:read'])]
  private DateTimeImmutable $createdAt;
  
  // Conditionally included based on ?include=project
  #[Groups(['task:read:project'])]
  private ?Project $project = null;
  
  // Conditionally included based on ?include=tags
  #[Groups(['task:read:tags'])]
  private Collection $tags;
  ```

- [ ] **6.1.4** Create IncludeParameterExtension
  ```php
  // src/ApiPlatform/IncludeParameterExtension.php
  
  /**
   * Handles ?include=project,tags parameter to enable eager loading
   * and add appropriate serialization groups.
   */
  class IncludeParameterExtension implements QueryCollectionExtensionInterface
  {
      public function applyToCollection(
          QueryBuilder $queryBuilder,
          QueryNameGeneratorInterface $queryNameGenerator,
          string $resourceClass,
          Operation $operation = null,
          array $context = []
      ): void {
          $includes = $this->parseIncludeParameter($context);
          
          foreach ($includes as $include) {
              // Add JOIN for eager loading
              $queryBuilder->leftJoin("o.$include", $include)
                           ->addSelect($include);
          }
      }
  }
  ```

- [ ] **6.1.5** Set up API versioning prefix
  ```yaml
  # config/routes/api_platform.yaml
  api_platform:
    resource: .
    type: api_platform
    prefix: /api/v1
  ```

### Completion Criteria
- [ ] API Platform configured
- [ ] Include parameter working for eager loading
- [ ] Swagger UI accessible at /api/v1/docs
- [ ] Redoc accessible at /api/v1/redoc
- [ ] Error responses use standard format
- [ ] All endpoints properly documented

### Files to Create/Update
```
config/packages/api_platform.yaml
config/routes/api_platform.yaml
src/Entity/*.php (updated with API Platform annotations)
src/ApiPlatform/IncludeParameterExtension.php (new)
```

---

## Sub-Phase 6.2: Custom API Endpoints

### Objective
Implement specialized endpoints that go beyond basic CRUD with full documentation of query parameters and error conditions.

### Tasks

- [ ] **6.2.1** Create specialized task endpoints with full query param support
  ```php
  // src/Controller/Api/TaskController.php
  
  Custom endpoints (all support standard parameters):
  GET  /api/v1/tasks/today
  GET  /api/v1/tasks/upcoming
  GET  /api/v1/tasks/overdue
  GET  /api/v1/tasks/no-date
  POST /api/v1/tasks/{id}/complete-forever
  PATCH /api/v1/tasks/{id}/status
  PATCH /api/v1/tasks/{id}/reschedule
  
  STANDARD QUERY PARAMETERS (all specialized endpoints):
  
  Pagination:
  - page: int (default: 1)
  - per_page: int (default: 20, max: 100)
  
  Include expansions:
  - include: project,tags,subtasks
  
  Filtering:
  - project_id: int|int[]
  - tag: string|string[]
  - tag_match: any|all
  - priority: int
  - priority_min: int
  - priority_max: int
  - include_archived_projects: bool (default: false)
  
  Sorting (defaults vary by endpoint, but all accept overrides):
  - sort: due_date|priority|created_at|updated_at|title|overdue_days
  - sort_order: asc|desc
  ```

- [ ] **6.2.2** Implement status update endpoint with error handling
  ```php
  PATCH /api/v1/tasks/{id}/status
  
  Request:
  {
    "status": "completed"
  }
  
  Success Response (200):
  {
    "data": {
      "id": 123,
      "status": "completed",
      "completed_at": "2026-01-23T15:30:00Z"
    },
    "undo_token": "undo_xyz789",
    "undo_expires_at": "2026-01-23T15:31:00Z"
  }
  
  ERROR CONDITIONS:
  
  Invalid status value (422):
  {
    "error": {
      "code": "ERROR_INVALID_STATUS",
      "message": "Invalid status value",
      "details": {
        "status": ["Must be one of: pending, in_progress, completed"],
        "provided": "invalid"
      }
    }
  }
  
  Task not found (404):
  {
    "error": {
      "code": "ERROR_NOT_FOUND",
      "message": "Task not found"
    }
  }
  
  Permission denied (403):
  {
    "error": {
      "code": "ERROR_PERMISSION",
      "message": "You do not have permission to modify this task"
    }
  }
  ```

- [ ] **6.2.3** Implement reschedule endpoint with error handling
  ```php
  PATCH /api/v1/tasks/{id}/reschedule
  
  Request (structured - RECOMMENDED):
  {
    "due_date": "2026-01-24",
    "due_time": "14:00"
  }
  
  Request (natural language):
  {
    "due_date": "tomorrow at 2pm"
  }
  
  Success Response (200):
  {
    "data": {
      "id": 123,
      "due_date": "2026-01-24T00:00:00Z",
      "due_time": "14:00:00"
    }
  }
  
  ERROR CONDITIONS:
  
  Ambiguous/unparseable date (422):
  {
    "error": {
      "code": "ERROR_AMBIGUOUS_DATE",
      "message": "Could not parse date from natural language",
      "details": {
        "due_date": ["Could not understand 'next blue moon'. Try 'tomorrow' or '2026-01-24'."],
        "suggestion": "Use ISO format (YYYY-MM-DD) for unambiguous dates"
      }
    }
  }
  
  Invalid date (422):
  {
    "error": {
      "code": "ERROR_VALIDATION",
      "message": "Invalid date",
      "details": {
        "due_date": ["Date cannot be in the past"]
      }
    }
  }
  ```

- [ ] **6.2.4** Create project tree endpoint
  ```php
  GET /api/v1/projects/tree
  
  Query params:
  - include_archived: bool (default: false)
  - include_task_counts: bool (default: true)
  
  Response:
  {
    "data": [
      {
        "id": 1,
        "name": "Work",
        "children": [...],
        "task_count": 15,
        "pending_task_count": 10
      }
    ],
    "meta": {
      "timestamp": "2026-01-23T10:30:00Z",
      "request_id": "uuid-v4"
    }
  }
  ```

- [ ] **6.2.5** Create global search endpoint
  ```php
  GET /api/v1/search?q={query}
  
  Query params:
  - q: string (required, min 2 chars)
  - type: tasks|projects|all (default: all)
  - include_archived_projects: bool (default: false)
  - page: int
  - per_page: int
  
  Response:
  {
    "data": {
      "tasks": [...],
      "projects": [...]
    },
    "meta": {
      "task_count": 5,
      "project_count": 2,
      "query": "meeting",
      "timestamp": "2026-01-23T10:30:00Z",
      "request_id": "uuid-v4"
    }
  }
  
  ERROR CONDITIONS:
  
  Query too short (400):
  {
    "error": {
      "code": "ERROR_VALIDATION",
      "message": "Query too short",
      "details": {
        "q": ["Query must be at least 2 characters"]
      }
    }
  }
  ```

### Completion Criteria
- [ ] All specialized endpoints working
- [ ] All endpoints accept standard query parameters
- [ ] Natural language accepted where appropriate
- [ ] Error responses documented and implemented
- [ ] Responses consistent with API format

### Files to Update
```
src/Controller/Api/TaskController.php
src/Controller/Api/ProjectController.php
src/Controller/Api/SearchController.php (new)
```

---

## Sub-Phase 6.3: Batch Operations

### Objective
Implement batch operations for efficient bulk updates with clear execution semantics.

### Tasks

- [ ] **6.3.1** Create batch endpoint with all supported operations
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
      {
        "action": "create",
        "data": {
          "title": "New task 1",
          "due_date": "tomorrow"
        }
      },
      {
        "action": "update",
        "id": 123,
        "data": {
          "priority": 3
        }
      },
      {
        "action": "delete",
        "id": 124
      },
      {
        "action": "complete",
        "id": 125
      },
      {
        "action": "reschedule",
        "id": 126,
        "data": {
          "due_date": "next Monday"
        }
      }
    ]
  }
  ```

- [ ] **6.3.2** Define execution semantics
  ```php
  EXECUTION ORDER AND TRANSACTION BEHAVIOR:
  
  1. Operations execute SEQUENTIALLY in array order
     - This ensures predictable behavior
     - Earlier operations can create resources used by later ones
  
  2. PARTIAL SUCCESS is allowed
     - Each operation succeeds or fails independently
     - Failed operations do not roll back successful ones
     - All operations are attempted (no early exit on failure)
  
  3. ATOMIC OPTION available
     - ?atomic=true → All-or-nothing (rollback on any failure)
     - Default: false (partial success allowed)
  
  POST /api/v1/tasks/batch?atomic=true
  - If ANY operation fails, ALL are rolled back
  - Returns 422 with all errors if atomic fails
  ```

- [ ] **6.3.3** Implement BatchOperationService
  ```php
  // src/Service/BatchOperationService.php
  
  public function execute(User $user, array $operations, bool $atomic = false): BatchResult
  {
      $results = [];
      
      if ($atomic) {
          $this->entityManager->beginTransaction();
      }
      
      try {
          foreach ($operations as $index => $op) {
              try {
                  $result = $this->executeOperation($user, $op);
                  $results[$index] = BatchOperationResult::success($op['action'], $result);
              } catch (\Exception $e) {
                  $results[$index] = BatchOperationResult::failure($op['action'], $e);
                  
                  if ($atomic) {
                      throw $e; // Trigger rollback
                  }
              }
          }
          
          if ($atomic) {
              $this->entityManager->commit();
          }
      } catch (\Exception $e) {
          if ($atomic) {
              $this->entityManager->rollback();
          }
          // Re-throw for atomic mode, or continue for partial
          if ($atomic) {
              throw new BatchFailedException($results, $e);
          }
      }
      
      return new BatchResult($results);
  }
  ```

- [ ] **6.3.4** Define batch response format with validation errors
  ```php
  Response (partial success):
  HTTP 200 OK
  {
    "data": {
      "results": [
        {
          "index": 0,
          "action": "create",
          "success": true,
          "data": { "id": 456, "title": "New task 1", ... }
        },
        {
          "index": 1,
          "action": "update",
          "success": true,
          "data": { "id": 123, "priority": 3, ... }
        },
        {
          "index": 2,
          "action": "delete",
          "success": false,
          "error": {
            "code": "ERROR_NOT_FOUND",
            "message": "Task not found"
          }
        },
        {
          "index": 3,
          "action": "complete",
          "success": true,
          "data": { "id": 125, "status": "completed", ... }
        },
        {
          "index": 4,
          "action": "reschedule",
          "success": false,
          "error": {
            "code": "ERROR_AMBIGUOUS_DATE",
            "message": "Could not parse date",
            "details": {
              "due_date": ["Could not understand 'next blue moon'"]
            }
          }
        }
      ]
    },
    "meta": {
      "total": 5,
      "successful": 3,
      "failed": 2,
      "timestamp": "2026-01-23T10:30:00Z",
      "request_id": "uuid-v4"
    },
    "undo_token": "undo_batch_xyz"
  }
  
  Response (atomic mode failure):
  HTTP 422 Unprocessable Entity
  {
    "error": {
      "code": "ERROR_BATCH_ATOMIC_FAILED",
      "message": "Batch operation failed atomically",
      "details": {
        "failed_index": 2,
        "failed_action": "delete",
        "reason": "Task not found"
      }
    },
    "data": {
      "results": [...]  // All marked as rolled back
    }
  }
  ```

- [ ] **6.3.5** Implement batch undo
  ```php
  // Store all operations in single undo token
  // Undo reverses ALL successful operations atomically
  
  POST /api/v1/undo/{token}
  
  // For batch: undoes creates (deletes them), undoes updates (reverts),
  // undoes deletes (restores), undoes completes (uncompletes)
  ```

- [ ] **6.3.6** Add batch limits and validation
  ```php
  // Maximum 100 operations per batch
  // Validate before processing
  
  if (count($operations) > 100) {
      throw new BadRequestException([
          'code' => 'ERROR_BATCH_LIMIT',
          'message' => 'Maximum 100 operations per batch',
          'details' => [
              'limit' => 100,
              'provided' => count($operations)
          ]
      ]);
  }
  
  // Validate each operation has required fields
  foreach ($operations as $index => $op) {
      if (!isset($op['action'])) {
          throw new ValidationException([
              "operations[$index].action" => ['Action is required']
          ]);
      }
      if (in_array($op['action'], ['update', 'delete', 'complete', 'reschedule'])) {
          if (!isset($op['id'])) {
              throw new ValidationException([
                  "operations[$index].id" => ['ID is required for ' . $op['action']]
              ]);
          }
      }
  }
  ```

### Completion Criteria
- [ ] Batch endpoint processes multiple operations
- [ ] All 5 operations supported (create, update, delete, complete, reschedule)
- [ ] Sequential execution order documented
- [ ] Partial failures handled correctly (default mode)
- [ ] Atomic mode available with rollback
- [ ] Results include success/failure per operation with error details
- [ ] Single undo token for batch
- [ ] Limits enforced with clear error

### Files to Create
```
src/Controller/Api/BatchController.php
src/Service/BatchOperationService.php
src/DTO/BatchResult.php
src/DTO/BatchOperationResult.php
src/Exception/BatchFailedException.php
```

---

## Sub-Phase 6.4: Rate Limiting

### Objective
Implement API rate limiting to prevent abuse with clear documentation of scope.

### Tasks

- [ ] **6.4.1** Configure rate limiter
  ```yaml
  # config/packages/rate_limiter.yaml
  framework:
    rate_limiter:
      api_token:
        policy: 'sliding_window'
        limit: 1000
        interval: '1 hour'
      
      # Stricter limit for unauthenticated requests
      api_anonymous:
        policy: 'sliding_window'
        limit: 100
        interval: '1 hour'
  ```

- [ ] **6.4.2** Document rate limit scope
  ```php
  RATE LIMIT SCOPING:
  
  Authenticated requests:
  - Limit: 1000 requests per hour
  - Counter tied to API TOKEN (not IP)
  - Each user's token has independent counter
  - Multiple tokens per user = multiple counters
  
  Unauthenticated requests:
  - Limit: 100 requests per hour
  - Counter tied to IP address
  - Only applies to public endpoints (if any)
  
  Auth endpoints (login):
  - Separate limiter: 5 attempts per minute
  - Counter tied to IP address
  - Prevents brute force attacks
  ```

- [ ] **6.4.3** Create RateLimitSubscriber
  ```php
  // src/EventSubscriber/RateLimitSubscriber.php
  
  public function onKernelRequest(RequestEvent $event): void
  {
      $request = $event->getRequest();
      
      if (!$this->isApiRequest($request)) {
          return;
      }
      
      // Get appropriate limiter based on authentication
      $token = $this->getAuthToken($request);
      if ($token) {
          $limiter = $this->rateLimiterFactory->create('api_token', $token);
      } else {
          $limiter = $this->rateLimiterFactory->create('api_anonymous', $request->getClientIp());
      }
      
      $limit = $limiter->consume();
      
      if (!$limit->isAccepted()) {
          throw new TooManyRequestsHttpException(
              $limit->getRetryAfter()->getTimestamp() - time(),
              'Rate limit exceeded'
          );
      }
      
      // Store for header generation
      $request->attributes->set('rate_limit', $limit);
  }
  ```

- [ ] **6.4.4** Add rate limit headers
  ```php
  // src/EventSubscriber/RateLimitHeaderSubscriber.php
  
  public function onKernelResponse(ResponseEvent $event): void
  {
      $request = $event->getRequest();
      $response = $event->getResponse();
      
      $limit = $request->attributes->get('rate_limit');
      if ($limit) {
          $response->headers->set('X-RateLimit-Limit', (string) $limit->getLimit());
          $response->headers->set('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
          $response->headers->set('X-RateLimit-Reset', (string) $limit->getRetryAfter()->getTimestamp());
      }
  }
  ```

- [ ] **6.4.5** Create rate limit error response
  ```php
  // 429 Too Many Requests
  {
    "error": {
      "code": "ERROR_RATE_LIMIT",
      "message": "Too many requests. Please try again later.",
      "details": {
        "limit": 1000,
        "window": "1 hour",
        "retry_after": 3600
      }
    },
    "meta": {
      "timestamp": "2026-01-23T10:30:00Z",
      "request_id": "uuid-v4"
    }
  }
  
  Headers included:
  Retry-After: 3600
  X-RateLimit-Limit: 1000
  X-RateLimit-Remaining: 0
  X-RateLimit-Reset: 1706013000
  ```

### Completion Criteria
- [ ] Rate limiting enforced per token (authenticated)
- [ ] Rate limiting enforced per IP (unauthenticated)
- [ ] Headers included in all responses
- [ ] 429 returned when exceeded
- [ ] Retry-After header included
- [ ] Scope documented clearly

### Files to Create
```
config/packages/rate_limiter.yaml
src/EventSubscriber/RateLimitSubscriber.php
src/EventSubscriber/RateLimitHeaderSubscriber.php
```

---

## Sub-Phase 6.5: Authentication Integration

### Objective
Document authentication flow and token handling for API access.

### Tasks

- [ ] **6.5.1** Document token generation
  ```php
  POST /api/v1/auth/token
  
  Request:
  {
    "email": "user@example.com",
    "password": "secret"
  }
  
  Success Response (200):
  {
    "data": {
      "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
      "expires_at": "2026-02-22T10:30:00Z",
      "user": {
        "id": 1,
        "email": "user@example.com",
        "timezone": "America/Los_Angeles"
      }
    }
  }
  
  TOKEN PROPERTIES:
  - Type: JWT (JSON Web Token)
  - Expiry: 30 days by default
  - Contains: user_id, issued_at, expires_at
  - Algorithm: HS256
  ```

- [ ] **6.5.2** Document authentication middleware
  ```php
  AUTHENTICATION FLOW:
  
  1. Client sends request with Authorization header:
     Authorization: Bearer <token>
  
  2. Middleware validates token:
     - Check token format
     - Verify signature
     - Check expiry
     - Load user from token claims
  
  3. On success: Request proceeds with authenticated user
  
  4. On failure: Return 401 with appropriate error code
     - Missing token: ERROR_AUTH_REQUIRED
     - Invalid format: ERROR_INVALID_TOKEN
     - Expired: ERROR_INVALID_TOKEN
     - Wrong signature: ERROR_INVALID_TOKEN
  ```

- [ ] **6.5.3** Document token expiry and revocation
  ```php
  TOKEN EXPIRY:
  - Default TTL: 30 days
  - Configurable per token on creation
  - Expired tokens return 401 ERROR_INVALID_TOKEN
  
  TOKEN REVOCATION:
  POST /api/v1/auth/revoke
  {
    "token": "eyJ0eXAi..."  // Optional, defaults to current token
  }
  
  - Revoked tokens are blacklisted
  - Blacklist stored in Redis with TTL matching token expiry
  - Immediate effect (next request fails)
  
  REFRESH:
  POST /api/v1/auth/refresh
  
  - Requires valid (non-expired) token
  - Returns new token with fresh expiry
  - Old token remains valid until its expiry
  ```

- [ ] **6.5.4** Document protected vs public endpoints
  ```php
  PUBLIC ENDPOINTS (no auth required):
  - POST /api/v1/auth/token (login)
  - POST /api/v1/auth/register (if enabled)
  - GET /api/v1/docs (documentation)
  - GET /api/v1/health (health check)
  
  PROTECTED ENDPOINTS (auth required):
  - All other endpoints
  - Return 401 if no valid token provided
  ```

### Completion Criteria
- [ ] Token generation documented
- [ ] Authentication flow documented
- [ ] Token expiry behavior documented
- [ ] Token revocation documented
- [ ] Protected vs public endpoints listed

### Files to Update
```
src/Controller/Api/AuthController.php (reference)
config/packages/security.yaml (reference)
docs/api/authentication.md (new)
```

---

## Sub-Phase 6.6: API Response Headers

### Objective
Implement consistent headers and metadata for all API responses.

### Tasks

- [ ] **6.6.1** Add request ID to all responses
  ```php
  // src/EventSubscriber/RequestIdSubscriber.php
  
  public function onKernelRequest(RequestEvent $event): void
  {
      $request = $event->getRequest();
      $requestId = $request->headers->get('X-Request-ID') 
          ?? Uuid::v4()->toString();
      $request->attributes->set('request_id', $requestId);
  }
  
  public function onKernelResponse(ResponseEvent $event): void
  {
      $request = $event->getRequest();
      $response = $event->getResponse();
      $requestId = $request->attributes->get('request_id');
      $response->headers->set('X-Request-ID', $requestId);
  }
  ```

- [ ] **6.6.2** Add timestamp to all responses
  ```php
  // Include in meta for all responses
  "meta": {
    "timestamp": "2026-01-23T10:30:00Z",
    "request_id": "uuid-v4"
  }
  ```

- [ ] **6.6.3** Document complete header set
  ```php
  HEADERS INCLUDED ON EVERY RESPONSE:
  
  Always present:
  - Content-Type: application/json
  - X-Request-ID: <uuid>
  
  Rate limiting (authenticated requests):
  - X-RateLimit-Limit: 1000
  - X-RateLimit-Remaining: <count>
  - X-RateLimit-Reset: <timestamp>
  
  CORS:
  - Access-Control-Allow-Origin: <origin>
  - Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
  - Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID
  - Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, X-Request-ID
  
  Caching (where applicable):
  - Cache-Control: private, max-age=0 (user-specific data)
  - Cache-Control: public, max-age=3600 (static data)
  
  Error responses add:
  - Retry-After: <seconds> (for 429 and 503)
  ```

- [ ] **6.6.4** Add CORS headers
  ```yaml
  # config/packages/nelmio_cors.yaml
  nelmio_cors:
    defaults:
      allow_origin: ['*']
      allow_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
      allow_headers: ['Content-Type', 'Authorization', 'X-Request-ID']
      expose_headers: ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset', 'X-Request-ID']
      max_age: 3600
  ```

- [ ] **6.6.5** Add cache headers for appropriate endpoints
  ```php
  // For user-specific data (default)
  $response->setPrivate();
  $response->setMaxAge(0);
  
  // For documentation endpoints
  $response->setPublic();
  $response->setMaxAge(3600);
  ```

### Completion Criteria
- [ ] Request ID on all responses
- [ ] Timestamp in meta
- [ ] Complete header set documented
- [ ] CORS configured
- [ ] Cache headers appropriate

### Files to Create/Update
```
composer require nelmio/cors-bundle
config/packages/nelmio_cors.yaml
src/EventSubscriber/RequestIdSubscriber.php
```

---

## Sub-Phase 6.7: OpenAPI Documentation

### Objective
Generate comprehensive OpenAPI 3.0 documentation with full schema definitions.

### Tasks

- [ ] **6.7.1** Define all shared schemas
  ```php
  // src/OpenApi/Schemas.php
  
  #[OA\Schema(
      schema: 'Task',
      required: ['id', 'title', 'status'],
      properties: [
          new OA\Property(property: 'id', type: 'integer', example: 123),
          new OA\Property(property: 'title', type: 'string', example: 'Review document'),
          new OA\Property(property: 'description', type: 'string', nullable: true),
          new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'completed']),
          new OA\Property(property: 'priority', type: 'integer', minimum: 0, maximum: 4),
          new OA\Property(property: 'due_date', type: 'string', format: 'date-time', nullable: true),
          new OA\Property(property: 'due_time', type: 'string', format: 'time', nullable: true),
          new OA\Property(property: 'is_recurring', type: 'boolean'),
          new OA\Property(property: 'recurrence_rule', type: 'string', nullable: true),
          new OA\Property(property: 'project_id', type: 'integer', nullable: true),
          new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
          new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
          new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
      ]
  )]
  
  #[OA\Schema(
      schema: 'Project',
      required: ['id', 'name'],
      properties: [
          new OA\Property(property: 'id', type: 'integer'),
          new OA\Property(property: 'name', type: 'string'),
          new OA\Property(property: 'color', type: 'string', pattern: '^#[0-9A-Fa-f]{6}$'),
          new OA\Property(property: 'icon', type: 'string', nullable: true),
          new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
          new OA\Property(property: 'position', type: 'integer'),
          new OA\Property(property: 'is_archived', type: 'boolean'),
          new OA\Property(property: 'show_children_tasks', type: 'boolean'),
          new OA\Property(property: 'task_count', type: 'integer'),
          new OA\Property(property: 'pending_task_count', type: 'integer'),
      ]
  )]
  
  #[OA\Schema(
      schema: 'Error',
      required: ['error'],
      properties: [
          new OA\Property(
              property: 'error',
              properties: [
                  new OA\Property(property: 'code', type: 'string', example: 'ERROR_VALIDATION'),
                  new OA\Property(property: 'message', type: 'string'),
                  new OA\Property(property: 'details', type: 'object', additionalProperties: true),
              ],
              type: 'object'
          ),
          new OA\Property(
              property: 'meta',
              properties: [
                  new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                  new OA\Property(property: 'request_id', type: 'string', format: 'uuid'),
              ],
              type: 'object'
          ),
      ]
  )]
  
  #[OA\Schema(
      schema: 'BatchResult',
      properties: [
          new OA\Property(
              property: 'data',
              properties: [
                  new OA\Property(property: 'results', type: 'array', items: new OA\Items(ref: '#/components/schemas/BatchOperationResult')),
              ],
              type: 'object'
          ),
          new OA\Property(
              property: 'meta',
              properties: [
                  new OA\Property(property: 'total', type: 'integer'),
                  new OA\Property(property: 'successful', type: 'integer'),
                  new OA\Property(property: 'failed', type: 'integer'),
              ],
              type: 'object'
          ),
          new OA\Property(property: 'undo_token', type: 'string'),
      ]
  )]
  
  #[OA\Schema(
      schema: 'BatchOperationResult',
      properties: [
          new OA\Property(property: 'index', type: 'integer'),
          new OA\Property(property: 'action', type: 'string', enum: ['create', 'update', 'delete', 'complete', 'reschedule']),
          new OA\Property(property: 'success', type: 'boolean'),
          new OA\Property(property: 'data', type: 'object', nullable: true),
          new OA\Property(property: 'error', ref: '#/components/schemas/Error', nullable: true),
      ]
  )]
  ```

- [ ] **6.7.2** Enhance endpoint documentation with request bodies
  ```php
  #[OA\Post(
      path: '/api/v1/tasks',
      summary: 'Create task',
      description: 'Creates a new task. Supports both structured and natural language input.',
      tags: ['Tasks'],
      requestBody: new OA\RequestBody(
          required: true,
          content: new OA\JsonContent(
              required: ['title'],
              properties: [
                  new OA\Property(property: 'title', type: 'string', example: 'Review document'),
                  new OA\Property(property: 'description', type: 'string'),
                  new OA\Property(property: 'due_date', type: 'string', description: 'ISO date or natural language', example: '2026-01-24'),
                  new OA\Property(property: 'due_time', type: 'string', example: '14:00'),
                  new OA\Property(property: 'priority', type: 'integer', minimum: 0, maximum: 4),
                  new OA\Property(property: 'project_id', type: 'integer'),
                  new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
                  new OA\Property(property: 'recurrence_rule', type: 'string', example: 'every Monday'),
                  new OA\Property(property: 'is_recurring', type: 'boolean'),
              ]
          )
      )
  )]
  #[OA\Response(
      response: 201,
      description: 'Task created',
      content: new OA\JsonContent(
          properties: [
              new OA\Property(property: 'data', ref: '#/components/schemas/Task'),
          ]
      )
  )]
  #[OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
  
  #[OA\Patch(
      path: '/api/v1/tasks/{id}',
      summary: 'Update task',
      description: 'Partially updates an existing task.',
      tags: ['Tasks'],
      requestBody: new OA\RequestBody(
          content: new OA\JsonContent(
              properties: [
                  new OA\Property(property: 'title', type: 'string'),
                  new OA\Property(property: 'description', type: 'string'),
                  new OA\Property(property: 'priority', type: 'integer'),
                  // ... other fields
              ]
          )
      )
  )]
  ```

- [ ] **6.7.3** Create OpenAPI decorator for customization
  ```php
  // src/OpenApi/OpenApiDecorator.php
  
  /**
   * Customize API Platform's default OpenAPI output.
   * 
   * This decorator:
   * - Adds global security scheme
   * - Adds server URLs
   * - Adds custom tags with descriptions
   * - Ensures consistent error schemas
   */
  final class OpenApiDecorator implements OpenApiFactoryInterface
  {
      public function __construct(
          private OpenApiFactoryInterface $decorated
      ) {}
      
      public function __invoke(array $context = []): OpenApi
      {
          $openApi = ($this->decorated)($context);
          
          // Add security scheme
          $securitySchemes = $openApi->getComponents()->getSecuritySchemes() ?? [];
          $securitySchemes['bearerAuth'] = new SecurityScheme(
              type: 'http',
              scheme: 'bearer',
              bearerFormat: 'JWT',
              description: 'API token obtained from POST /api/v1/auth/token'
          );
          
          // Add servers
          $openApi = $openApi->withServers([
              new Server(url: 'https://api.example.com', description: 'Production'),
              new Server(url: 'http://localhost:8000', description: 'Development'),
          ]);
          
          // Add tag descriptions
          $openApi = $openApi->withTags([
              new Tag(name: 'Tasks', description: 'Task management operations'),
              new Tag(name: 'Projects', description: 'Project hierarchy management'),
              new Tag(name: 'Search', description: 'Search across tasks and projects'),
              new Tag(name: 'Batch', description: 'Bulk operations'),
              new Tag(name: 'Auth', description: 'Authentication and tokens'),
          ]);
          
          return $openApi;
      }
  }
  ```

- [ ] **6.7.4** Document error responses consistently
  ```php
  #[OA\Response(
      response: 400,
      description: 'Bad Request - Invalid JSON or request format',
      content: new OA\JsonContent(ref: '#/components/schemas/Error')
  )]
  #[OA\Response(
      response: 401,
      description: 'Unauthorized - Missing or invalid token',
      content: new OA\JsonContent(ref: '#/components/schemas/Error')
  )]
  #[OA\Response(
      response: 403,
      description: 'Forbidden - Insufficient permissions',
      content: new OA\JsonContent(ref: '#/components/schemas/Error')
  )]
  #[OA\Response(
      response: 404,
      description: 'Not Found - Resource does not exist',
      content: new OA\JsonContent(ref: '#/components/schemas/Error')
  )]
  #[OA\Response(
      response: 422,
      description: 'Validation Error - Check details for field-specific errors',
      content: new OA\JsonContent(ref: '#/components/schemas/Error')
  )]
  #[OA\Response(
      response: 429,
      description: 'Rate Limit Exceeded - Check Retry-After header',
      content: new OA\JsonContent(ref: '#/components/schemas/Error')
  )]
  ```

- [ ] **6.7.5** Add authentication documentation
  ```php
  #[OA\SecurityScheme(
      securityScheme: 'bearerAuth',
      type: 'http',
      scheme: 'bearer',
      bearerFormat: 'JWT',
      description: 'API token authentication. Obtain token from POST /api/v1/auth/token'
  )]
  ```

- [ ] **6.7.6** Verify documentation accessible
  ```
  Swagger UI: /api/v1/docs
  Redoc: /api/v1/redoc
  OpenAPI JSON: /api/v1/docs.json
  OpenAPI YAML: /api/v1/docs.yaml
  ```

### Completion Criteria
- [ ] All shared schemas defined (Task, Project, Error, BatchResult)
- [ ] All endpoints documented with request bodies
- [ ] OpenAPI decorator customizes output
- [ ] All parameters described
- [ ] All responses documented
- [ ] Auth documented
- [ ] Documentation accessible

### Files to Create
```
src/OpenApi/
├── Schemas.php
└── OpenApiDecorator.php

services.yaml (register decorator)
```

---

## Sub-Phase 6.8: AI Agent Documentation

### Objective
Create documentation specifically for AI agents using the API with clear guidance on best practices.

### Tasks

- [ ] **6.8.1** Create AI quickstart guide
  ```markdown
  # API Quickstart for AI Agents
  
  ## Authentication
  POST /api/v1/auth/token
  Returns: { "data": { "token": "..." } }
  
  ## Creating a Task
  POST /api/v1/tasks
  
  RECOMMENDED: Use structured fields for reliability
  {
    "title": "Review document",
    "due_date": "2026-01-24",
    "due_time": "14:00",
    "priority": 3,
    "project_id": 5
  }
  
  ALTERNATIVE: Natural language (for user-facing input only)
  POST /api/v1/tasks?parse_natural_language=true
  {
    "input_text": "Review document tomorrow at 2pm p3 #work"
  }
  
  ## Common Operations
  ...
  ```

- [ ] **6.8.2** Document when to use structured vs natural language
  ```markdown
  ## Structured vs Natural Language Input
  
  ### USE STRUCTURED FIELDS WHEN:
  - Building automated workflows
  - Integrating with other systems
  - Maximum reliability is required
  - You have explicit date/time values
  - You know the project ID
  
  Example:
  {
    "title": "Call John",
    "due_date": "2026-01-24",
    "due_time": "14:00",
    "priority": 3,
    "project_id": 5,
    "tags": ["urgent", "calls"]
  }
  
  ### USE NATURAL LANGUAGE WHEN:
  - Passing through user input directly
  - User explicitly typed natural language
  - For quick prototyping
  
  Example:
  {
    "input_text": "Call John tomorrow at 2pm p3 #work @urgent"
  }
  
  ### DATE FORMAT GUIDANCE:
  
  PREFERRED (unambiguous):
  - "2026-01-24" (ISO format)
  - "2026-01-24T14:00:00Z" (ISO with time)
  
  ACCEPTABLE (natural language):
  - "tomorrow"
  - "next Monday"
  - "in 3 days"
  
  AVOID (ambiguous):
  - "1/2/26" (US vs EU format unclear)
  - "next week" (which day?)
  ```

- [ ] **6.8.3** Document confirmation message patterns
  ```markdown
  ## Response Interpretation for Agents
  
  ### Success Confirmation
  When an action succeeds, confirm to user:
  
  Task created:
  "Created task 'Review document' due tomorrow at 2pm with high priority."
  
  Task completed:
  "Marked 'Review document' as complete."
  
  If recurring:
  "Marked 'Weekly meeting' as complete. Next occurrence scheduled for Monday."
  
  Batch operation:
  "Processed 5 tasks: 4 successful, 1 failed (Task 124 not found)."
  
  ### Error Handling
  When an error occurs, explain clearly:
  
  Validation error:
  "Could not create task: due date cannot be in the past."
  
  Ambiguous date:
  "I couldn't understand 'next blue moon'. Try 'tomorrow' or a specific date like '2026-01-24'."
  
  Rate limit:
  "Too many requests. Please wait 5 minutes before trying again."
  ```

- [ ] **6.8.4** Document use cases with examples
  ```markdown
  ## Use Case: Get today's tasks
  GET /api/v1/tasks/today?sort=priority&sort_order=desc&include=project,tags
  
  ## Use Case: Create task for tomorrow
  POST /api/v1/tasks
  { "title": "...", "due_date": "2026-01-24" }
  
  ## Use Case: Reschedule overdue tasks
  1. GET /api/v1/tasks/overdue
  2. POST /api/v1/tasks/batch
     {
       "operations": [
         { "action": "reschedule", "id": 123, "data": { "due_date": "2026-01-24" } },
         { "action": "reschedule", "id": 124, "data": { "due_date": "2026-01-24" } }
       ]
     }
  
  ## Use Case: Find high-priority work tasks
  GET /api/v1/tasks?project_id=5&priority_min=3&include=tags
  
  ## Use Case: Complete a task and check if recurring
  PATCH /api/v1/tasks/123/status
  { "status": "completed" }
  
  Check response.next_task to see if a new instance was created.
  ```

- [ ] **6.8.5** Document filter combinations
  ```markdown
  ## Filter Examples
  
  ### High priority work tasks
  GET /api/v1/tasks?project_id=5&priority_min=3
  
  ### Tasks due this week with tag
  GET /api/v1/tasks?due_after=2026-01-20&due_before=2026-01-26&tag=work
  
  ### Completed tasks from last week
  GET /api/v1/tasks?status=completed&completed_after=2026-01-13&completed_before=2026-01-20
  
  ### Tasks with ALL specified tags
  GET /api/v1/tasks?tag[]=urgent&tag[]=work&tag_match=all
  
  ### Tasks with ANY specified tags
  GET /api/v1/tasks?tag[]=urgent&tag[]=important&tag_match=any
  ```

- [ ] **6.8.6** Document error codes and handling
  ```markdown
  ## Error Handling
  
  All errors return consistent format:
  {
    "error": {
      "code": "ERROR_CODE",
      "message": "Human-readable message",
      "details": { ... }
    }
  }
  
  ## Error Code Reference
  
  | Code | Status | Meaning |
  |------|--------|---------|
  | ERROR_VALIDATION | 422 | Check details for field errors |
  | ERROR_AUTH_REQUIRED | 401 | Include Authorization header |
  | ERROR_INVALID_TOKEN | 401 | Token expired or invalid |
  | ERROR_NOT_FOUND | 404 | Resource doesn't exist |
  | ERROR_PERMISSION | 403 | Not your resource |
  | ERROR_RATE_LIMIT | 429 | Wait and retry |
  | ERROR_INVALID_STATUS | 422 | Use: pending, in_progress, completed |
  | ERROR_INVALID_PRIORITY | 422 | Use: 0, 1, 2, 3, or 4 |
  | ERROR_AMBIGUOUS_DATE | 422 | Use ISO format instead |
  ```

- [ ] **6.8.7** Create API reference page
  ```markdown
  ## API Reference
  
  ### Tasks
  | Method | Endpoint | Description |
  |--------|----------|-------------|
  | GET | /tasks | List all tasks |
  | POST | /tasks | Create task |
  | GET | /tasks/{id} | Get single task |
  | PATCH | /tasks/{id} | Update task |
  | DELETE | /tasks/{id} | Delete task |
  | GET | /tasks/today | Today's tasks |
  | GET | /tasks/upcoming | Upcoming tasks |
  | GET | /tasks/overdue | Overdue tasks |
  | GET | /tasks/no-date | Tasks without due date |
  | PATCH | /tasks/{id}/status | Update status |
  | PATCH | /tasks/{id}/reschedule | Change due date |
  | POST | /tasks/{id}/complete-forever | Stop recurring |
  | POST | /tasks/batch | Batch operations |
  
  ### Projects
  | Method | Endpoint | Description |
  |--------|----------|-------------|
  | GET | /projects | List projects |
  | POST | /projects | Create project |
  | GET | /projects/tree | Get hierarchy |
  | GET | /projects/{id}/tasks | Project tasks |
  ...
  ```

### Completion Criteria
- [ ] Quickstart guide written
- [ ] Structured vs natural language guidance documented
- [ ] Confirmation message patterns provided
- [ ] Use cases documented with examples
- [ ] Filter examples provided
- [ ] Error codes explained with table
- [ ] Complete reference available

### Files to Create
```
docs/api/
├── quickstart.md
├── structured-vs-natural.md
├── use-cases.md
├── filters.md
├── errors.md
└── reference.md
```

---

## Sub-Phase 6.9: API Testing & Validation

### Objective
Comprehensive API testing to ensure reliability.

### Tasks

- [ ] **6.9.1** Create API test base class
  ```php
  // tests/Functional/Api/ApiTestCase.php
  
  abstract class ApiTestCase extends WebTestCase
  {
      protected function authenticatedRequest(
          string $method, 
          string $uri, 
          array $data = []
      ): Response {
          $client = static::createClient();
          $client->request($method, $uri, [], [], [
              'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getTestToken(),
              'CONTENT_TYPE' => 'application/json'
          ], json_encode($data));
          return $client->getResponse();
      }
  }
  ```

- [ ] **6.9.2** Test all endpoints
  ```php
  // Full coverage tests for each endpoint
  
  public function testGetTasks(): void
  public function testGetTasksWithPagination(): void
  public function testGetTasksWithFilters(): void
  public function testGetTasksWithIncludeProject(): void
  public function testGetTasksWithIncludeTags(): void
  public function testCreateTask(): void
  public function testCreateTaskValidation(): void
  public function testUpdateTask(): void
  public function testDeleteTask(): void
  public function testUnauthorizedAccess(): void
  ```

- [ ] **6.9.3** Test error responses
  ```php
  public function testInvalidJsonReturns400(): void
  public function testMissingAuthReturns401(): void
  public function testInvalidTokenReturns401(): void
  public function testExpiredTokenReturns401(): void
  public function testWrongUserReturns403(): void
  public function testNotFoundReturns404(): void
  public function testValidationReturns422(): void
  public function testInvalidStatusReturns422(): void
  public function testInvalidPriorityReturns422(): void
  public function testAmbiguousDateReturns422(): void
  public function testRateLimitReturns429(): void
  ```

- [ ] **6.9.4** Test batch operations
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
  public function testBatchValidationErrors(): void
  ```

- [ ] **6.9.5** Test natural language parsing at API level
  ```php
  public function testCreateTaskWithNaturalLanguageDate(): void
  public function testRescheduleWithNaturalLanguageDate(): void
  public function testNaturalLanguageDateAmbiguousError(): void
  public function testNaturalLanguageDateInvalidError(): void
  public function testBatchRescheduleWithNaturalLanguage(): void
  ```

- [ ] **6.9.6** Test rate limiting
  ```php
  public function testRateLimitHeadersPresent(): void
  public function testRateLimitDecrements(): void
  public function testRateLimitExceededReturns429(): void
  public function testRateLimitResetsAfterWindow(): void
  public function testRateLimitPerToken(): void
  public function testAnonymousRateLimitPerIp(): void
  ```

- [ ] **6.9.7** Test authentication
  ```php
  public function testLoginSuccess(): void
  public function testLoginInvalidCredentials(): void
  public function testLoginRateLimited(): void
  public function testTokenRefresh(): void
  public function testTokenRevocation(): void
  public function testRevokedTokenRejected(): void
  ```

### Completion Criteria
- [ ] All endpoints tested
- [ ] Error responses verified with correct codes
- [ ] Batch operations tested (all actions)
- [ ] Natural language parsing tested at API level
- [ ] Rate limiting tested (including reset)
- [ ] Authentication flow tested
- [ ] Edge cases covered
- [ ] 95%+ API coverage

### Files to Create
```
tests/Functional/Api/
├── ApiTestCase.php
├── TaskApiTest.php
├── ProjectApiTest.php
├── BatchApiTest.php
├── AuthApiTest.php
├── RateLimitApiTest.php
├── NaturalLanguageApiTest.php
└── ErrorResponseTest.php
```

---

## Phase 6 Deliverables Checklist

At the end of Phase 6, the following should be complete:

### API Platform
- [ ] API Platform configured and working
- [ ] Include parameter (eager loading) working
- [ ] Error responses use standard format

### Endpoints
- [ ] All specialized endpoints implemented
- [ ] All endpoints support standard query parameters
- [ ] Error conditions documented and handled

### Batch Operations
- [ ] All 5 operations supported (create, update, delete, complete, reschedule)
- [ ] Sequential execution documented
- [ ] Partial success mode working
- [ ] Atomic mode working
- [ ] Validation errors formatted consistently

### Rate Limiting
- [ ] Rate limiting enforced per token
- [ ] Rate limiting enforced per IP (anonymous)
- [ ] Scope documented clearly

### Authentication
- [ ] Token generation documented
- [ ] Token expiry documented
- [ ] Token revocation documented

### Headers
- [ ] All standard headers documented
- [ ] Request IDs on all responses
- [ ] CORS configured

### Documentation
- [ ] OpenAPI 3.0 documentation complete
- [ ] All schemas defined
- [ ] Request bodies documented
- [ ] OpenAPI decorator customizes output
- [ ] Swagger UI accessible
- [ ] Redoc accessible

### AI Agent Documentation
- [ ] Quickstart guide written
- [ ] Structured vs natural language guidance
- [ ] Confirmation patterns documented
- [ ] Use case examples provided
- [ ] Error codes explained

### Testing
- [ ] Comprehensive API tests passing
- [ ] Natural language parsing tested
- [ ] Rate limit reset tested
- [ ] Performance acceptable

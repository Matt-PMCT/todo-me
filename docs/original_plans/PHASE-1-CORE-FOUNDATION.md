# Phase 1: Core Foundation

## Overview
**Duration**: Week 1-2  
**Goal**: Establish the foundational infrastructure including Symfony project setup, database schema, authentication, basic CRUD operations, Redis integration, and core API infrastructure (response formatting, rate limiting, request tracking).

## Prerequisites
- Docker and Docker Compose installed
- PHP 8.2+ knowledge
- Familiarity with Symfony 7.x

---

## Sub-Phase 1.1: Project Initialization

### Objective
Set up the Symfony 7 project with Docker containerization.

### Tasks

- [ ] **1.1.1** Create Docker Compose configuration
  - PHP-FPM 8.2+ container with required extensions: `pdo_pgsql`, `redis`, `intl`, `mbstring`, `xml`, `curl`, `zip`, `gd`
  - PostgreSQL 15+ container
  - Redis 7+ container
  - Nginx container (for production-like setup)
  
- [ ] **1.1.2** Initialize Symfony 7 project
  ```bash
  composer create-project symfony/skeleton todo-app
  ```

- [ ] **1.1.3** Install core dependencies
  ```bash
  composer require symfony/orm-pack
  composer require symfony/security-bundle
  composer require api-platform/core
  composer require predis/predis symfony/cache
  composer require symfony/rate-limiter
  composer require symfony/monolog-bundle
  composer require --dev symfony/maker-bundle
  composer require --dev phpunit/phpunit
  ```

- [ ] **1.1.4** Configure environment files
  - Create `.env.local` template
  - Set up database connection string for PostgreSQL
  - Configure Redis connection

### Completion Criteria
- [ ] `docker-compose up` starts all containers successfully
- [ ] Symfony welcome page accessible at `http://localhost:8080`
- [ ] Database connection verified via `php bin/console doctrine:database:create`
- [ ] Redis connection verified

### Files to Create
```
docker/
├── docker-compose.yml
├── php/
│   └── Dockerfile
├── nginx/
│   └── default.conf
.env
.env.local.example
```

---

## Sub-Phase 1.2: Database Schema - Core Tables

### Objective
Create the database schema with all core tables and constraints.

### Tasks

- [ ] **1.2.1** Create User entity and migration
  ```php
  // src/Entity/User.php
  - id: BIGINT AUTO_INCREMENT PRIMARY KEY
  - username: VARCHAR(100) UNIQUE NOT NULL
  - email: VARCHAR(255) UNIQUE NOT NULL
  - password_hash: VARCHAR(255) NOT NULL
  - api_token: VARCHAR(64) UNIQUE (nullable)
  - settings: JSON DEFAULT '{}'
  - created_at: DATETIME NOT NULL
  - updated_at: DATETIME NOT NULL
  ```

- [ ] **1.2.2** Implement User settings with defaults
  ```php
  // User entity must provide default settings when absent
  
  public function getTimezone(): string
  {
      return $this->settings['timezone'] ?? 'UTC';
  }
  
  public function getDateFormat(): string
  {
      return $this->settings['date_format'] ?? 'MDY';
  }
  
  public function getStartOfWeek(): int
  {
      return $this->settings['start_of_week'] ?? 0; // 0=Sunday
  }
  
  // Convenience method to get settings with all defaults applied
  public function getSettingsWithDefaults(): array
  {
      return array_merge([
          'timezone' => 'UTC',
          'date_format' => 'MDY',
          'start_of_week' => 0,
      ], $this->settings ?? []);
  }
  ```

- [ ] **1.2.3** Create Project entity and migration
  ```php
  // src/Entity/Project.php
  - id: BIGINT AUTO_INCREMENT PRIMARY KEY
  - user_id: BIGINT NOT NULL (FK to users)
  - parent_id: BIGINT (FK to projects, nullable)
  - name: VARCHAR(255) NOT NULL
  - color: VARCHAR(7) DEFAULT '#808080'
  - icon: VARCHAR(50) (nullable)
  - position: INTEGER DEFAULT 0
  - is_archived: BOOLEAN DEFAULT FALSE
  - archived_at: DATETIME (nullable)
  - show_children_tasks: BOOLEAN DEFAULT TRUE
  - created_at: DATETIME NOT NULL
  - updated_at: DATETIME NOT NULL
  
  Constraints:
  - parent_id != id (valid_hierarchy check)
  - CASCADE DELETE on user_id
  - CASCADE DELETE on parent_id
  ```

- [ ] **1.2.4** Create Task entity and migration
  ```php
  // src/Entity/Task.php
  - id: BIGINT AUTO_INCREMENT PRIMARY KEY
  - user_id: BIGINT NOT NULL (FK to users)
  - project_id: BIGINT (FK to projects, nullable)
  - parent_task_id: BIGINT (FK to tasks, nullable)
  - original_task_id: BIGINT (FK to tasks, nullable)
  - title: VARCHAR(500) NOT NULL
  - description: TEXT (nullable)
  - status: VARCHAR(20) DEFAULT 'pending'
  - priority: INTEGER DEFAULT 0
  - due_date: DATETIME (nullable)
  - due_time: TIME (nullable)
  - is_recurring: BOOLEAN DEFAULT FALSE
  - recurrence_rule: TEXT (nullable)
  - recurrence_type: VARCHAR(10) (nullable)
  - recurrence_end_date: DATETIME (nullable)
  - position: INTEGER NOT NULL DEFAULT 0
  - search_vector: TSVECTOR (nullable)
  - created_at: DATETIME NOT NULL
  - updated_at: DATETIME NOT NULL
  - completed_at: DATETIME (nullable)
  
  Constraints:
  - status IN ('pending', 'in_progress', 'completed')
  - priority BETWEEN 0 AND 4
  - recurrence_type IN ('absolute', 'relative') OR NULL
  ```

- [ ] **1.2.5** Create Tag entity and migration
  ```php
  // src/Entity/Tag.php
  - id: BIGINT AUTO_INCREMENT PRIMARY KEY
  - user_id: BIGINT NOT NULL (FK to users)
  - name: VARCHAR(100) NOT NULL
  - color: VARCHAR(7) DEFAULT '#808080'
  - created_at: DATETIME NOT NULL
  
  Constraints:
  - UNIQUE (user_id, name)
  ```

- [ ] **1.2.6** Create TaskTag join table migration
  ```php
  // Many-to-Many relationship
  - task_id: BIGINT NOT NULL (FK to tasks)
  - tag_id: BIGINT NOT NULL (FK to tags)
  - PRIMARY KEY (task_id, tag_id)
  ```

- [ ] **1.2.7** Create database indexes
  ```sql
  -- Users
  CREATE INDEX idx_users_email ON users(email);
  
  -- Projects
  CREATE INDEX idx_projects_user ON projects(user_id);
  CREATE INDEX idx_projects_parent ON projects(parent_id);
  
  -- Tasks
  CREATE INDEX idx_tasks_user ON tasks(user_id);
  CREATE INDEX idx_tasks_project ON tasks(project_id);
  CREATE INDEX idx_tasks_due_date ON tasks(due_date);
  CREATE INDEX idx_tasks_status ON tasks(status);
  CREATE INDEX idx_tasks_parent ON tasks(parent_task_id);
  CREATE INDEX idx_tasks_original ON tasks(original_task_id);
  CREATE INDEX idx_tasks_search USING GIN ON tasks(search_vector);
  
  -- Tags
  CREATE INDEX idx_tags_user ON tags(user_id);
  
  -- Task Tags
  CREATE INDEX idx_task_tags_task ON task_tags(task_id);
  CREATE INDEX idx_task_tags_tag ON task_tags(tag_id);
  ```

- [ ] **1.2.8** Create PostgreSQL trigger for search_vector
  ```sql
  CREATE TRIGGER tasks_search_vector_update
  BEFORE INSERT OR UPDATE ON tasks
  FOR EACH ROW
  EXECUTE FUNCTION tsvector_update_trigger(
    search_vector, 'pg_catalog.english', title, description
  );
  ```

### Completion Criteria
- [ ] All migrations run successfully: `php bin/console doctrine:migrations:migrate`
- [ ] All indexes created and verified
- [ ] Foreign key constraints working (test cascade deletes)
- [ ] search_vector trigger functioning
- [ ] User::getSettingsWithDefaults() returns correct defaults

### Files to Create
```
src/Entity/
├── User.php
├── Project.php
├── Task.php
└── Tag.php

migrations/
├── Version20260101000001_CreateUsersTable.php
├── Version20260101000002_CreateProjectsTable.php
├── Version20260101000003_CreateTasksTable.php
├── Version20260101000004_CreateTagsTable.php
├── Version20260101000005_CreateTaskTagsTable.php
├── Version20260101000006_CreateIndexes.php
└── Version20260101000007_CreateSearchVectorTrigger.php
```

---

## Sub-Phase 1.3: User Authentication System

### Objective
Implement user registration, login, and API token authentication with rate limiting.

### Tasks

- [ ] **1.3.1** Configure Symfony Security
  ```yaml
  # config/packages/security.yaml
  - Configure password hasher (bcrypt)
  - Define user provider
  - Configure API token authenticator
  ```

- [ ] **1.3.2** Create UserRepository with authentication methods
  ```php
  // src/Repository/UserRepository.php
  - findByEmail(string $email): ?User
  - findByApiToken(string $token): ?User
  ```

- [ ] **1.3.3** Create API Token Authenticator
  ```php
  // src/Security/ApiTokenAuthenticator.php
  - Extract token from Authorization: Bearer {token}
  - Validate token against database (hashed comparison)
  - Return authenticated user or throw exception
  ```

- [ ] **1.3.4** Configure Rate Limiting for Authentication
  ```yaml
  # config/packages/rate_limiter.yaml
  framework:
    rate_limiter:
      # Login rate limiting - prevent brute force
      login:
        policy: 'sliding_window'
        limit: 5
        interval: '1 minute'
      
      # API rate limiting foundation - 1000 requests per hour per token
      api:
        policy: 'sliding_window'
        limit: 1000
        interval: '1 hour'
  ```

- [ ] **1.3.5** Create AuthController for token management
  ```php
  // src/Controller/Api/AuthController.php
  
  POST /api/v1/auth/token
  - Accepts: { "username": "...", "password": "..." }
  - Apply login rate limiter (5 attempts per minute per IP)
  - Returns: { "data": { "token": "...", "user": {...}, "expires_at": null } }
  - Error: 401 INVALID_CREDENTIALS
  - Error: 429 RATE_LIMIT_EXCEEDED (if too many attempts)
  
  POST /api/v1/auth/revoke
  - Requires: Bearer token
  - Revokes current token
  - Returns: 204 No Content
  ```

- [ ] **1.3.6** Create token generation utility
  ```php
  // src/Service/TokenGenerator.php
  - generateToken(): string (64 characters, cryptographically secure)
  - hashToken(string $token): string
  ```

- [ ] **1.3.7** Create UserService for registration
  ```php
  // src/Service/UserService.php
  - register(string $username, string $email, string $password): User
  - Validates unique username/email
  - Hashes password
  - Generates initial API token
  - Initializes settings with defaults
  ```

### Completion Criteria
- [ ] User can register via API
- [ ] User can obtain token via POST /api/v1/auth/token
- [ ] Protected endpoints reject requests without valid token
- [ ] Token can be revoked
- [ ] Login attempts rate limited (5/minute)
- [ ] Rate limit returns 429 with proper error format

### Files to Create
```
src/
├── Security/
│   └── ApiTokenAuthenticator.php
├── Service/
│   ├── UserService.php
│   └── TokenGenerator.php
├── Controller/Api/
│   └── AuthController.php
└── Repository/
    └── UserRepository.php

config/packages/security.yaml
config/packages/rate_limiter.yaml
```

---

## Sub-Phase 1.4: API Infrastructure (Request Tracking, Response Format, Rate Limiting)

### Objective
Implement core API infrastructure: request ID generation, consistent response/error formatting, rate limiting middleware, and logging foundation.

### Tasks

- [ ] **1.4.1** Create Request ID Middleware
  ```php
  // src/EventListener/RequestIdListener.php
  
  /**
   * Generates unique request_id for every API request.
   * Stores in request attributes for use in responses and logging.
   */
  class RequestIdListener implements EventSubscriberInterface
  {
      public function onKernelRequest(RequestEvent $event): void
      {
          $request = $event->getRequest();
          $requestId = $request->headers->get('X-Request-ID') 
              ?? Uuid::v4()->toRfc4122();
          $request->attributes->set('request_id', $requestId);
      }
      
      public function onKernelResponse(ResponseEvent $event): void
      {
          $requestId = $event->getRequest()->attributes->get('request_id');
          $event->getResponse()->headers->set('X-Request-ID', $requestId);
      }
  }
  ```

- [ ] **1.4.2** Create API Rate Limiting Subscriber
  ```php
  // src/EventListener/ApiRateLimitSubscriber.php
  
  /**
   * Applies rate limiting to all /api/ endpoints.
   * Uses token-based limiting (1000 requests/hour per token).
   * Adds X-RateLimit-* headers to all responses.
   */
  class ApiRateLimitSubscriber implements EventSubscriberInterface
  {
      public function onKernelRequest(RequestEvent $event): void
      {
          // Apply rate limiter based on API token
          // If limit exceeded, throw TooManyRequestsHttpException
      }
      
      public function onKernelResponse(ResponseEvent $event): void
      {
          // Add headers to response:
          // X-RateLimit-Limit: 1000
          // X-RateLimit-Remaining: 950
          // X-RateLimit-Reset: 1706025600 (Unix timestamp)
      }
  }
  ```

- [ ] **1.4.3** Create ResponseFormatter Service
  ```php
  // src/Service/ResponseFormatter.php
  
  /**
   * Formats all API responses consistently per spec.
   */
  class ResponseFormatter
  {
      public function success(
          mixed $data, 
          array $links = [], 
          ?array $meta = null,
          int $status = 200
      ): JsonResponse {
          $requestId = $this->requestStack->getCurrentRequest()
              ->attributes->get('request_id');
          
          return new JsonResponse([
              'data' => $data,
              'meta' => array_merge([
                  'timestamp' => (new \DateTimeImmutable())->format(\DATE_ATOM),
                  'request_id' => $requestId,
              ], $meta ?? []),
              'links' => $links,
          ], $status);
      }
      
      public function paginated(
          array $data,
          int $page,
          int $perPage,
          int $total,
          string $baseUrl
      ): JsonResponse {
          $totalPages = (int) ceil($total / $perPage);
          
          return $this->success($data, [
              'self' => $this->buildUrl($baseUrl, $page, $perPage),
              'first' => $this->buildUrl($baseUrl, 1, $perPage),
              'last' => $this->buildUrl($baseUrl, $totalPages, $perPage),
              'prev' => $page > 1 ? $this->buildUrl($baseUrl, $page - 1, $perPage) : null,
              'next' => $page < $totalPages ? $this->buildUrl($baseUrl, $page + 1, $perPage) : null,
          ], [
              'current_page' => $page,
              'per_page' => $perPage,
              'total' => $total,
              'total_pages' => $totalPages,
          ]);
      }
      
      public function created(mixed $data, string $location, array $links = []): JsonResponse
      {
          $response = $this->success($data, array_merge(['self' => $location], $links), null, 201);
          $response->headers->set('Location', $location);
          return $response;
      }
  }
  ```

- [ ] **1.4.4** Create API Exception Listener with Error Codes
  ```php
  // src/EventListener/ApiExceptionListener.php
  
  /**
   * Catches all exceptions and formats them consistently.
   * Uses error codes from the spec's error dictionary.
   */
  class ApiExceptionListener implements EventSubscriberInterface
  {
      // Error code constants matching spec
      public const ERROR_VALIDATION = 'VALIDATION_ERROR';
      public const ERROR_AUTH_REQUIRED = 'AUTHENTICATION_REQUIRED';
      public const ERROR_INVALID_TOKEN = 'INVALID_TOKEN';
      public const ERROR_INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
      public const ERROR_NOT_FOUND = 'RESOURCE_NOT_FOUND';
      public const ERROR_DUPLICATE = 'DUPLICATE_RESOURCE';
      public const ERROR_RATE_LIMIT = 'RATE_LIMIT_EXCEEDED';
      public const ERROR_PERMISSION = 'PERMISSION_DENIED';
      public const ERROR_INVALID_STATUS = 'INVALID_STATUS';
      public const ERROR_INVALID_PRIORITY = 'INVALID_PRIORITY';
      public const ERROR_INVALID_RECURRENCE = 'INVALID_RECURRENCE';
      public const ERROR_PROJECT_NOT_FOUND = 'PROJECT_NOT_FOUND';
      
      public function onKernelException(ExceptionEvent $event): void
      {
          $exception = $event->getThrowable();
          $requestId = $event->getRequest()->attributes->get('request_id');
          
          // Map exception to appropriate error code and HTTP status
          [$code, $message, $status, $details] = $this->mapException($exception);
          
          $response = new JsonResponse([
              'error' => [
                  'code' => $code,
                  'message' => $message,
                  'details' => $details,
              ],
              'meta' => [
                  'timestamp' => (new \DateTimeImmutable())->format(\DATE_ATOM),
                  'request_id' => $requestId,
              ],
          ], $status);
          
          $event->setResponse($response);
      }
  }
  ```

- [ ] **1.4.5** Configure Monolog for Request Logging
  ```yaml
  # config/packages/monolog.yaml
  monolog:
    handlers:
      main:
        type: stream
        path: "%kernel.logs_dir%/%kernel.environment%.log"
        level: debug
        channels: ["!event"]
      
      api:
        type: stream
        path: "%kernel.logs_dir%/api.log"
        level: info
        channels: ["api"]
        formatter: monolog.formatter.json
  
  services:
    monolog.formatter.json:
      class: Monolog\Formatter\JsonFormatter
  ```

- [ ] **1.4.6** Create API Logger Service
  ```php
  // src/Service/ApiLogger.php
  
  /**
   * Logs API requests with request_id for tracing.
   */
  class ApiLogger
  {
      public function logRequest(Request $request): void
      {
          $this->logger->info('API Request', [
              'request_id' => $request->attributes->get('request_id'),
              'method' => $request->getMethod(),
              'path' => $request->getPathInfo(),
              'user_id' => $this->security->getUser()?->getId(),
          ]);
      }
      
      public function logResponse(Request $request, Response $response): void
      {
          $this->logger->info('API Response', [
              'request_id' => $request->attributes->get('request_id'),
              'status' => $response->getStatusCode(),
              'duration_ms' => /* calculate */,
          ]);
      }
  }
  ```

### Completion Criteria
- [ ] Every API response includes `meta.request_id` and `meta.timestamp`
- [ ] X-Request-ID header present on all responses
- [ ] X-RateLimit-* headers present on all API responses
- [ ] 429 returned when rate limit exceeded with proper error format
- [ ] All error responses use defined error codes from spec
- [ ] API requests logged to separate api.log file with request_id
- [ ] Links generated correctly in responses

### Files to Create
```
src/
├── EventListener/
│   ├── RequestIdListener.php
│   ├── ApiRateLimitSubscriber.php
│   └── ApiExceptionListener.php
├── Service/
│   ├── ResponseFormatter.php
│   └── ApiLogger.php

config/packages/monolog.yaml
```

---

## Sub-Phase 1.5: Core Validation Helpers and Invariant Enforcement

### Objective
Create centralized validation helpers that enforce all AI-specific invariants from the architecture spec.

### Tasks

- [ ] **1.5.1** Create ValidationHelper Service
  ```php
  // src/Service/ValidationHelper.php
  
  /**
   * Central validation service for enforcing business invariants.
   * All TaskService and ProjectService methods MUST use these helpers.
   */
  class ValidationHelper
  {
      // Valid status values per spec
      public const VALID_STATUSES = ['pending', 'in_progress', 'completed'];
      
      // Valid priority range per spec  
      public const MIN_PRIORITY = 0;
      public const MAX_PRIORITY = 4;
      
      // Valid recurrence types
      public const VALID_RECURRENCE_TYPES = ['absolute', 'relative'];
      
      /**
       * Validates task status against allowed values.
       * @throws InvalidStatusException with code INVALID_STATUS
       */
      public function validateStatus(string $status): void
      {
          if (!in_array($status, self::VALID_STATUSES, true)) {
              throw new InvalidStatusException(
                  sprintf('Invalid status "%s". Allowed: %s', 
                      $status, 
                      implode(', ', self::VALID_STATUSES)
                  )
              );
          }
      }
      
      /**
       * Validates priority is within 0-4 range.
       * @throws InvalidPriorityException with code INVALID_PRIORITY
       */
      public function validatePriority(int $priority): void
      {
          if ($priority < self::MIN_PRIORITY || $priority > self::MAX_PRIORITY) {
              throw new InvalidPriorityException(
                  sprintf('Priority must be between %d and %d, got %d',
                      self::MIN_PRIORITY,
                      self::MAX_PRIORITY,
                      $priority
                  )
              );
          }
      }
      
      /**
       * Validates recurrence type if provided.
       * @throws InvalidRecurrenceException with code INVALID_RECURRENCE
       */
      public function validateRecurrenceType(?string $type): void
      {
          if ($type !== null && !in_array($type, self::VALID_RECURRENCE_TYPES, true)) {
              throw new InvalidRecurrenceException(
                  sprintf('Invalid recurrence type "%s". Allowed: %s',
                      $type,
                      implode(', ', self::VALID_RECURRENCE_TYPES)
                  )
              );
          }
      }
      
      /**
       * Validates that task and project belong to same user.
       * CRITICAL INVARIANT: task.user_id must equal project.user_id
       * @throws PermissionDeniedException with code PERMISSION_DENIED
       */
      public function validateTaskProjectOwnership(User $user, ?Project $project): void
      {
          if ($project !== null && $project->getUser()->getId() !== $user->getId()) {
              throw new PermissionDeniedException(
                  'Task cannot be assigned to a project owned by a different user'
              );
          }
      }
      
      /**
       * Validates user owns the resource.
       * @throws PermissionDeniedException with code PERMISSION_DENIED
       */
      public function validateOwnership(User $user, UserOwnedInterface $resource): void
      {
          if ($resource->getUser()->getId() !== $user->getId()) {
              throw new PermissionDeniedException(
                  'You do not have permission to access this resource'
              );
          }
      }
  }
  ```

- [ ] **1.5.2** Create Custom Exception Classes
  ```php
  // src/Exception/InvalidStatusException.php
  class InvalidStatusException extends DomainException
  {
      public function getErrorCode(): string
      {
          return 'INVALID_STATUS';
      }
  }
  
  // src/Exception/InvalidPriorityException.php
  class InvalidPriorityException extends DomainException
  {
      public function getErrorCode(): string
      {
          return 'INVALID_PRIORITY';
      }
  }
  
  // src/Exception/InvalidRecurrenceException.php
  class InvalidRecurrenceException extends DomainException
  {
      public function getErrorCode(): string
      {
          return 'INVALID_RECURRENCE';
      }
  }
  
  // src/Exception/PermissionDeniedException.php
  class PermissionDeniedException extends DomainException
  {
      public function getErrorCode(): string
      {
          return 'PERMISSION_DENIED';
      }
  }
  
  // src/Exception/ResourceNotFoundException.php
  class ResourceNotFoundException extends DomainException
  {
      public function getErrorCode(): string
      {
          return 'RESOURCE_NOT_FOUND';
      }
  }
  ```

- [ ] **1.5.3** Create UserOwnedInterface
  ```php
  // src/Entity/UserOwnedInterface.php
  
  /**
   * Interface for entities that belong to a user.
   * Used by ValidationHelper for ownership checks.
   */
  interface UserOwnedInterface
  {
      public function getUser(): User;
  }
  
  // Task, Project, Tag entities implement this interface
  ```

### Completion Criteria
- [ ] ValidationHelper created with all validation methods
- [ ] All custom exceptions created with correct error codes
- [ ] Task and Project entities implement UserOwnedInterface
- [ ] Validation methods throw appropriate exceptions
- [ ] ApiExceptionListener correctly maps custom exceptions to HTTP responses

### Files to Create
```
src/
├── Service/
│   └── ValidationHelper.php
├── Entity/
│   └── UserOwnedInterface.php
├── Exception/
│   ├── InvalidStatusException.php
│   ├── InvalidPriorityException.php
│   ├── InvalidRecurrenceException.php
│   ├── PermissionDeniedException.php
│   └── ResourceNotFoundException.php
```

---

## Sub-Phase 1.6: Basic Task CRUD Operations

### Objective
Implement basic Create, Read, Update, Delete operations for tasks with full invariant enforcement.

### Tasks

- [ ] **1.6.1** Create TaskRepository with basic queries
  ```php
  // src/Repository/TaskRepository.php
  - findByUser(User $user, int $page = 1, int $perPage = 20): Paginator
  - findByUserAndProject(User $user, Project $project): array
  - findOneByIdAndUser(int $id, User $user): ?Task
  - countByUser(User $user): int
  
  // Pagination defaults per spec:
  // page default: 1
  // per_page default: 20, max: 100
  ```

- [ ] **1.6.2** Create TaskService with Invariant Enforcement
  ```php
  // src/Service/TaskService.php
  
  /**
   * Task business logic with full invariant enforcement.
   * CRITICAL: Every method must validate ownership and constraints.
   */
  class TaskService
  {
      public function __construct(
          private TaskRepository $taskRepository,
          private ProjectRepository $projectRepository,
          private ValidationHelper $validator,
          private UndoService $undoService,
      ) {}
      
      /**
       * Creates a new task for the user.
       * 
       * Invariants enforced:
       * - User ownership (task.user_id = authenticated user)
       * - Project ownership (project.user_id = task.user_id if project set)
       * - Valid status (pending, in_progress, completed)
       * - Valid priority (0-4)
       * 
       * @throws InvalidStatusException
       * @throws InvalidPriorityException  
       * @throws PermissionDeniedException
       * @throws ResourceNotFoundException if project_id invalid
       */
      public function create(User $user, array $data): Task
      {
          // Validate status if provided
          if (isset($data['status'])) {
              $this->validator->validateStatus($data['status']);
          }
          
          // Validate priority if provided
          if (isset($data['priority'])) {
              $this->validator->validatePriority($data['priority']);
          }
          
          // Validate project ownership if project_id provided
          $project = null;
          if (isset($data['project_id'])) {
              $project = $this->projectRepository->find($data['project_id']);
              if (!$project) {
                  throw new ResourceNotFoundException('Project not found');
              }
              // CRITICAL: Enforce user_id invariant
              $this->validator->validateTaskProjectOwnership($user, $project);
          }
          
          $task = new Task();
          $task->setUser($user);
          $task->setTitle($data['title']);
          // ... set other fields
          
          $this->taskRepository->save($task);
          return $task;
      }
      
      /**
       * Updates an existing task.
       * 
       * Invariants enforced:
       * - Ownership check (user must own task)
       * - Project ownership (if changing project)
       * - Valid status and priority
       */
      public function update(User $user, Task $task, array $data): Task
      {
          // CRITICAL: Verify ownership first
          $this->validator->validateOwnership($user, $task);
          
          if (isset($data['status'])) {
              $this->validator->validateStatus($data['status']);
          }
          
          if (isset($data['priority'])) {
              $this->validator->validatePriority($data['priority']);
          }
          
          if (isset($data['project_id'])) {
              $project = $this->projectRepository->find($data['project_id']);
              if (!$project) {
                  throw new ResourceNotFoundException('Project not found');
              }
              $this->validator->validateTaskProjectOwnership($user, $project);
          }
          
          // Apply updates...
          return $task;
      }
      
      /**
       * Updates task status with undo support.
       */
      public function updateStatus(User $user, Task $task, string $status): TaskStatusResult
      {
          $this->validator->validateOwnership($user, $task);
          $this->validator->validateStatus($status);
          
          $previousStatus = $task->getStatus();
          $task->setStatus($status);
          
          if ($status === 'completed') {
              $task->setCompletedAt(new \DateTimeImmutable());
          } else {
              $task->setCompletedAt(null);
          }
          
          $this->taskRepository->save($task);
          
          // Create undo token
          $undoToken = $this->undoService->createToken('task_complete', $user->getId(), [
              'task_id' => $task->getId(),
              'previous_status' => $previousStatus,
              'previous_completed_at' => null,
          ]);
          
          return new TaskStatusResult($task, $undoToken);
      }
      
      /**
       * Deletes a task.
       */
      public function delete(User $user, Task $task): UndoToken
      {
          $this->validator->validateOwnership($user, $task);
          
          // Store task data for potential undo
          $taskData = $this->serializeForUndo($task);
          
          $this->taskRepository->remove($task);
          
          return $this->undoService->createToken('task_delete', $user->getId(), [
              'task_data' => $taskData,
          ]);
      }
  }
  ```

- [ ] **1.6.3** Create TaskController with API endpoints
  ```php
  // src/Controller/Api/TaskController.php
  
  GET /api/v1/tasks
  - Returns paginated list of user's tasks
  - Query params:
    - page: int (default: 1)
    - per_page: int (default: 20, max: 100)
  - Uses ResponseFormatter::paginated()
  
  GET /api/v1/tasks/{id}
  - Returns single task if owned by user
  - 404 RESOURCE_NOT_FOUND if not found or not owned
  
  POST /api/v1/tasks
  - Creates new task
  - Returns 201 with Location header
  - Validates all invariants via TaskService
  
  PUT /api/v1/tasks/{id}
  - Full replacement
  - All required fields must be provided
  
  PATCH /api/v1/tasks/{id}
  - Partial update
  - Only provided fields updated
  
  DELETE /api/v1/tasks/{id}
  - Deletes task
  - Returns 204
  - Response includes undo_token
  
  PATCH /api/v1/tasks/{id}/status
  - Quick status update
  - Accepts: { "status": "completed" }
  - Response includes undo_token
  ```

- [ ] **1.6.4** Create Pagination Helper
  ```php
  // src/Service/PaginationHelper.php
  
  class PaginationHelper
  {
      public const DEFAULT_PAGE = 1;
      public const DEFAULT_PER_PAGE = 20;
      public const MAX_PER_PAGE = 100;
      
      public function getPage(Request $request): int
      {
          $page = (int) $request->query->get('page', self::DEFAULT_PAGE);
          return max(1, $page);
      }
      
      public function getPerPage(Request $request): int
      {
          $perPage = (int) $request->query->get('per_page', self::DEFAULT_PER_PAGE);
          return min(max(1, $perPage), self::MAX_PER_PAGE);
      }
  }
  ```

### Completion Criteria
- [ ] All CRUD endpoints functional
- [ ] User isolation enforced (users only see own tasks)
- [ ] Validation errors return 422 with correct error codes
- [ ] 404 RESOURCE_NOT_FOUND returned for non-existent or unauthorized resources
- [ ] 403 PERMISSION_DENIED returned for ownership violations
- [ ] Consistent response format for all endpoints
- [ ] Pagination defaults: page=1, per_page=20, max=100
- [ ] All responses include undo_token where applicable

### Files to Create
```
src/
├── Controller/Api/
│   └── TaskController.php
├── Service/
│   ├── TaskService.php
│   └── PaginationHelper.php
├── Repository/
│   └── TaskRepository.php
└── DTO/
    ├── TaskCreateDTO.php
    ├── TaskUpdateDTO.php
    └── TaskStatusResult.php
```

---

## Sub-Phase 1.7: Basic Project CRUD Operations

### Objective
Implement basic CRUD operations for projects with archive-by-default deletion semantics.

### Tasks

- [ ] **1.7.1** Create ProjectRepository
  ```php
  // src/Repository/ProjectRepository.php
  - findByUser(User $user, bool $includeArchived = false): array
  - findOneByIdAndUser(int $id, User $user): ?Project
  - findByNameAndUser(string $name, User $user): ?Project
  ```

- [ ] **1.7.2** Create ProjectService with Invariant Enforcement
  ```php
  // src/Service/ProjectService.php
  
  class ProjectService
  {
      /**
       * Creates a new project.
       * 
       * Invariants enforced:
       * - User ownership
       * - Parent project ownership (if parent_id set)
       */
      public function create(User $user, array $data): Project
      {
          // Validate parent ownership if provided
          if (isset($data['parent_id'])) {
              $parent = $this->projectRepository->find($data['parent_id']);
              if (!$parent) {
                  throw new ResourceNotFoundException('Parent project not found');
              }
              $this->validator->validateOwnership($user, $parent);
          }
          
          $project = new Project();
          $project->setUser($user);
          // ... set other fields
          
          return $project;
      }
      
      /**
       * Updates a project.
       */
      public function update(User $user, Project $project, array $data): Project
      {
          $this->validator->validateOwnership($user, $project);
          // ... apply updates
          return $project;
      }
      
      /**
       * Archives a project (DEFAULT delete behavior per spec).
       * Sets is_archived = true and archived_at timestamp.
       */
      public function archive(User $user, Project $project): Project
      {
          $this->validator->validateOwnership($user, $project);
          
          $project->setIsArchived(true);
          $project->setArchivedAt(new \DateTimeImmutable());
          
          $this->projectRepository->save($project);
          
          return $project;
      }
      
      /**
       * Unarchives a project.
       */
      public function unarchive(User $user, Project $project): Project
      {
          $this->validator->validateOwnership($user, $project);
          
          $project->setIsArchived(false);
          $project->setArchivedAt(null);
          
          $this->projectRepository->save($project);
          
          return $project;
      }
      
      /**
       * Hard deletes a project (special admin action).
       * WARNING: This cascades to all tasks. Use archive() for normal deletion.
       */
      public function hardDelete(User $user, Project $project): void
      {
          $this->validator->validateOwnership($user, $project);
          $this->projectRepository->remove($project);
      }
  }
  ```

- [ ] **1.7.3** Create ProjectController
  ```php
  // src/Controller/Api/ProjectController.php
  
  GET /api/v1/projects
  - Returns list of user's projects
  - Excludes archived by default
  - Query param: include_archived=true
  
  GET /api/v1/projects/{id}
  - Returns single project
  
  POST /api/v1/projects
  - Creates new project
  
  PUT /api/v1/projects/{id}
  PATCH /api/v1/projects/{id}
  
  DELETE /api/v1/projects/{id}
  - ARCHIVES project by default (NOT hard delete)
  - Returns archived project data with undo_token
  - Per spec: "Projects are archived, not deleted"
  
  PATCH /api/v1/projects/{id}/archive
  - Explicitly archives project
  
  PATCH /api/v1/projects/{id}/unarchive
  - Restores archived project
  ```

### Completion Criteria
- [ ] All project CRUD endpoints functional
- [ ] Projects properly linked to users
- [ ] DELETE endpoint archives by default (NOT hard delete)
- [ ] Archive/unarchive working with timestamps
- [ ] include_archived filter working
- [ ] Ownership validated on all operations

### Files to Create
```
src/
├── Controller/Api/
│   └── ProjectController.php
├── Service/
│   └── ProjectService.php
├── Repository/
│   └── ProjectRepository.php
└── DTO/
    ├── ProjectCreateDTO.php
    └── ProjectUpdateDTO.php
```

---

## Sub-Phase 1.8: Redis Setup and Basic Undo Infrastructure

### Objective
Set up Redis connection and create the foundation for the undo system.

### Tasks

- [ ] **1.8.1** Configure Redis connection
  ```yaml
  # config/packages/cache.yaml
  framework:
    cache:
      app: cache.adapter.redis
      default_redis_provider: '%env(REDIS_URL)%'
  ```

- [ ] **1.8.2** Create RedisService wrapper
  ```php
  // src/Service/RedisService.php
  - set(string $key, mixed $value, int $ttl): void
  - get(string $key): mixed
  - delete(string $key): void
  - exists(string $key): bool
  ```

- [ ] **1.8.3** Create UndoToken value object
  ```php
  // src/ValueObject/UndoToken.php
  - token: string
  - operation: string
  - userId: int
  - data: array
  - expiresAt: DateTimeImmutable
  ```

- [ ] **1.8.4** Create UndoService foundation
  ```php
  // src/Service/UndoService.php
  
  Constants:
  - TTL = 60 seconds
  - KEY_PREFIX = 'undo:'
  
  Methods:
  - createToken(string $operation, int $userId, array $data): UndoToken
    * Generates unique token
    * Stores in Redis with 60s TTL
    * Key format: undo:{token}
    * Returns token with expiry timestamp
    
  - getToken(string $token): ?UndoToken
    * Retrieves from Redis
    * Returns null if expired/not found
    
  - executeUndo(string $token, int $userId): array
    * Validates token belongs to user
    * Placeholder for actual undo logic (Phase 7)
    * Deletes token after use
  ```

### Completion Criteria
- [ ] Redis connection working
- [ ] Undo tokens can be created and retrieved
- [ ] Tokens expire after 60 seconds
- [ ] Token key format: undo:{token}
- [ ] Task status updates include undo_token in response

### Files to Create
```
src/
├── Service/
│   ├── RedisService.php
│   └── UndoService.php
└── ValueObject/
    └── UndoToken.php

config/packages/cache.yaml
```

---

## Sub-Phase 1.9: Simple Task List View (UI)

### Objective
Create a basic web UI to display tasks.

### Tasks

- [ ] **1.9.1** Set up Twig and Tailwind CSS
  ```bash
  composer require symfony/twig-bundle
  composer require symfony/asset
  npm init -y
  npm install tailwindcss postcss autoprefixer
  npx tailwindcss init
  ```

- [ ] **1.9.2** Create base layout template
  ```twig
  {# templates/base.html.twig #}
  - HTML5 structure
  - Tailwind CSS include
  - Main content block
  - JavaScript block
  ```

- [ ] **1.9.3** Create TaskListController for web views
  ```php
  // src/Controller/Web/TaskListController.php
  
  GET /
  - Redirects to /tasks
  
  GET /tasks
  - Displays all tasks for logged-in user
  - Groups by project
  ```

- [ ] **1.9.4** Create task list template
  ```twig
  {# templates/task/list.html.twig #}
  - Sidebar with project list
  - Main area with task list
  - Basic task display (checkbox, title, due date, priority)
  - No interactivity yet (view only)
  ```

- [ ] **1.9.5** Create web authentication (session-based)
  ```php
  // src/Controller/Web/SecurityController.php
  
  GET /login
  POST /login
  GET /logout
  GET /register
  POST /register
  ```

### Completion Criteria
- [ ] Users can register and log in via web
- [ ] Task list displays after login
- [ ] Tasks grouped by project visually
- [ ] Basic styling with Tailwind

### Files to Create
```
templates/
├── base.html.twig
├── security/
│   ├── login.html.twig
│   └── register.html.twig
└── task/
    └── list.html.twig

src/Controller/Web/
├── TaskListController.php
└── SecurityController.php

assets/
├── styles/
│   └── app.css
└── app.js

tailwind.config.js
postcss.config.js
```

---

## Phase 1 Integration Tests

### Required Tests

```php
// tests/Functional/Api/AuthApiTest.php
- testTokenGeneration()
- testInvalidCredentials()
- testInvalidCredentialsRateLimited() // Verify 429 after 5 attempts
- testTokenRevocation()
- testProtectedEndpointWithoutToken()
- testProtectedEndpointWithValidToken()

// tests/Functional/Api/TaskApiTest.php
- testCreateTask()
- testCreateTaskValidationErrors()
- testCreateTaskInvalidStatus() // Verify INVALID_STATUS error code
- testCreateTaskInvalidPriority() // Verify INVALID_PRIORITY error code
- testCreateTaskWithOtherUsersProject() // Verify PERMISSION_DENIED
- testGetTaskList()
- testGetTaskListPaginationDefaults() // Verify page=1, per_page=20
- testGetTaskListMaxPerPage() // Verify per_page capped at 100
- testGetSingleTask()
- testUpdateTask()
- testPartialUpdateTask()
- testDeleteTask()
- testUserIsolation()
- testResponseIncludesRequestId() // Verify meta.request_id
- testResponseIncludesTimestamp() // Verify meta.timestamp

// tests/Functional/Api/ProjectApiTest.php
- testCreateProject()
- testGetProjectList()
- testDeleteProjectArchivesByDefault() // Verify DELETE archives
- testArchiveProject()
- testUnarchiveProject()
- testGetProjectListExcludesArchived() // Verify default excludes archived

// tests/Functional/Api/RateLimitApiTest.php
- testRateLimitHeadersPresent() // Verify X-RateLimit-* headers
- testRateLimitExceeded() // Verify 429 response

// tests/Unit/Service/TaskServiceTest.php
- testCreateWithValidData()
- testCreateWithInvalidStatus()
- testCreateWithInvalidPriority()
- testCreateWithOtherUsersProjectThrowsException()
- testUpdateStatus()
- testUserOwnershipValidation()

// tests/Unit/Service/ValidationHelperTest.php
- testValidateStatusWithValidValues()
- testValidateStatusWithInvalidValueThrows()
- testValidatePriorityWithValidRange()
- testValidatePriorityOutOfRangeThrows()
- testValidateTaskProjectOwnershipSameUser()
- testValidateTaskProjectOwnershipDifferentUserThrows()

// tests/Unit/Service/UndoServiceTest.php
- testCreateToken()
- testTokenExpiration()
- testTokenRetrieval()

// tests/Unit/Entity/UserTest.php
- testGetTimezoneReturnsDefault()
- testGetDateFormatReturnsDefault()
- testGetStartOfWeekReturnsDefault()
- testGetSettingsWithDefaultsMergesCorrectly()
```

---

## Deliverables Checklist

At the end of Phase 1, the following should be complete:

### Infrastructure
- [ ] Docker development environment working
- [ ] All database tables created with proper constraints
- [ ] Redis connected and working

### Authentication
- [ ] User registration and authentication working
- [ ] API token authentication working
- [ ] Login rate limiting (5 attempts/minute)

### API Infrastructure
- [ ] Request ID middleware generating X-Request-ID
- [ ] All responses include meta.request_id and meta.timestamp
- [ ] API rate limiting (1000 requests/hour) with X-RateLimit-* headers
- [ ] 429 responses when rate limited
- [ ] All error responses use defined error codes from spec
- [ ] API requests logged with request_id

### Validation & Invariants
- [ ] ValidationHelper enforcing all business rules
- [ ] Custom exceptions with proper error codes
- [ ] Ownership validated on all resource access

### Task CRUD
- [ ] Task CRUD API endpoints functional
- [ ] Pagination defaults: page=1, per_page=20, max=100
- [ ] Undo tokens returned for status/delete operations

### Project CRUD
- [ ] Project CRUD API endpoints functional
- [ ] DELETE archives by default (not hard delete)
- [ ] Archive/unarchive working

### User Settings
- [ ] User::getSettingsWithDefaults() returning correct defaults
- [ ] timezone default: 'UTC'
- [ ] date_format default: 'MDY'
- [ ] start_of_week default: 0 (Sunday)

### UI
- [ ] Basic task list UI viewable

### Testing
- [ ] All Phase 1 tests passing
- [ ] Code coverage > 80% for services

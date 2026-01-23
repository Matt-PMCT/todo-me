# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**todo-me** is a Symfony 7-based self-hosted todo/task management application with REST API and web UI. Uses PostgreSQL 15, Redis 7, and Nginx 1.24 in Docker containers.

**Tech Stack:** PHP 8.4-FPM, Symfony 7.0, Doctrine ORM 3.0, PostgreSQL 15, Redis 7, Twig + Alpine.js + Tailwind CSS

## Commands

### Docker & Setup
```bash
docker-compose -f docker/docker-compose.yml up -d      # Start services
docker-compose -f docker/docker-compose.yml exec php bash  # Enter PHP container
composer install                                        # Install deps (runs migrations)
php bin/console cache:clear                            # Clear cache
```

### Testing
```bash
php bin/phpunit                           # All tests (450 tests)
php bin/phpunit tests/Unit                # Unit tests only
php bin/phpunit tests/Functional          # Functional API tests only
php bin/phpunit --filter=TaskServiceTest  # Run single test class
php bin/phpunit --filter=testCreateTask   # Run single test method
```

### Database
```bash
php bin/console doctrine:migrations:migrate    # Run migrations
php bin/console doctrine:migrations:generate   # Create new migration
php bin/console doctrine:migrations:rollback   # Revert last migration
```

## Architecture

### Directory Structure
```
src/
├── Controller/Api/     # REST endpoints (Auth, Task, Project controllers)
├── Controller/Web/     # Web UI controllers (Security, TaskList, Home)
├── Service/            # Business logic layer (TaskService, ProjectService, etc.)
├── Repository/         # Doctrine repositories with query methods
├── Entity/             # Doctrine entities (User, Task, Project, Tag) - all use UUIDs
├── DTO/                # Request/Response DTOs with validation constraints
├── Exception/          # Custom exceptions (ValidationException, EntityNotFoundException, etc.)
├── Security/           # ApiTokenAuthenticator for Bearer/X-API-Key auth
├── EventListener/      # Exception handling, request ID tracking
├── EventSubscriber/    # Rate limiting subscriber
└── Interface/          # UserOwnedInterface for multi-tenant ownership
```

### Key Patterns

**Multi-tenant Ownership:** All user-scoped entities implement `UserOwnedInterface`. The `OwnershipChecker` service validates ownership on all mutations.

**Service Layer:** Controllers are thin, delegating to Service classes for business logic. Services handle validation, repository access, and entity updates.

**DTO Pattern:** Request DTOs have Symfony Validator constraints. Response DTOs handle serialization. Use static `fromArray()` for hydration.

**Undo System:** UndoService creates Redis-stored tokens (60s TTL) before mutations. UndoToken is an immutable value object.

**Exception Handling:** Custom exceptions extend HttpException. ApiExceptionListener catches and formats all API exceptions consistently.

### API Response Format
All API endpoints return:
```json
{
  "success": true/false,
  "data": {...},
  "error": {"code": "ERROR_CODE", "message": "...", "details": {...}},
  "meta": {"requestId": "uuid", "timestamp": "ISO-8601"}
}
```

### Rate Limiting
- Anonymous: 1000 req/hour
- Authenticated: 1000 req/hour
- Login: 5 attempts/min
- Test env has 100× higher limits

### Database Schema
- All entities use UUID primary keys
- Task has full-text search vector
- Composite indexes on common filter combinations (owner, status, priority, due_date, position)
- Relationships: User → Projects/Tasks/Tags (orphanRemoval), Project → Tasks, Task ↔ Tags (many-to-many)

## Configuration

- **Security:** `config/packages/security.yaml` - API firewall is stateless, web firewall uses sessions
- **Rate limiting:** `config/packages/rate_limiter.yaml`
- **Services:** `config/services.yaml` - autowiring with explicit configs where needed
- **Docker:** `docker/docker-compose.yml` - PHP, Nginx, PostgreSQL, Redis services
- **Environment:** `.env.local.example` as template, `.env.test` for test database

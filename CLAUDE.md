# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**todo-me** is a Symfony 7-based self-hosted todo/task management application with REST API and web UI. Uses PostgreSQL 15, Redis 7, and Nginx 1.24 in Docker containers.

**Tech Stack:** PHP 8.4-FPM, Symfony 7.0, Doctrine ORM 3.0, PostgreSQL 15, Redis 7, Twig + Alpine.js + Tailwind CSS

**MCP Server:** Python 3.12 + FastMCP in `mcp-server/` — 16 tools exposing the REST API to AI assistants via [Model Context Protocol](https://modelcontextprotocol.io). See `mcp-server/README.md` for setup and `docs/MCP-SERVER-ARCHITECTURE.md` for architecture.

## Commands

### Docker & Setup
```bash
docker compose -f docker/docker-compose.yml up -d           # Start services
docker compose -f docker/docker-compose.yml exec php bash   # Enter PHP container
composer install                                             # Install deps (in container)
php bin/console cache:clear                                  # Clear cache (in container)
php bin/console asset-map:compile --env=prod                 # Compile assets (REQUIRED for production)
```

### Production Deployment
**CRITICAL:** Always use the deploy script or include `asset-map:compile`. Skipping asset compilation after adding/changing JS files breaks the entire frontend (Alpine.js won't load).
```bash
# Preferred: use deploy script on production server
cd ~/todo-me && git pull && ./scripts/deploy/update-web.sh --quick   # code-only deploy

# Full deploy (no --quick): backs up, pulls fresh base images, rebuilds. Use monthly for
# security patches, or whenever Dockerfiles / image tags change.
cd ~/todo-me && ./scripts/deploy/update-web.sh
```
Set `APP_URL` in the server environment to have the script print your real URL at the end.

**Fresh server / file ownership:** the project is bind-mounted into the PHP container and PHP-FPM workers run as the `www` user, whose uid/gid is baked in at build time (`WWW_UID`/`WWW_GID`, default 1000; `update-web.sh` sets them to the deploy user's uid/gid). Always deploy as the same non-root user that owns the checkout. `update-web.sh` creates `var/cache` and `var/log` and repairs ownership of `var/`, `vendor/` and build output on every run (idempotent), so a fresh clone works without manual `chown`. A 500 with "Permission denied" for `var/log` means the workers' uid doesn't match the owner of `var/`: run a full deploy. If you expose the MCP server remotely, the host reverse proxy also needs the `/todo-me/mcp/` location block from `docs/MCP-SERVER-ARCHITECTURE.md` (section 6).

**Container hardening:** all services run with `no-new-privileges` and `cap_drop: [ALL]` (each adds back only what it needs, with the reason commented in `docker/docker-compose.yml`), and `mcp` runs as a non-root user. When adding a service or upgrading an image, start with no capabilities, read `docker logs` for "Operation not permitted", and add the minimum back; test on a fresh volume as well as an existing one, because entrypoints that chown data only fail on foreign-owned volumes. Web port exposure is chosen once per server: the first interactive `update-web.sh` run asks whether port 8083 should be reachable from the LAN or only from this machine (reverse proxy on the same host) and saves `WEB_BIND` to `docker/.env` (gitignored); later deploys respect it, and non-interactive runs never change it. To change it later, edit `docker/.env` and run `docker compose -f docker/docker-compose.yml -f docker/docker-compose.prod.yml up -d web`. A multi-stage/runtime-only PHP image is deliberately not used: the PHP image doubles as the tooling image (`composer`, `bin/console`) that deploys run against the bind-mounted project.

**Image freshness & rollback:** every deploy runs `scripts/image-status.sh`: it saves the running image IDs to `backups/images-before-<timestamp>.txt` (rollback: `docker tag <image-id> <image-name>`, then `up -d --no-build`; don't `docker image prune` until the release is verified) and warns when any image is older than `STALE_DAYS` (default 30) — a `--quick` deploy never rebuilds, so act on that warning with a full deploy. Optional weekly notification (cron mails any output; no output = fresh):
```bash
0 8 * * 1 cd ~/todo-me && ./scripts/image-status.sh --check --quiet
```
The script only reports; it never deploys.

### Testing

**IMPORTANT:** Tests MUST be run inside the Docker PHP container. The database and Redis services are only accessible from within the Docker network.

```bash
# Enter the PHP container first
docker compose -f docker/docker-compose.yml exec php bash

# Then run tests inside the container:
vendor/bin/phpunit                              # All tests
vendor/bin/phpunit tests/Unit                   # Unit tests only
vendor/bin/phpunit tests/Functional             # Functional tests only
vendor/bin/phpunit --filter=TaskServiceTest     # Single test class
vendor/bin/phpunit --filter=testCreateTask      # Single test method
```

**One-liner (without entering container):**
```bash
docker compose -f docker/docker-compose.yml exec php vendor/bin/phpunit tests/Unit
```

**Output to file (REQUIRED for full test suite):**
Always output full test suite results to a file to avoid losing output due to terminal truncation:
```bash
# Full test suite - ALWAYS use file output
vendor/bin/phpunit --testdox-text var/test-results.txt

# Then read the results
cat var/test-results.txt

# For CI/CD pipelines, also generate JUnit XML
vendor/bin/phpunit --testdox-text var/test-results.txt --log-junit var/test-results.xml
```
**IMPORTANT:** Never run the full test suite without `--testdox-text var/test-results.txt` or similar file output. Terminal output can be truncated and you'll lose important failure information.

### Security Scanning (CI)
`.github/workflows/security.yml` runs on PRs touching dependencies/Docker files and weekly (Mon 06:00 UTC): `composer audit --locked`, `pip-audit` for `mcp-server`, and Trivy scans of the built PHP/MCP images and the nginx/redis/postgres images from `docker/docker-compose.yml` (HIGH/CRITICAL with a fix available). To resolve a finding: upgrade the dependency, or rebuild with `build --pull`, first; only then add the CVE to `.trivyignore` with a justification and expiry date comment.

### Dependabot
`.github/dependabot.yml` opens weekly PRs for Composer, pip (`mcp-server`), Docker base images, compose images and GitHub Actions. Never auto-merge: merge one group at a time after CI and the security workflow are green, and do a full deploy (`./scripts/deploy/update-web.sh`, no `--quick`) after Composer or Docker updates. Compose image tags intentionally float (`nginx:stable-alpine`, `redis:7-alpine`, `postgres:15-alpine`); major bumps (Postgres, Redis, `mcp` 2.x, PHP) need a deliberate plan and are not drive-by merges.

### MCP Server Tests
```bash
cd mcp-server
.venv/bin/python -m pytest tests/ -v    # Run all MCP tests (from host)
```

### Database
```bash
php bin/console doctrine:migrations:migrate    # Run migrations
php bin/console doctrine:migrations:generate   # Create new migration
php bin/console doctrine:migrations:migrate prev  # Revert last migration
```

### Code Style & Static Analysis (CRITICAL - CI WILL FAIL WITHOUT THIS)

**IMPORTANT:** Before committing ANY PHP code changes, you MUST run PHP-CS-Fixer and PHPStan. The GitHub CI runs `composer lint` (which runs both `composer cs-check` and `composer phpstan`) and will fail if either has errors.

```bash
# ALWAYS run these before committing PHP changes:
docker compose -f docker/docker-compose.yml exec php composer cs-fix
docker compose -f docker/docker-compose.yml exec php composer phpstan

# Or run both checks at once (what CI runs):
docker compose -f docker/docker-compose.yml exec php composer lint
```

**PHPStan Baseline:** This project uses a PHPStan baseline (`phpstan-baseline.neon`) to track known errors. If you add or modify test files that access nullable return types without null checks (common in test assertions), you must regenerate the baseline:
```bash
docker compose -f docker/docker-compose.yml exec php php vendor/bin/phpstan analyse --memory-limit=512M --generate-baseline phpstan-baseline.neon
```

**Workflow:** Write code → Run `composer cs-fix` → Run `composer phpstan` → Run tests → Commit

## Architecture

### Directory Structure
```
src/
├── Controller/Api/     # REST endpoints (Auth, Task, Project controllers)
├── Controller/Web/     # Web UI controllers (Security, TaskList, Home)
├── Service/            # Business logic layer (TaskService, ProjectService, etc.)
├── Repository/         # Doctrine repositories with query methods
├── Entity/             # Doctrine entities (User, Task, Project, Tag, ApiToken, Notification, SavedFilter, etc.) - all use UUIDs
├── DTO/                # Request/Response DTOs with validation constraints
├── Exception/          # Custom exceptions (ValidationException, EntityNotFoundException, etc.)
├── Security/           # SessionApiAuthenticator + ApiTokenAuthenticator for dual auth
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

### Authentication Architecture

**Web UI (Browser):**
- Session-based auth via cookies (HttpOnly, Secure, SameSite=Strict)
- CSRF token required for mutations (X-CSRF-Token header)
- No API tokens exposed to JavaScript - uses `window.api` helper in `base.html.twig`
- `SessionApiAuthenticator` validates session and CSRF for `/api/` routes

**External API:**
- Token auth via `Authorization: Bearer` or `X-API-Key` header
- Tokens stored as SHA256 hashes (never plaintext)
- Token TTL: 48 hours (configurable via `API_TOKEN_TTL_HOURS`)
- `ApiTokenAuthenticator` handles token validation

**Dual Authenticator Flow:**
1. `SessionApiAuthenticator` checks first (for web UI requests without token headers)
2. Falls back to `ApiTokenAuthenticator` if session auth fails or token headers present

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

### Real-Time Sync (Polling)
- SyncService tracks mutations in Redis (version counter + change log per user)
- Client polls `GET /api/v1/sync/poll?lastVersion=N` at a user-configurable interval (default: 30s)
- User can configure polling interval in Settings (10s, 15s, 30s, 60s, or disabled)
- Origin tab filtering via X-Tab-Id header prevents double updates
- Visibility API pauses polling on hidden tabs
- Changes applied to DOM via Alpine.js reactive data updates

### Database Schema
- All entities use UUID primary keys
- Task has full-text search vector
- Composite indexes on common filter combinations (owner, status, priority, due_date, position)
- Relationships: User → Projects/Tasks/Tags (orphanRemoval), Project → Tasks, Task ↔ Tags (many-to-many)

## Coding Guidelines

Don't add error handling, fallbacks, or validation for scenarios that can't happen. Trust internal code and framework guarantees. Only validate at system boundaries (user input, external APIs). Don't use backwards-compatibility shims when you can just change the code. The right amount of complexity is the minimum needed for the current task. Reuse existing abstractions where possible and follow the DRY principle.

## Testing Guidelines

Only test application-specific code. Do NOT write tests for:
- Framework functions (Symfony rate limiters, validators, serializers, etc.)
- Third-party library functionality (Doctrine ORM operations, Redis clients, etc.)
- Infrastructure concerns already guaranteed by the framework

Trust that frameworks and libraries work correctly. Focus tests on business logic, custom services, and application-specific behavior.

## UI Design System

All frontend work must follow the UI Design System documented in `docs/UI-DESIGN-SYSTEM.md`. Key principles:

**Styling:**
- Use Tailwind CSS utility classes directly (avoid `@apply`)
- Follow the established color tokens: teal-600 primary, semantic status colors
- Typography: system font stack, text-sm default, font-semibold for buttons
- **Dark Mode:** Always include `dark:` variants for new styles (dark mode is class-based)
- Spacing: 4px base unit (Tailwind scale), p-4 card padding, gap-4 standard spacing

**Components:**
- Task cards: white bg, shadow, rounded-lg, hover:shadow-md transition
- Buttons: rounded-md, text-sm font-semibold, focus ring states
- Forms: ring-1 borders, focus:ring-2 focus:ring-teal-600
- Modals: fixed overlay, bg-gray-500/75 backdrop, max-w-lg panel

**Interaction:**
- Alpine.js for state (x-data, @click, x-show, x-transition)
- Transitions: duration-100 for dropdowns, duration-300 for modals
- Toast auto-dismiss: 5 seconds

**Accessibility:**
- Minimum 4.5:1 color contrast for text
- All interactive elements keyboard accessible
- sr-only labels for icon-only buttons
- aria-labelledby for modals

See `docs/UI-PHASE-MODIFICATIONS.md` for phase-specific UI requirements.

## Configuration

- **Security:** `config/packages/security.yaml` - API firewall supports session+token auth, web firewall uses sessions
- **Rate limiting:** `config/packages/rate_limiter.yaml`
- **Services:** `config/services.yaml` - autowiring with explicit configs where needed
- **Docker:** `docker/docker-compose.yml` - PHP, Nginx, PostgreSQL, Redis services
- **Environment:** `.env.local.example` as template, `.env.test` for test database

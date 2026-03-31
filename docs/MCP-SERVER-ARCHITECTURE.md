# MCP Server Architecture Document

**Issue:** #124 - MCP Server Functionality
**Date:** 2026-03-31

## 1. Production Server Infrastructure

The MCP server will be deployed alongside the existing Todo-Me application on `pmct.work` (192.168.4.4).

### Available Resources

| Resource | Details |
|----------|---------|
| **CPU** | 14-core Intel i9-9900K @ 3.60GHz |
| **RAM** | 47GB total, ~44GB available |
| **Disk** | 323GB free of 754GB |
| **OS** | Ubuntu 24.04 |
| **Python** | 3.12.3 (system), pip 24.0 |
| **Node.js** | Not installed |
| **Docker** | 29.1.3, Compose v5.0.0 |
| **SSL** | Cloudflare origin certs at `/etc/ssl/cloudflare/pmct.work.{pem,key}` |
| **Nginx** | Systemd service, reverse proxy with path-based routing |

### Current Port Allocations

| Port | Service |
|------|---------|
| 443 | Nginx (HTTPS termination) |
| 8082 | Better Trails web |
| 8083 | Todo-Me web (todo-nginx) |
| 8080 | Hillshade tiles |
| 8000 | Better Trails API (systemd) |
| 3000 | Martin tile server |
| 5433 | Better Trails Postgres |
| 5434 | Tiles Postgres |
| 6379 | Better Trails Redis |

**Available for MCP server:** 8084 (recommended), 8085, or any unused port.

### Todo-Me Docker Network

The Todo-Me stack runs on `docker_todo-network` (172.22.0.0/16):

| Container | IP | Service |
|-----------|-----|---------|
| todo-php | 172.22.0.4 | PHP 8.4-FPM |
| todo-nginx | 172.22.0.5 | Nginx (port 8083) |
| todo-postgres | 172.22.0.3 | PostgreSQL 15 |
| todo-redis | 172.22.0.2 | Redis 7 |

### Python Tooling

Python 3.12.3 and pip are available on the host. No `uv`, `pipx`, or `poetry` installed. The Better Trails API already uses Python/FastAPI as a systemd service, establishing a precedent for Python services on this server.

## 2. Recommended Tech Stack

**Python with FastMCP** is the recommended approach:

- Python 3.12 is already available on the server
- The Python MCP SDK (`mcp`) with FastMCP provides the simplest implementation path
- Better Trails API already runs Python on this server (operational precedent)
- Single-file server is feasible for the initial scope
- Supports both stdio and Streamable HTTP transports natively

### Dependencies (minimal)

```
mcp>=1.0.0          # MCP SDK with FastMCP
httpx>=0.27.0       # Async HTTP client for Todo-Me API calls
python-dotenv>=1.0  # Environment configuration
```

## 3. Architecture

### Transport Modes

The MCP server must support two transport modes as outlined in the issue:

#### stdio (Local Use)
- For Claude Desktop, Claude Code, and other local MCP clients
- No network exposure — the client spawns the server as a subprocess
- Authentication: API key passed via environment variable
- No additional infrastructure needed

#### Streamable HTTP (Remote Use)
- For claude.ai custom connectors and other remote clients
- Runs as a persistent service behind Nginx
- Authentication: Bearer token in Authorization header
- Requires Nginx location block and optional systemd service

### Network Architecture

```
                          Internet
                             |
                        [Cloudflare]
                             |
                     [Nginx :443 SSL]
                      /      |       \
                     /       |        \
         /todo-me/*    /todo-me/mcp/*   / (Better Trails)
              |              |               |
        [todo-nginx]   [MCP Server]    [BT web]
          :8083          :8084           :8082
              |              |
        [todo-php]     calls Todo-Me API via
          :9000        docker_todo-network
              |        (http://web:80)
        [todo-postgres]
        [todo-redis]
```

For stdio transport (local use), the MCP server runs on the user's machine and calls the API over the internet via `https://pmct.work/todo-me`.

The MCP server communicates with the Todo-Me REST API over HTTP, not directly with the database. This provides:

1. **Separation of concerns** — MCP server is a thin translation layer
2. **Security** — no direct database access, all authorization enforced by the existing API
3. **Maintainability** — API changes automatically propagate to MCP tools
4. **Existing auth** — reuses the named API token system (`tm_` prefixed tokens)

### Deployment Options

**Option A: Standalone Docker container (recommended)**

Add a new service to `docker/docker-compose.yml` or a separate compose file:

```yaml
services:
  mcp:
    build:
      context: ../mcp-server
      dockerfile: Dockerfile
    container_name: todo-mcp
    restart: unless-stopped
    ports:
      - "127.0.0.1:8084:8000"
    environment:
      - TODO_API_URL=http://web:80
      - MCP_TRANSPORT=http
      - MCP_HOST=0.0.0.0
      - MCP_PORT=8000
    networks:
      - todo-network
    depends_on:
      - web
```

Advantages: isolated, uses internal Docker network to reach the API via the compose service name `web` (no host networking needed), consistent with existing deployment patterns.

> **Note:** Use the compose service name (`web`) in `TODO_API_URL`, not the `container_name` (`todo-nginx`). Service names are the standard DNS mechanism in Docker Compose and remain stable across container rebuilds.

**Option B: Systemd service on host**

Similar to how `better-trails-api` runs. Uses the host Python directly.

Advantages: simpler setup, no Docker build step. Disadvantage: less isolated, depends on host Python.

**Recommendation:** Option A for remote transport (production). For stdio-only local use, the MCP server is just a Python script that users run on their own machines — no server deployment needed.

## 4. MCP Tool Design

Based on the issue requirements, mapped to existing API endpoints:

### Core Tools (Issue Scope)

| MCP Tool | API Endpoint | Method | Notes |
|----------|-------------|--------|-------|
| `list_todos` | `/api/v1/tasks` | GET | Filters: status, priority (1-5), due dates, project_ids, tag_ids, search |
| `get_todo` | `/api/v1/tasks/{id}` | GET | Returns full task with subtask counts |
| `create_todo` | `/api/v1/tasks` | POST | Support natural language via `?parse_natural_language=true` |
| `update_todo` | `/api/v1/tasks/{id}` | PATCH | Title, description, priority (1-5), due_date, project_id, tag_ids |
| `complete_todo` | `/api/v1/tasks/{id}/status` | PATCH | Body: `{"status": "completed"}` (generic status endpoint) |
| `delete_todo` | `/api/v1/tasks/{id}` | DELETE | Hard delete (60s undo token returned via Redis) |
| `list_tags` | `/api/v1/tags` | GET | Required for tag-aware task creation/updates |

**7 core tools** (the 6 from issue #124 plus `list_tags`, which is needed for tag-aware task creation/updates).

**Task statuses:** `pending`, `in_progress`, `completed`
**Priority:** Integer 1-5 (1 = lowest, 5 = urgent). API uses `priority_min`/`priority_max` for filtering.

> **Note:** The API's `GET /api/v1/tasks` defaults to `exclude_subtasks=true`. Subtasks are hidden unless explicitly requested. Use `list_subtasks` for direct access to a task's subtasks.

### Extended Tools

Added beyond the initial MVP to support context discovery, smart views, undo, subtasks, and rescheduling:

| MCP Tool | API Endpoint | Method | Notes |
|----------|-------------|--------|-------|
| `undo` | `/api/v1/undo` | POST | Execute undo using token from mutation tools (60s TTL, single-use) |
| `get_today` | `/api/v1/tasks/today` | GET | Today + overdue daily view |
| `get_overdue` | `/api/v1/tasks/overdue` | GET | Overdue tasks only |
| `get_upcoming` | `/api/v1/tasks/upcoming` | GET | Tasks due in next N days (1-90, default 7) |
| `search` | `/api/v1/search` | GET | Global full-text search across tasks, projects, tags |
| `list_projects` | `/api/v1/projects` | GET | Project list with task counts for context discovery |
| `reschedule_todo` | `/api/v1/tasks/{id}/reschedule` | PATCH | Natural language ("next Monday") or ISO date rescheduling |
| `list_subtasks` | `/api/v1/tasks/{id}/subtasks` | GET | Subtasks of a parent task |
| `create_subtask` | `/api/v1/tasks/{id}/subtasks` | POST | Create subtask (max 1 level nesting, inherits parent project) |

**16 total tools** (7 core + 9 extended).

### Undo Tokens

The API returns `undoToken` values on `update_todo`, `delete_todo`, `complete_todo`, and `reschedule_todo` operations. These tokens expire after 60 seconds and allow single-use reversal. The `undo` tool executes the reversal via `POST /api/v1/undo` with `{"token": "..."}`.

### Pagination

The API paginates with `page` and `limit` parameters (default limit: 20, max: 100). MCP tools should expose both parameters so the LLM can page through larger result sets. Response metadata includes `total`, `page`, `limit`, and `totalPages` for navigation.

### Potential Future Tools

| MCP Tool | API Endpoint | Notes |
|----------|-------------|-------|
| `get_completed` | `/api/v1/tasks/completed` | Recently completed tasks |
| `get_no_date` | `/api/v1/tasks/no-date` | Tasks without due dates |
| `batch_update` | `/api/v1/tasks/batch` | Bulk operations (max 100, optional atomic mode) |
| `get_activity` | `/api/v1/activity` | User activity log / audit trail |

### Tool Schema Example

```python
@mcp.tool()
async def list_todos(
    status: str | None = None,           # "pending", "in_progress", "completed"
    priority_min: int | None = None,     # 1-5 (1 = lowest)
    priority_max: int | None = None,     # 1-5 (5 = urgent)
    project_ids: str | None = None,      # Comma-separated UUIDs
    tag_ids: str | None = None,          # Comma-separated UUIDs
    tag_mode: str | None = None,         # "AND" or "OR" (default: "OR")
    due_before: str | None = None,       # ISO-8601 date
    due_after: str | None = None,        # ISO-8601 date
    search: str | None = None,           # Full-text search query
    sort: str | None = None,             # "due_date", "priority", "created_at", "updated_at", "completed_at", "title", "position"
    direction: str | None = None,        # "asc" or "desc"
    page: int = 1,                       # Page number
    limit: int = 20,                     # Max 100
) -> dict:
    """List todo items with optional filters.

    Returns a dict with 'items' (list of tasks) and 'meta' (pagination info
    with total, page, limit, totalPages).
    """
    params = {k: v for k, v in locals().items() if v is not None}
    return await api.get("/api/v1/tasks", params=params)
```

## 5. Authentication Flow

### For stdio Transport (Local)

```
User's machine:
  Claude Desktop / Claude Code
       |
       | spawns subprocess
       v
  MCP Server (stdio)
       |
       | HTTP + Bearer token (from env var)
       v
  Todo-Me API (pmct.work or localhost)
```

The user creates a **named API token** (prefixed with `tm_`) via the Todo-Me web UI or `POST /api/v1/users/me/tokens` and places it in their MCP client config as `TODO_API_KEY`. Named tokens support scopes (default: `['*']` for full access) and optional expiration dates.

**Important:** The MCP server should only use named API tokens (`tm_` prefix), not login tokens. Named tokens are designed for long-lived programmatic access with configurable (or no) expiration and scope restrictions. Login tokens (`User.apiToken`) have a fixed 48-hour TTL and are intended for short-lived API access after login — not suitable for persistent MCP integrations.

### For Streamable HTTP Transport (Remote)

```
claude.ai / Remote Client
       |
       | HTTPS + Bearer token (in Authorization header)
       v
  Nginx (SSL termination)
       |
       | HTTP
       v
  MCP Server (:8084)
       |
       | validates token against Todo-Me API
       | then proxies tool calls with same token
       v
  Todo-Me API (internal network)
```

The MCP server acts as a **pass-through** for authentication. It forwards the user's API token to the Todo-Me API on every request. This means:

- No separate auth system for MCP
- Token scopes, expiration, and rate limits all handled by the existing API
- The MCP server validates tokens by calling `GET /api/v1/auth/me` on first use
- Per-user isolation is automatic (the API enforces ownership)

## 6. Nginx Configuration (Remote Transport)

Add to the existing `pmct.work` Nginx config. This block must appear alongside the existing `^~ /todo-me/` location block. The longer prefix (`/todo-me/mcp/`) takes priority automatically under Nginx's `^~` matching rules.

> **Prerequisite:** The `api_limit` rate limit zone must be defined in the `http` block. The production Nginx config already defines `limit_req_zone $binary_remote_addr zone=api_limit:10m rate=10r/s;` in the `http` block, so no additional zone definition is needed.

```nginx
# MCP Server (Streamable HTTP)
location ^~ /todo-me/mcp/ {
    limit_req zone=api_limit burst=20 nodelay;

    proxy_pass http://127.0.0.1:8084/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection "";

    # SSE support (for legacy SSE transport if needed)
    proxy_set_header Accept $http_accept;
    proxy_buffering off;
    proxy_cache off;

    # Longer timeout for MCP sessions
    proxy_read_timeout 300s;
    proxy_send_timeout 300s;
}
```

## 7. Security

### Requirements from Issue (all addressed)

| Requirement | Implementation |
|-------------|---------------|
| Auth on all remote transports | Bearer token required, validated against Todo-Me API |
| OAuth 2.1 recommendation | Not implemented — see rationale below |
| API key auth fallback | Named API tokens (`tm_` prefix) already exist |
| Per-user credential scoping | Enforced by Todo-Me API ownership model |
| Input validation | All tool arguments validated before API calls |
| Parameterized queries | MCP server never touches the database directly |
| Sanitized error responses | Generic error messages, no stack traces |
| HTTPS for remote | Nginx handles SSL termination |
| Localhost binding by default | Docker binds to `127.0.0.1:8084` |
| Dockerfile for isolation | Dedicated container on `todo-network` |
| Rate limiting | Nginx `api_limit` zone (defined in server `http` block) + Todo-Me's own rate limits |
| Audit logging | Log all tool invocations with timestamp and user identity |
| Pinned dependencies | `requirements.txt` with pinned versions |
| Minimal dependencies | `mcp`, `httpx`, `python-dotenv` only |

### OAuth 2.1 Decision

Issue #124 recommends OAuth 2.1 for remote transports. This is **not implemented** because:

- Todo-Me is a **single-user, self-hosted** application — there is no multi-user or shared deployment scenario
- The existing named API token system (`tm_` prefix) provides equivalent security for this use case: scoped permissions, expiration, and per-user isolation
- OAuth adds significant complexity (authorization server, token exchange flow, refresh tokens) with no benefit for a single-user setup
- If multi-user support is added in the future, OAuth should be revisited at that point

### Security Boundaries

```
MCP Server responsibilities:
  - Validate tool argument types and ranges
  - Forward auth tokens (never store or log them)
  - Sanitize API error responses before returning to LLM
  - Log tool invocations for audit

Todo-Me API responsibilities (already implemented):
  - Token validation and expiration
  - User ownership enforcement
  - Rate limiting
  - CSRF protection (not needed for token auth)
  - Input validation via DTO constraints
  - SQL injection prevention via Doctrine ORM
```

## 8. API Client Design

### Base Path Handling

The `TODO_API_URL` environment variable includes the full base URL to the Todo-Me application. All API paths are appended relative to this:

| Scenario | `TODO_API_URL` | Resulting request |
|----------|---------------|-------------------|
| Docker (internal) | `http://web:80` | `http://web:80/api/v1/tasks` |
| stdio (remote) | `https://pmct.work/todo-me` | `https://pmct.work/todo-me/api/v1/tasks` |
| stdio (local dev) | `http://localhost:8083` | `http://localhost:8083/api/v1/tasks` |

The API client must join `TODO_API_URL` with the endpoint path (e.g., `/api/v1/tasks`), ensuring no double slashes or missing path segments.

### Response Unwrapping

The Todo-Me API returns all responses in a standard envelope:

```json
{
  "success": true,
  "data": { ... },
  "error": { "code": "ERROR_CODE", "message": "...", "details": { ... } },
  "meta": { "requestId": "uuid", "timestamp": "ISO-8601" }
}
```

The API client should:
- Check `success` is `true`, otherwise raise an error
- Return `data` to the MCP tool (which passes it to the LLM)
- For list endpoints, `data` contains `{"items": [...], "meta": {"total": N, "page": N, ...}}`

### Error Handling

| API Status | Meaning | MCP Server Action |
|------------|---------|-------------------|
| 401 | Token expired or invalid | Return MCP error: "Authentication failed. Check your API key." |
| 403 | Insufficient scope | Return MCP error: "Permission denied for this operation." |
| 404 | Resource not found | Return MCP error: "Task/project not found with ID: {id}" |
| 422 | Validation error | Return MCP error with validation details from `error.details` |
| 429 | Rate limited | Return MCP error: "Rate limit exceeded. Try again shortly." |
| 500 | Server error | Return MCP error: "Todo-Me API error." Do not expose internal details. |

### Transport Mode Selection

The `__main__.py` entry point selects transport via the `MCP_TRANSPORT` environment variable:

| Value | Behavior |
|-------|----------|
| `stdio` (default) | Runs in stdio mode for Claude Desktop / Claude Code |
| `http` | Runs Streamable HTTP server on `MCP_HOST`:`MCP_PORT` |

Example:
```python
transport = os.environ.get("MCP_TRANSPORT", "stdio")
if transport == "http":
    mcp.run(transport="streamable-http", host=host, port=port)
else:
    mcp.run(transport="stdio")
```

### Environment Variables

Full set for `.env.example`:

```env
# Required
TODO_API_URL=https://pmct.work/todo-me   # Base URL to Todo-Me (include path prefix if any)
TODO_API_KEY=tm_xxxxxxxxxxxx              # Named API token (tm_ prefix)

# Optional (defaults shown)
MCP_TRANSPORT=stdio                       # "stdio" or "http"
MCP_HOST=127.0.0.1                        # Bind address (http transport only)
MCP_PORT=8000                             # Listen port (http transport only)
MCP_LOG_LEVEL=INFO                        # Logging level
```

## 9. Operational Concerns

### Logging

The MCP server should log to **stdout** in JSON format (standard for Docker containers). Logs are accessible via `docker logs todo-mcp`. Each tool invocation should log:
- Timestamp
- Tool name
- Caller identity (user ID from the forwarded token, obtained via `GET /api/v1/auth/me`)
- Duration
- Success/failure

### Health Check

The MCP server (http transport) should expose a `GET /health` endpoint that:
1. Returns `200` if the server is running
2. Optionally verifies connectivity to the Todo-Me API

Docker healthcheck stanza:
```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost:8000/health"]
  interval: 30s
  timeout: 5s
  retries: 3
```

### Updating / Redeployment

After code changes to the MCP server:
```bash
cd ~/todo-me
git pull
docker compose -f docker/docker-compose.yml build mcp
docker compose -f docker/docker-compose.yml up -d mcp
```

The MCP server is **stateless** — it holds no data and proxies everything to the Todo-Me API. There is nothing to back up or migrate. Container restarts and rebuilds are safe at any time.

### CORS

For the Streamable HTTP transport, CORS is not expected to be needed. MCP clients like claude.ai custom connectors make server-to-server requests, not browser-to-server. If a browser-based client is added in the future, CORS headers should be added to the Nginx location block.

## 10. Directory Structure

```
mcp-server/
├── Dockerfile
├── requirements.txt
├── pyproject.toml
├── README.md
├── .env.example
├── todo_me_mcp/
│   ├── __init__.py
│   ├── __main__.py          # Entry point (python -m todo_me_mcp)
│   ├── server.py            # FastMCP server definition + tool registration
│   ├── api_client.py        # httpx wrapper for Todo-Me API
│   └── config.py            # Environment variable handling
└── tests/
    ├── __init__.py
    └── test_tools.py
```

## 11. Client Configuration Examples

### Claude Desktop (stdio)

```json
{
  "mcpServers": {
    "todo-me": {
      "command": "python",
      "args": ["-m", "todo_me_mcp"],
      "env": {
        "TODO_API_URL": "https://pmct.work/todo-me",
        "TODO_API_KEY": "tm_xxxxxxxxxxxx"
      }
    }
  }
}
```

### Claude Code (stdio)

In `.claude/settings.json` or project-level MCP config:

```json
{
  "mcpServers": {
    "todo-me": {
      "command": "python",
      "args": ["-m", "todo_me_mcp"],
      "env": {
        "TODO_API_URL": "https://pmct.work/todo-me",
        "TODO_API_KEY": "tm_xxxxxxxxxxxx"
      }
    }
  }
}
```

### claude.ai Custom Connector (Remote HTTP)

URL: `https://pmct.work/todo-me/mcp/`
Authentication: Bearer token (Todo-Me API key)

## 12. Implementation Plan

### Phase 1: MVP (stdio transport + core tools)
1. Create `mcp-server/` directory with project structure
2. Implement `api_client.py` with httpx for Todo-Me API communication (see Section 8 for base path and error handling)
3. Implement the 7 core MCP tools (6 from issue #124 + `list_tags`)
4. Add input validation for all tool arguments
5. Test locally with Claude Desktop or Claude Code
6. Write README with setup instructions

### Phase 2: Remote Transport
1. Add Streamable HTTP transport support
2. Create Dockerfile
3. Add to `docker-compose.yml` as `todo-mcp` service
4. Configure Nginx location block
5. Add audit logging
6. Test with claude.ai custom connector

### Phase 3: Hardening
1. Add error sanitization (strip internal details from API errors)
2. Add structured logging with caller identity
3. Pin all dependency versions
4. Add health check endpoint
5. Write deployment documentation

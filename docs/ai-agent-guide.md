# AI Agent Integration Guide

This guide explains how to integrate AI agents (like Claude, GPT, or custom agents) with the todo-me API.

## Overview

todo-me provides a REST API designed for both human users and AI agents. The API supports:

- Natural language task creation
- Batch operations for efficiency
- Structured JSON responses
- Undo capabilities for safe operations
- Comprehensive error messages

## Authentication

### Getting an API Token

AI agents should use long-lived API tokens. Register or login to obtain a token:

```bash
# Register a new account for your agent
curl -X POST https://your-domain.com/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email": "agent@example.com", "password": "SecurePassword123"}'
```

Response:
```json
{
  "success": true,
  "data": {
    "user": {"id": "uuid", "email": "agent@example.com"},
    "token": "your-api-token",
    "expiresAt": "2026-01-26T12:00:00+00:00"
  }
}
```

### Token Refresh

Tokens expire after 48 hours. Refresh before expiration:

```bash
curl -X POST https://your-domain.com/api/v1/auth/refresh \
  -H "Authorization: Bearer YOUR_EXPIRED_TOKEN"
```

### Using the Token

Include the token in all requests:

```bash
# Bearer token (recommended)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://your-domain.com/api/v1/tasks

# Or X-API-Key header
curl -H "X-API-Key: YOUR_TOKEN" \
  https://your-domain.com/api/v1/tasks
```

## Common Workflows

### 1. Creating Tasks from Natural Language

The most powerful feature for AI agents is natural language task creation:

```bash
curl -X POST "https://your-domain.com/api/v1/tasks?parse_natural_language=true" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"input_text": "Buy groceries tomorrow at 5pm #shopping high priority"}'
```

Response:
```json
{
  "success": true,
  "data": {
    "task": {
      "id": "uuid",
      "title": "Buy groceries",
      "dueDate": "2026-01-25T17:00:00+00:00",
      "priority": 4,
      "tags": [{"name": "shopping"}]
    },
    "parsed": {
      "title": "Buy groceries",
      "dueDate": "2026-01-25T17:00:00+00:00",
      "priority": 4,
      "tags": ["shopping"]
    }
  }
}
```

### 2. Preview Before Creating

To show users what will be created without actually creating it:

```bash
curl -X POST https://your-domain.com/api/v1/parse \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"text": "Call dentist next Monday at 2pm"}'
```

### 3. Batch Operations

For efficiency, use batch operations to update multiple tasks:

```bash
curl -X POST https://your-domain.com/api/v1/batch \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "operations": [
      {"action": "complete", "taskId": "uuid1"},
      {"action": "complete", "taskId": "uuid2"},
      {"action": "update", "taskId": "uuid3", "data": {"priority": 5}}
    ]
  }'
```

### 4. Smart Task Listing

Get tasks relevant to the current context:

```bash
# Today's tasks (due today + overdue)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-domain.com/api/v1/tasks/today"

# Upcoming tasks (next 7 days)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-domain.com/api/v1/tasks/upcoming?days=7"

# Search tasks
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-domain.com/api/v1/search?q=meeting"

# Filter by project and tags
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-domain.com/api/v1/tasks?project_ids=uuid&tag_ids=uuid1,uuid2"
```

### 5. Undo Operations

Many operations return an undo token valid for 60 seconds:

```bash
# Delete a task
curl -X DELETE "https://your-domain.com/api/v1/tasks/uuid" \
  -H "Authorization: Bearer YOUR_TOKEN"
# Response includes undoToken

# Undo if needed
curl -X POST "https://your-domain.com/api/v1/undo/UNDO_TOKEN" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Best Practices

### 1. Handle Errors Gracefully

All errors return a consistent format:

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Title is required",
    "details": {"fields": {"title": ["This field is required"]}}
  }
}
```

Common error codes:
- `VALIDATION_ERROR` - Invalid input data
- `NOT_FOUND` - Resource doesn't exist
- `UNAUTHORIZED` - Invalid or expired token
- `RATE_LIMIT_EXCEEDED` - Too many requests

### 2. Use Pagination

Always handle pagination for list endpoints:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://your-domain.com/api/v1/tasks?page=1&limit=50"
```

Response includes pagination meta:
```json
{
  "data": {
    "tasks": [...],
    "pagination": {
      "page": 1,
      "limit": 50,
      "total": 150,
      "totalPages": 3,
      "hasNextPage": true,
      "hasPreviousPage": false
    }
  }
}
```

### 3. Rate Limiting

The API has rate limits:
- 1000 requests/hour for authenticated requests
- 5 login attempts/minute

Check rate limit headers:
- `X-RateLimit-Remaining`: Requests remaining
- `X-RateLimit-Reset`: Reset timestamp

### 4. Caching

For agents making frequent requests, cache:
- Project list (changes infrequently)
- Tag list (changes infrequently)
- Task list (cache for short periods, invalidate on mutations)

### 5. Efficient Updates

Use PATCH for partial updates instead of PUT:

```bash
# Only update priority (efficient)
curl -X PATCH "https://your-domain.com/api/v1/tasks/uuid" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"priority": 5}'
```

## MCP Server Configuration

For Claude Desktop and other MCP-compatible clients, see the example configuration in `docs/examples/mcp-config.json`.

## Example Implementations

- Python: `docs/examples/python-client.py`
- curl: `docs/examples/curl-examples.sh`

## API Reference

Full API documentation with all endpoints, parameters, and response schemas is available at:

- Swagger UI: `https://your-domain.com/api/v1/docs`
- OpenAPI JSON: `https://your-domain.com/api/v1/docs.json`

## Support

For issues or questions:
1. Check the API documentation
2. Review error responses carefully
3. Open an issue on GitHub

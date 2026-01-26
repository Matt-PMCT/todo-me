# API Quickstart Guide

This guide will get you making API requests in minutes.

## Base URL

All API endpoints are prefixed with:
```
https://your-domain.com/api/v1
```

## Authentication

> **Note**: The web UI uses session-based authentication automatically. API tokens are only needed for external integrations, scripts, mobile apps, or third-party applications.

The API uses token-based authentication. Include your token in every request using one of these methods:

### Option 1: Bearer Token (Recommended)
```bash
curl -H "Authorization: Bearer YOUR_API_TOKEN" \
  https://your-domain.com/api/v1/tasks
```

### Option 2: X-API-Key Header
```bash
curl -H "X-API-Key: YOUR_API_TOKEN" \
  https://your-domain.com/api/v1/tasks
```

## Getting Your API Token

### Register a New User
```bash
curl -X POST https://your-domain.com/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email": "you@example.com", "password": "SecurePass123"}'
```

Response:
```json
{
  "success": true,
  "data": {
    "user": {"id": "uuid", "email": "you@example.com"},
    "token": "your-api-token",
    "expiresAt": "2026-01-26T10:30:00+00:00"
  }
}
```

### Login with Existing Account
```bash
curl -X POST https://your-domain.com/api/v1/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email": "you@example.com", "password": "SecurePass123"}'
```

## Your First API Calls

### Create a Task
```bash
curl -X POST https://your-domain.com/api/v1/tasks \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title": "My first task", "priority": 3}'
```

### List All Tasks
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://your-domain.com/api/v1/tasks
```

### Get Today's Tasks
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://your-domain.com/api/v1/tasks/today
```

### Complete a Task
```bash
curl -X PUT https://your-domain.com/api/v1/tasks/TASK_ID/status \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "completed"}'
```

## Response Format

All responses follow this structure:

```json
{
  "success": true,
  "data": { ... },
  "error": null,
  "meta": {
    "requestId": "uuid",
    "timestamp": "2026-01-24T10:30:00+00:00"
  }
}
```

Error responses:
```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": {"title": "Title is required"}
  },
  "meta": { ... }
}
```

## Rate Limits

- **Authenticated requests**: 1000 per hour
- **Login attempts**: 5 per minute (per email)
- **Registration**: 10 per hour (per IP)

Rate limit headers in responses:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Requests remaining
- `X-RateLimit-Reset`: Unix timestamp when limit resets

## Next Steps

- [Structured vs Natural Language](structured-vs-natural.md) - When to use each approach
- [Common Use Cases](use-cases.md) - Examples for typical workflows
- [Filter Reference](filters.md) - Advanced task filtering
- [Error Reference](errors.md) - Complete error code list
- [API Reference](reference.md) - Full endpoint documentation

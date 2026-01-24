# Error Code Reference

All API errors follow a consistent format:

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable message",
    "details": { ... }
  },
  "meta": {
    "requestId": "uuid",
    "timestamp": "2026-01-24T10:30:00+00:00"
  }
}
```

## Error Codes

### Authentication Errors (401)

| Code | Message | Cause |
|------|---------|-------|
| `AUTHENTICATION_REQUIRED` | Not authenticated | Missing or invalid auth header |
| `INVALID_CREDENTIALS` | Invalid credentials | Wrong email or password |
| `INVALID_TOKEN` | Invalid API token | Token doesn't exist |
| `TOKEN_EXPIRED` | API token has expired | Token past expiration date |
| `TOKEN_REQUIRED` | API token not provided | No Authorization header |
| `TOKEN_REFRESH_EXPIRED` | Token expired too long ago | Refresh window (7 days) exceeded |

**Resolution**: Re-authenticate with `POST /auth/token` or register a new account.

### Authorization Errors (403)

| Code | Message | Cause |
|------|---------|-------|
| `FORBIDDEN` | Access denied | Accessing another user's resource |
| `NOT_OWNER` | You do not own this resource | Attempting to modify non-owned entity |

**Resolution**: Ensure you're accessing your own resources.

### Not Found Errors (404)

| Code | Message | Cause |
|------|---------|-------|
| `NOT_FOUND` | Resource not found | Entity doesn't exist |
| `TASK_NOT_FOUND` | Task not found | Task ID doesn't exist |
| `PROJECT_NOT_FOUND` | Project not found | Project ID doesn't exist |
| `TAG_NOT_FOUND` | Tag not found | Tag ID doesn't exist |

**Resolution**: Verify the resource ID is correct.

### Validation Errors (422)

| Code | Message | Details |
|------|---------|---------|
| `VALIDATION_ERROR` | Validation failed | `errors` object with field-level messages |
| `INVALID_STATUS` | Invalid status value | Status must be pending/in_progress/completed |
| `INVALID_PRIORITY` | Invalid priority value | Priority must be 1-5 |
| `INVALID_JSON` | Invalid JSON body | Request body is not valid JSON |

**Resolution**: Check the `details.errors` object for specific field errors.

Example validation error:
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": {
      "errors": {
        "title": "Title is required",
        "priority": "Priority must be between 1 and 5"
      }
    }
  }
}
```

### Rate Limit Errors (429)

| Code | Message | Headers |
|------|---------|---------|
| `RATE_LIMIT_EXCEEDED` | Too many requests | `Retry-After`, `X-RateLimit-Reset` |

**Resolution**: Wait until the `Retry-After` timestamp, then retry.

Response headers:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Requests remaining (will be 0)
- `X-RateLimit-Reset`: Unix timestamp when limit resets
- `Retry-After`: Seconds until you can retry

### Conflict Errors (409)

| Code | Message | Cause |
|------|---------|-------|
| `USER_EXISTS` | User with this email already exists | Email taken during registration |
| `DUPLICATE_NAME` | Name already exists | Duplicate project/tag name |

**Resolution**: Use a different value for the conflicting field.

### Server Errors (500)

| Code | Message | Cause |
|------|---------|-------|
| `INTERNAL_ERROR` | Internal server error | Unexpected server failure |
| `DATABASE_ERROR` | Database error | Database connection/query failure |

**Resolution**: Retry the request. If persistent, contact support with `requestId`.

## Handling Errors in Code

### Python Example
```python
import requests

response = requests.post(
    'https://api.example.com/api/v1/tasks',
    headers={'Authorization': f'Bearer {token}'},
    json={'title': ''}
)

data = response.json()

if not data['success']:
    error = data['error']

    if error['code'] == 'VALIDATION_ERROR':
        for field, message in error['details']['errors'].items():
            print(f"Field '{field}': {message}")
    elif error['code'] == 'TOKEN_EXPIRED':
        # Refresh token and retry
        refresh_token()
        retry_request()
    elif error['code'] == 'RATE_LIMIT_EXCEEDED':
        retry_after = response.headers.get('Retry-After', 60)
        time.sleep(int(retry_after))
        retry_request()
    else:
        print(f"Error: {error['message']}")
```

### JavaScript Example
```javascript
async function createTask(title) {
  try {
    const response = await fetch('/api/v1/tasks', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ title })
    });

    const data = await response.json();

    if (!data.success) {
      switch (data.error.code) {
        case 'VALIDATION_ERROR':
          Object.entries(data.error.details.errors).forEach(([field, msg]) => {
            console.error(`${field}: ${msg}`);
          });
          break;
        case 'TOKEN_EXPIRED':
          await refreshToken();
          return createTask(title); // Retry
        case 'RATE_LIMIT_EXCEEDED':
          const retryAfter = response.headers.get('Retry-After') || 60;
          await sleep(retryAfter * 1000);
          return createTask(title); // Retry
        default:
          throw new Error(data.error.message);
      }
    }

    return data.data;
  } catch (error) {
    console.error('Request failed:', error);
    throw error;
  }
}
```

## Best Practices

1. **Always check `success` field** before processing `data`
2. **Log `requestId`** for debugging and support requests
3. **Handle rate limits gracefully** with exponential backoff
4. **Refresh tokens proactively** before they expire
5. **Validate input client-side** to reduce 422 errors

# API Reference

Complete reference for all Todo-Me API endpoints.

## Base URL
```
https://your-domain.com/api/v1
```

## Authentication
All endpoints except registration and login require authentication.

```
Authorization: Bearer YOUR_API_TOKEN
```
or
```
X-API-Key: YOUR_API_TOKEN
```

---

## Authentication Endpoints

### Register User
```
POST /auth/register
```

Create a new user account.

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | Yes | Valid email address |
| `password` | string | Yes | Min 12 chars, 1 uppercase, 1 lowercase, 1 number |

**Response:** `201 Created`
```json
{
  "user": {"id": "uuid", "email": "user@example.com"},
  "token": "api-token",
  "expiresAt": "2026-01-26T10:30:00+00:00"
}
```

---

### Login
```
POST /auth/token
```

Authenticate and receive an API token.

**Request Body:**
| Field | Type | Required |
|-------|------|----------|
| `email` | string | Yes |
| `password` | string | Yes |

**Response:** `200 OK`
```json
{
  "token": "api-token",
  "expiresAt": "2026-01-26T10:30:00+00:00"
}
```

---

### Refresh Token
```
POST /auth/refresh
```

Get a new token using an expired (within 7 days) token.

**Response:** `200 OK` - Same as login

---

### Revoke Token
```
POST /auth/revoke
```

Invalidate the current token (logout).

**Response:** `204 No Content`

---

### Get Current User
```
GET /auth/me
```

Get the authenticated user's information.

**Response:** `200 OK`
```json
{
  "id": "uuid",
  "email": "user@example.com",
  "createdAt": "2026-01-01T00:00:00+00:00"
}
```

---

### Change Password
```
PATCH /auth/me/password
```

Change the authenticated user's password.

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `current_password` | string | Yes | Current password |
| `new_password` | string | Yes | New password (min 12 chars, 1 uppercase, 1 lowercase, 1 number) |

**Response:** `200 OK`

---

### Forgot Password
```
POST /auth/forgot-password
```

Request a password reset email. Returns a generic success message regardless of whether the email exists (prevents email enumeration).

**Request Body:**
| Field | Type | Required |
|-------|------|----------|
| `email` | string | Yes |

**Response:** `200 OK`
```json
{
  "message": "If the email exists, a reset link has been sent."
}
```

---

### Validate Reset Token
```
POST /auth/reset-password/validate
```

Check if a password reset token is valid.

**Request Body:**
| Field | Type | Required |
|-------|------|----------|
| `token` | string | Yes |

**Response:** `200 OK`
```json
{
  "valid": true
}
```

---

### Reset Password
```
POST /auth/reset-password
```

Reset password using a valid reset token.

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `token` | string | Yes | Reset token from email |
| `password` | string | Yes | New password (min 12 chars, 1 uppercase, 1 lowercase, 1 number) |

**Response:** `200 OK`

---

### Password Requirements
```
GET /auth/password-requirements
```

Get the current password policy requirements. No authentication required.

**Response:** `200 OK`
```json
{
  "minLength": 12,
  "requireUppercase": true,
  "requireLowercase": true,
  "requireNumbers": true,
  "requireSpecialChars": false
}
```

---

### Verify Email
```
POST /auth/verify-email/{token}
```

Verify a user's email address using the token sent via email. No authentication required.

**Response:** `200 OK`

---

### Resend Verification Email
```
POST /auth/resend-verification
```

Resend the email verification link to the authenticated user.

**Response:** `200 OK`

---

## Task Endpoints

### List Tasks
```
GET /tasks
```

Get paginated list of tasks with optional filtering.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | integer | 1 | Page number |
| `limit` | integer | 20 | Items per page (max 100) |
| `status` | string | - | Filter by status |
| `priority` | integer | - | Filter by priority (1-5) |
| `project` | uuid | - | Filter by project ID |
| `q` | string | - | Full-text search |
| `sort` | string | position | Sort field |
| `order` | string | asc | Sort order (asc/desc) |

See [Filter Reference](filters.md) for complete filtering options.

---

### Create Task
```
POST /tasks
```

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | Yes | Task title (max 500) |
| `description` | string | No | Description (max 2000) |
| `status` | string | No | pending, in_progress, completed |
| `priority` | integer | No | 1-5 (default: 3) |
| `dueDate` | string | No | ISO date (YYYY-MM-DD) |
| `projectId` | uuid | No | Project to assign |
| `tagIds` | uuid[] | No | Tags to add |
| `isRecurring` | boolean | No | Enable recurrence |
| `recurrenceRule` | string | No | Recurrence pattern |

**Response:** `201 Created`

---

### Get Task
```
GET /tasks/{id}
```

---

### Update Task
```
PATCH /tasks/{id}
```

Partial update - only include fields to change.

**Response includes `undoToken`** for reverting changes.

---

### Delete Task
```
DELETE /tasks/{id}
```

**Response includes `undoToken`** for restoring the task.

---

### Update Task Status
```
PUT /tasks/{id}/status
```

**Request Body:**
```json
{"status": "completed"}
```

---

### Reschedule Task
```
PUT /tasks/{id}/reschedule
```

**Request Body:**
```json
{"dueDate": "2026-02-01"}
```

---

### Complete Forever (Stop Recurrence)
```
POST /tasks/{id}/complete-forever
```

Completes a recurring task and stops future recurrences.

---

### Undo Operation
```
POST /tasks/{id}/undo/{token}
```

Reverses a delete, update, or status change within 60 seconds.

---

### Reorder Tasks
```
PUT /tasks/reorder
```

**Request Body:**
```json
{
  "taskIds": ["uuid-1", "uuid-2", "uuid-3"]
}
```

---

## Specialized Task Views

### Today's Tasks
```
GET /tasks/today
```

### Upcoming Tasks
```
GET /tasks/upcoming
GET /tasks/upcoming?days=14
```

### Overdue Tasks
```
GET /tasks/overdue
```

### Tasks Without Due Date
```
GET /tasks/no-date
```

### Completed Tasks
```
GET /tasks/completed
GET /tasks/completed?since=2026-01-01
```

---

## Batch Operations

### Execute Batch
```
POST /tasks/batch
POST /tasks/batch?atomic=true
```

Execute multiple operations in one request.

**Request Body:**
```json
{
  "operations": [
    {"action": "create", "data": {"title": "New task"}},
    {"action": "update", "taskId": "uuid", "data": {"title": "Updated"}},
    {"action": "delete", "taskId": "uuid"},
    {"action": "complete", "taskId": "uuid"},
    {"action": "reschedule", "taskId": "uuid", "data": {"due_date": "2026-02-01"}}
  ]
}
```

**Query Parameters:**
| Parameter | Default | Description |
|-----------|---------|-------------|
| `atomic` | false | Rollback all on any failure |

**Response:** `200 OK` (all success) or `207 Multi-Status` (partial)

---

### Undo Batch
```
POST /tasks/batch/undo/{token}
```

---

## Project Endpoints

### List Projects
```
GET /projects
```

### Create Project
```
POST /projects
```

**Request Body:**
| Field | Type | Required |
|-------|------|----------|
| `name` | string | Yes |
| `description` | string | No |
| `parentId` | uuid | No |
| `color` | string | No |
| `icon` | string | No |

### Get Project
```
GET /projects/{id}
```

### Update Project
```
PATCH /projects/{id}
```

### Delete Project
```
DELETE /projects/{id}
```

### Archive Project
```
POST /projects/{id}/archive
```

### Unarchive Project
```
POST /projects/{id}/unarchive
```

---

## Search

### Global Search
```
GET /search
```

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `q` | string | Required | Search query |
| `type` | string | all | all, tasks, projects, tags |
| `page` | integer | 1 | Page number |
| `limit` | integer | 20 | Items per page |

---

## Autocomplete

### Project Autocomplete
```
GET /autocomplete/projects?q={prefix}
```

### Tag Autocomplete
```
GET /autocomplete/tags?q={prefix}
```

---

## Parse (Natural Language)

### Parse and Create Task
```
POST /parse
```

**Request Body:**
```json
{"input": "Call mom tomorrow at 3pm #personal p2"}
```

### Preview Parse (No Creation)
```
POST /parse/preview
```

Shows how input will be parsed without creating a task.

---

## Response Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 204 | No Content (success with empty body) |
| 207 | Multi-Status (batch partial success) |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 423 | Account Locked (too many failed login attempts) |
| 429 | Rate Limited |
| 500 | Server Error |

---

## OpenAPI Documentation

Interactive API documentation available at:
```
GET /api/v1/docs
```

JSON schema:
```
GET /api/v1/docs.json
```

# AI-Accessible Todo List Application - Architecture Document

## 1. Executive Summary

A Symfony-based todo list application inspired by Todoist, designed for both human interaction through an intuitive web UI and programmatic access via a comprehensive REST API. The application emphasizes **ease of entry** as a core principle, featuring intelligent natural language parsing for dates and projects, hierarchical project organization, and multiple customizable views.

**Core Philosophy**: Tasks should flow from thought to system with minimal friction. The interface should be smart enough to understand intent while staying out of the user's way.

## 2. Technology Stack

- **Backend Framework**: Symfony 7.x (latest stable)
- **PHP**: 8.2+ (required for Symfony 7)
  - Required extensions: pdo_pgsql, redis, intl, mbstring, xml, curl, zip, gd
- **Database**: PostgreSQL 15+
- **Cache & Sessions**: Redis 7+
  - **Roles**: 
    - Undo token storage (60-second TTL)
    - Application caching (project trees, user settings, tag lists)
    - Session storage (optional)
- **API Layer**: API Platform 3.x for RESTful endpoints
- **ORM**: Doctrine ORM
- **Authentication**: Symfony Security component with API tokens
- **Frontend**: 
  - Twig templates for server-rendered pages
  - Vanilla JavaScript for interactive features (date parsing, project tagging)
  - Alpine.js or Stimulus (optional, for reactive components)
  - Tailwind CSS for styling
- **Web Server**: Nginx (for production), Symfony CLI dev server (for development)
- **Testing**: PHPUnit 10+ for backend, optional Playwright for E2E tests
- **Containerization**: Docker and Docker Compose (recommended but not mandatory)

## 3. Database Schema

### 3.1 Core Tables

**users**
```sql
id              BIGSERIAL PRIMARY KEY
username        VARCHAR(100) UNIQUE NOT NULL
email           VARCHAR(255) UNIQUE NOT NULL
password_hash   VARCHAR(255) NOT NULL
api_token       VARCHAR(64) UNIQUE
created_at      TIMESTAMP NOT NULL DEFAULT NOW()
updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
settings        JSONB DEFAULT '{}'  -- User preferences
```

**projects**
```sql
id              BIGSERIAL PRIMARY KEY
user_id         BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE
parent_id       BIGINT REFERENCES projects(id) ON DELETE CASCADE
name            VARCHAR(255) NOT NULL
color           VARCHAR(7) DEFAULT '#808080'  -- Hex color
icon            VARCHAR(50)  -- emoji or icon identifier
position        INTEGER NOT NULL DEFAULT 0  -- For manual sorting
is_archived     BOOLEAN DEFAULT FALSE
show_children_tasks BOOLEAN DEFAULT TRUE  -- Show sub-project tasks in parent
created_at      TIMESTAMP NOT NULL DEFAULT NOW()
updated_at      TIMESTAMP NOT NULL DEFAULT NOW()

CONSTRAINT valid_hierarchy CHECK (parent_id != id)
INDEX idx_projects_user (user_id)
INDEX idx_projects_parent (parent_id)
```

**tasks**
```sql
id              BIGSERIAL PRIMARY KEY
user_id         BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE
project_id      BIGINT REFERENCES projects(id) ON DELETE SET NULL
parent_task_id  BIGINT REFERENCES tasks(id) ON DELETE CASCADE
original_task_id BIGINT REFERENCES tasks(id) ON DELETE SET NULL  -- For recurring task chains
title           VARCHAR(500) NOT NULL
description     TEXT
status          VARCHAR(20) NOT NULL DEFAULT 'pending'
priority        INTEGER DEFAULT 0
due_date        TIMESTAMP
due_time        TIME  -- Separate from date for better querying
is_recurring    BOOLEAN DEFAULT FALSE
recurrence_rule TEXT  -- Natural language or parsed JSON structure
recurrence_type VARCHAR(10)  -- 'absolute' (every) or 'relative' (every!)
recurrence_end_date TIMESTAMP  -- Optional end date for recurring tasks
position        INTEGER NOT NULL DEFAULT 0
search_vector   TSVECTOR  -- For full-text search
created_at      TIMESTAMP NOT NULL DEFAULT NOW()
updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
completed_at    TIMESTAMP

-- Constraints
CONSTRAINT valid_status CHECK (status IN ('pending', 'in_progress', 'completed'))
CONSTRAINT valid_priority CHECK (priority BETWEEN 0 AND 4)
CONSTRAINT valid_recurrence_type CHECK (recurrence_type IS NULL OR recurrence_type IN ('absolute', 'relative'))
-- Application-level enforcement: task.user_id must equal project.user_id when project_id is not null

-- Indexes
INDEX idx_tasks_user (user_id)
INDEX idx_tasks_project (project_id)
INDEX idx_tasks_due_date (due_date)
INDEX idx_tasks_status (status)
INDEX idx_tasks_parent (parent_task_id)
INDEX idx_tasks_original (original_task_id)
INDEX idx_tasks_search USING GIN (search_vector)

-- Trigger for maintaining search_vector
CREATE TRIGGER tasks_search_vector_update
BEFORE INSERT OR UPDATE ON tasks
FOR EACH ROW
EXECUTE FUNCTION tsvector_update_trigger(
  search_vector, 'pg_catalog.english', title, description
);
```

**Note on user_id invariant**: While not enforced by a database constraint (to avoid circular dependencies), the application MUST ensure that when a task has a project_id, the task.user_id equals the project.user_id. This is validated in the Task entity and TaskService.

**tags**
```sql
id              BIGSERIAL PRIMARY KEY
user_id         BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE
name            VARCHAR(100) NOT NULL
color           VARCHAR(7) DEFAULT '#808080'
created_at      TIMESTAMP NOT NULL DEFAULT NOW()

UNIQUE (user_id, name)
INDEX idx_tags_user (user_id)
```

**task_tags** (Many-to-Many)
```sql
task_id         BIGINT NOT NULL REFERENCES tasks(id) ON DELETE CASCADE
tag_id          BIGINT NOT NULL REFERENCES tags(id) ON DELETE CASCADE

PRIMARY KEY (task_id, tag_id)
INDEX idx_task_tags_task (task_id)
INDEX idx_task_tags_tag (tag_id)
```

### 3.2 Core Invariants for AI Agents

These rules MUST be enforced by the application at all times:

**User Ownership**:
- Every task belongs to exactly one user (task.user_id is NOT NULL)
- Every project belongs to exactly one user (project.user_id is NOT NULL)
- When a task has a project_id, that project MUST belong to the same user (task.user_id = project.user_id)
- Users can only query, create, update, or delete their own tasks and projects

**Task Status**:
- Valid values: `pending`, `in_progress`, `completed`
- Any other value is invalid and will result in a 422 error
- Default status is `pending`
- Status transitions: pending → in_progress → completed (any direction allowed)

**Task Priority**:
- Valid values: 0, 1, 2, 3, 4
- Semantic mapping: 0=None, 1=Low, 2=Medium, 3=High, 4=Urgent
- Any value outside 0-4 range is invalid and will result in a 422 error
- Default priority is 0

**Recurring Tasks**:
- All tasks in a recurring chain have `original_task_id` pointing to the FIRST task in that chain
- The first task in a chain has `original_task_id = NULL`
- When completing a recurring task, the system creates a new instance and copies the `original_task_id` from the completed task (or sets it to the completed task's ID if it was the first)
- `recurrence_type` must be either 'absolute' or 'relative' when `is_recurring = true`
- `recurrence_rule` stores the natural language recurrence pattern

**Project Hierarchy**:
- Projects can have a `parent_id` pointing to another project
- Circular references are not allowed (enforced by application logic)
- A project cannot be its own parent
- When `show_children_tasks = true`, querying a project returns tasks from all descendant projects
- When `show_children_tasks = false`, querying a project returns only tasks directly assigned to that project

**Time Zones**:
- All timestamps in the database are stored in UTC
- User time zone is stored in `users.settings->timezone` (JSONB field)
- Natural language parsing ("today", "tomorrow") is resolved using the user's time zone
- API responses include timestamps in UTC with ISO 8601 format
- Recurrence date calculations are performed in the user's time zone, then converted to UTC for storage

**Search Vector**:
- The `search_vector` column is automatically maintained by a PostgreSQL trigger
- It indexes both `title` and `description` fields
- Applications should never manually set this field

## 4. Key Features & Requirements

### 4.1 Easy Task Entry (CRITICAL FEATURE)

**Natural Language Parsing**

The task entry field must intelligently parse user input and extract metadata while typing:

- **Date Detection**: Recognizes natural language dates
  - Absolute: "Jan 23", "1/23", "2026-01-23"
  - Relative: "today", "tomorrow", "next Friday", "in 3 days"
  - Day names: "Mon", "Monday", "Fri"
  - When detected, highlight the text visually (e.g., with a colored underline or background)
  - If user backspaces through highlighted date text, remove the date assignment and treat as plain text

- **Project Assignment**: Using # hashtag syntax
  - Type "#ProjectName" anywhere in the task title
  - As user types, show autocomplete dropdown of matching projects
  - When selected/completed, visually tag the project (colored chip/pill)
  - Support for nested projects: "#ParentProject/ChildProject"

- **Priority Assignment**: Using p1, p2, p3, p4 syntax
  - p4 = Urgent (highest)
  - p3 = High
  - p2 = Medium
  - p1 = Low
  - p0 or no priority = None

- **Tags**: Using @ symbol
  - "@work", "@personal", "@urgent"
  - Autocomplete from existing tags
  - Create new tags on the fly

**Implementation Approach**:
- Parse input on keyup/input events (frontend)
- Use regex patterns to detect dates, projects, priority
- Highlight matches in real-time with visual indicators
- Store parsed metadata separately from displayed text
- Allow click-to-remove on highlighted elements

**Backend Parser**:
- A shared `NaturalLanguageParser` service exists in PHP that both the UI and API use
- The API supports two modes:
  1. **Structured input** (recommended for AI agents): Explicit fields like `due_date`, `project_id`, `tags[]`, `priority`
  2. **Natural language input**: Send `input_text` field with `parse_natural_language=true` flag, and the API will parse and extract metadata

Example API call with natural language:
```json
POST /api/v1/tasks?parse_natural_language=true
{
  "input_text": "Review project proposal #work @urgent tomorrow p3"
}

Response:
{
  "data": {
    "id": 123,
    "title": "Review project proposal",
    "project": {"id": 5, "name": "work"},
    "tags": [{"id": 10, "name": "urgent"}],
    "due_date": "2026-01-24",
    "priority": 3,
    ...
  }
}
```

**Time Zone and Locale Handling**:
- User time zone stored in `users.settings->timezone` (e.g., "America/Los_Angeles")
- Default time zone: UTC if not set
- "today" and "tomorrow" are resolved to dates in the user's time zone
- If a date is parsed without a time (e.g., "tomorrow"), default time is start of day (00:00) in user's time zone
- Start of week preference stored in `users.settings->start_of_week` (0=Sunday, 1=Monday, etc.)
- Date format preference stored in `users.settings->date_format` (e.g., "MDY", "DMY", "YMD")

**Project and Tag Auto-Creation**:
- **Tags**: Auto-created on the fly when using @ symbol
  - Tag names are case-insensitive (normalized to lowercase for matching)
  - If @urgent doesn't exist, it's created automatically
- **Projects**: NOT auto-created via # hashtag
  - Must match an existing project name (case-insensitive match)
  - If no match found, parsing fails and user is shown autocomplete suggestions
  - Nested project syntax `#Parent/Child` requires both Parent and Child to exist
  - AI agents should query `/api/v1/projects` first to get valid project names

**Priority Parsing**:
- Syntax: `p0`, `p1`, `p2`, `p3`, `p4` (case-insensitive)
- Invalid priority values (e.g., `p5`, `p10`) are ignored with a warning

**Example Task Entry**:
```
Input: "Review project proposal #work @urgent tomorrow p3"

Parsed:
- Title: "Review project proposal"
- Project: work
- Tags: [urgent]
- Due Date: 2026-01-24 (tomorrow)
- Priority: 3 (High)
```

### 4.2 Project Hierarchy

**Structure**:
- Support unlimited nesting depth (unlike Todoist's 3-level limit)
- Each project can have multiple child projects
- Visual indentation in the UI to show hierarchy

**Project Settings**:
- `show_children_tasks`: Boolean flag
  - When TRUE: Parent project view shows tasks from all descendant projects
  - When FALSE: Parent project view shows only tasks directly assigned to that project
- Configurable per project
- Affects both UI and API responses

**Visual Design**:
- Use indentation to show hierarchy
- Collapsible/expandable tree structure
- Show task counts for each project (with optional child task counts)

### 4.3 Views

**ALL Tasks View** (Default)
- Shows every task across all projects
- Display project name/color alongside each task
- Group by project, with visual separators
- Filterable and sortable

**Today View**
- Tasks due today or overdue
- Sorted by priority, then due time
- Clear visual distinction for overdue items (red text/icon)

**Upcoming View**
- All tasks with due dates, sorted chronologically
- Group by date (Today, Tomorrow, Next 7 Days, Later)
- Show week/month boundaries

**Overdue View**
- Tasks past their due date that are not completed
- Sorted by how overdue (oldest first)
- Highlight urgency visually

**Project Views**
- Individual view for each project
- Respects `show_children_tasks` setting
- Can toggle between "This project only" and "Include sub-projects"

**Filter & Sort Capabilities**:
All views must support:
- **Filters**:
  - Status (pending, in_progress, completed)
  - Priority (any combination of 0-4)
  - Projects (multi-select, including hierarchy)
  - Tags (multi-select with AND/OR logic)
  - Due date ranges
  - Search (title + description)
  
- **Sorting**:
  - Due date (asc/desc)
  - Priority (high to low / low to high)
  - Creation date (newest/oldest)
  - Alphabetical (A-Z / Z-A)
  - Manual (drag and drop)
  - Custom (combine multiple criteria)

### 4.4 Task Properties

**Core Attributes**:
- Title (required)
- Description (optional, supports markdown)
- Project assignment (optional)
- Due date (optional)
- Due time (optional, separate from date)
- Priority (0-4)
- Tags (multiple)
- Status (pending → in_progress → completed)

**Advanced Features**:
- **Subtasks**: Tasks can have child tasks (unlimited nesting)
- **Recurring Tasks**: 
  - Natural language: "every Monday", "every 2 weeks", "monthly on the 15th"
  - Generate next occurrence on completion
- **Task Notes**: Rich text area for additional context
- **Attachments**: (Future enhancement - not in v1)

### 4.5 Recurring Tasks - Detailed Implementation

**Core Behavior**:
Unlike Todoist's single-task-with-shifting-date approach, this system creates new task instances to maintain a complete history of completions.

**Task Creation**:
```json
POST /api/v1/tasks
{
  "title": "Weekly team meeting",
  "recurrence_rule": "every Monday at 2pm",
  "recurrence_type": "absolute",
  "is_recurring": true
}
```

This creates ONE task in the database with:
- `is_recurring = true`
- `recurrence_rule = "every Monday at 2pm"`
- `recurrence_type = "absolute"`
- `original_task_id = null` (this is the first in chain)
- `due_date = "2026-01-27T14:00:00"` (next Monday)

**Completion Flow**:
```
POST /api/v1/tasks/123/status
{"status": "completed"}

System actions:
1. Mark task 123 as completed (status='completed', completed_at=NOW())
2. Calculate next due date based on recurrence_rule and recurrence_type
   - Calculation performed in user's time zone (from users.settings->timezone)
   - Result converted to UTC for storage
3. Create NEW task with:
   - Same title, description, project_id, tags, priority
   - New calculated due_date (in UTC)
   - is_recurring = true
   - recurrence_rule = "every Monday at 2pm" (copied)
   - recurrence_type = "absolute" (copied)
   - original_task_id = <ID of first task in chain>
     * If task 123 has original_task_id = null (it's the first), set new task's original_task_id = 123
     * If task 123 has original_task_id = 456 (it's a recurrence), copy 456 to new task
   - New ID (e.g., 789)

Response:
{
  "data": {
    "id": 123,
    "status": "completed",
    "completed_at": "2026-01-23T15:30:00Z",
    ...
  },
  "next_task": {
    "id": 789,
    "title": "Weekly team meeting",
    "due_date": "2026-02-03T14:00:00Z",
    "original_task_id": 123,  // Points to first task in chain
    ...
  },
  "undo_token": "undo_xyz789"
}
```

**original_task_id Chain Rule**:
For any task in a recurring chain:
- The **first** task created has `original_task_id = NULL`
- All **subsequent** instances have `original_task_id` pointing to that first task
- This allows querying all instances: `GET /api/v1/tasks?original_task_id=123`

**Time Zone Handling**:
- User's time zone stored in `users.settings->timezone` (e.g., "America/Los_Angeles")
- When calculating "next Monday at 2pm":
  1. Determine what "next Monday 2pm" is in user's time zone
  2. Convert to UTC for database storage
  3. This ensures recurring tasks respect user's local time even during DST changes
- API responses always return dates in UTC (ISO 8601 format)
- Frontend displays dates in user's local time zone

**Recurrence Types**:

1. **Absolute (`every`)**: Based on original schedule
   - `every Monday` → Always next Monday at same time
   - `every 15th` → Always 15th of next month
   - `every 2 weeks` → 2 weeks from original date, not completion date

2. **Relative (`every!`)**: Based on completion date
   - `every! week` → 7 days from when you complete it
   - `every! 3 days` → 3 days from completion
   - Useful for tasks like "water plants" where timing is flexible

**Parsing Recurrence Rules**:

The `recurrence_rule` field stores the original natural language string. A parser service converts this to structured data for calculation purposes, but the original text is preserved for display and re-parsing.

Natural language examples:
```
"every Monday" → {interval: 'week', day: 'Monday', type: 'absolute'}
"every! week" → {interval: 'week', type: 'relative'}
"every 2 weeks" → {interval: 'week', count: 2, type: 'absolute'}
"every 15th" → {interval: 'month', day: 15, type: 'absolute'}
"monthly on the 15th" → {interval: 'month', day: 15, type: 'absolute'}
"every weekday" → {interval: 'week', days: ['Mon','Tue','Wed','Thu','Fri'], type: 'absolute'}
"every Monday, Wednesday, Friday" → {interval: 'week', days: ['Mon','Wed','Fri'], type: 'absolute'}
```

**Invalid or Ambiguous Rules**:
- If the API receives a recurrence_rule it cannot parse, return 422 error:
```json
{
  "error": {
    "code": "INVALID_RECURRENCE",
    "message": "Could not parse recurrence rule",
    "details": {
      "recurrence_rule": ["Recurrence pattern 'every 5 hours' is not supported. Use 'every day', 'every week', etc."]
    }
  }
}
```
- Default type when ambiguous: If user writes "every week" (without "!"), default to `absolute`
- The parser should be lenient with variations (e.g., "every mon" vs "every Monday")

**Complete Forever**:
```
POST /api/v1/tasks/123/complete-forever

System actions:
1. Mark task 123 as completed
2. Do NOT create next instance
3. Remove is_recurring flag or mark it as permanently completed

Response:
{
  "data": {
    "id": 123,
    "status": "completed",
    "is_recurring": false,
    ...
  }
}
```

**Viewing Recurring Task History**:
```
GET /api/v1/tasks?original_task_id=123&status=completed

Returns all completed instances of recurring task chain
```

**End Date Support**:
```
"every Monday until March 1" → Creates recurrences, stops after March 1
```

Store in database:
```sql
recurrence_rule = "every Monday"
recurrence_end_date = "2026-03-01"
```

When generating next instance, check if next_due_date > recurrence_end_date. If true, don't create (acts like complete-forever).

### 4.6 Undo System - Detailed Implementation

**Undoable Operations**:
- Task completion (especially important for recurring tasks)
- Task deletion
- Task status changes
- Bulk operations
- Project archiving

**Endpoints That Return undo_token**:
All operations that modify data and can be undone will include an `undo_token` in the response:
- `POST /api/v1/tasks` (creation - can undo to delete)
- `PATCH /api/v1/tasks/{id}` (update - can revert to previous state)
- `PATCH /api/v1/tasks/{id}/status` (status change - can revert)
- `DELETE /api/v1/tasks/{id}` (deletion - can restore)
- `POST /api/v1/tasks/batch` (batch operations - can undo all)
- `PATCH /api/v1/projects/{id}` (archive/update - can revert)

If an operation returns an `undo_token`, it is guaranteed to be undoable for the duration of the TTL.

**Redis Storage Structure**:
```
Key: undo:{token}
Value: {
  "operation": "task_complete",
  "user_id": 1,
  "task_id": 123,
  "previous_state": {...},
  "next_task_id": 456,  // For recurring tasks
  "timestamp": "2026-01-23T15:30:00Z"
}
TTL: 60 seconds
```

**TTL vs UI Timing**:
- Redis TTL: 60 seconds (hard limit, after which undo is impossible)
- UI toast: Displayed for 5-10 seconds (soft limit, user convenience)
- Reasoning: Token lives longer than toast to allow users to manually call the undo endpoint if needed
- After 60 seconds, the token is permanently expired and returns 404 error

**Undo Endpoint**:
```
POST /api/v1/undo
{
  "undo_token": "undo_xyz789"
}

System actions:
1. Fetch operation from Redis using token
2. Verify user_id matches authenticated user
3. Reverse the operation:
   - task_complete: Set status back to 'pending', clear completed_at
   - If recurring: Delete the newly created next_task
4. Delete token from Redis
5. Return restored state

Response:
{
  "data": {
    "id": 123,
    "status": "pending",
    "completed_at": null,
    ...
  },
  "message": "Task completion undone"
}
```

**UI Integration**:
- Toast notification: "Task completed. [Undo]"
- Countdown timer showing seconds remaining
- Click "Undo" triggers API call
- On success, update UI to show task as pending again

## 5. REST API Design

### 5.1 API Principles for AI Agents

**Design Goals**:
1. **Self-documenting**: Clear endpoint names and intuitive structure
2. **Consistent**: Predictable patterns across all resources
3. **Flexible**: Support for complex queries without overwhelming simple use cases
4. **Verbose responses**: Include related data to minimize round trips
5. **Clear errors**: Helpful error messages that guide correction

**Response Format**:
```json
{
  "data": { ... },  // or [ ... ] for collections
  "meta": {
    "timestamp": "2026-01-23T10:30:00Z",
    "request_id": "uuid"
  },
  "links": {
    "self": "url",
    "related": { ... }
  }
}
```

### 5.2 Authentication

**API Token Authentication**:
```
Authorization: Bearer {api_token}
```

**Token Generation**:

Endpoint: `POST /api/v1/auth/token`

Request:
```json
{
  "username": "user@example.com",
  "password": "secure_password"
}
```

Success Response (200 OK):
```json
{
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",  // Long-lived API token
    "user": {
      "id": 1,
      "username": "user@example.com",
      "email": "user@example.com"
    },
    "expires_at": null  // null = never expires (long-lived), or ISO datetime
  },
  "meta": {
    "timestamp": "2026-01-23T10:30:00Z"
  }
}
```

Error Response (401 Unauthorized):
```json
{
  "error": {
    "code": "INVALID_CREDENTIALS",
    "message": "Invalid username or password"
  },
  "meta": {
    "timestamp": "2026-01-23T10:30:00Z",
    "request_id": "..."
  }
}
```

**Token Storage**:
- Tokens are stored hashed in the database (using bcrypt or similar)
- Minimum token length: 32 characters
- Tokens are randomly generated (cryptographically secure)

**Token Expiry Model**:
- Default: Never expires (long-lived tokens)
- Optional: Configurable expiry (e.g., 30 days, 90 days, 1 year)
- No automatic refresh mechanism in v1
- Users can revoke and regenerate tokens via `/api/v1/auth/revoke`

**Token Revocation**:

Endpoint: `POST /api/v1/auth/revoke`

Request (requires Bearer token):
```json
{
  "token": "current_token_to_revoke"  // Optional, defaults to token used for auth
}
```

Success Response (204 No Content)

**Rate Limiting**:
- 1000 requests per hour per token
- Return `X-RateLimit-*` headers

**Note**: All API endpoints are versioned under `/api/v1/`. Future breaking changes will increment to `/api/v2/`, etc.

### 5.3 Error Response Format

All errors follow a consistent JSON structure:

```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": {}  // Optional, field-specific validation errors
  },
  "meta": {
    "timestamp": "2026-01-23T10:30:00Z",
    "request_id": "uuid-v4"
  }
}
```

**HTTP Status Codes**:
- **400 Bad Request**: Malformed request (invalid JSON, wrong parameter types)
- **401 Unauthorized**: Missing or invalid authentication token
- **403 Forbidden**: Valid token but insufficient permissions
- **404 Not Found**: Resource does not exist
- **422 Unprocessable Entity**: Validation errors (valid JSON but business rules violated)
- **429 Too Many Requests**: Rate limit exceeded
- **500 Internal Server Error**: Unexpected server error

**Common Error Codes**:
- `VALIDATION_ERROR`: Field-level validation failed
- `AUTHENTICATION_REQUIRED`: No auth token provided
- `INVALID_TOKEN`: Token is invalid or expired
- `RESOURCE_NOT_FOUND`: Requested resource doesn't exist
- `DUPLICATE_RESOURCE`: Unique constraint violation
- `RATE_LIMIT_EXCEEDED`: Too many requests
- `PERMISSION_DENIED`: User doesn't own this resource
- `INVALID_STATUS`: Invalid task status value
- `INVALID_PRIORITY`: Priority out of range
- `INVALID_RECURRENCE`: Recurrence rule parsing failed
- `PROJECT_NOT_FOUND`: Referenced project doesn't exist

**Validation Error Example**:
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": {
      "title": ["This value should not be blank."],
      "priority": ["This value must be between 0 and 4."],
      "due_date": ["This value is not a valid datetime."]
    }
  },
  "meta": {
    "timestamp": "2026-01-23T10:30:00Z",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

### 5.4 Core Endpoints

**HTTP Methods Semantics**:
- **GET**: Retrieve resource(s), idempotent, no side effects
- **POST**: Create new resource, returns 201 with Location header
- **PUT**: Replace entire resource, requires all required fields, idempotent
- **PATCH**: Partial update, only provided fields are modified, missing fields ignored, idempotent
- **DELETE**: Remove resource, returns 204 on success, idempotent

Example: PATCH vs PUT
```json
// PATCH /api/v1/tasks/123 - Update only priority
{
  "priority": 3
}
// Other fields (title, description, etc.) remain unchanged

// PUT /api/v1/tasks/123 - Replace entire task
{
  "title": "Updated title",
  "description": "Updated description",
  "project_id": 5,
  "priority": 3,
  "due_date": "2026-01-30",
  "tags": ["urgent"]
}
// ALL fields must be provided or will be set to defaults/null
```

#### Tasks

```
GET    /api/v1/tasks
GET    /api/v1/tasks/{id}
POST   /api/v1/tasks
PUT    /api/v1/tasks/{id}
PATCH  /api/v1/tasks/{id}
DELETE /api/v1/tasks/{id}
```

**GET /api/v1/tasks** - List tasks with extensive filtering

Query Parameters:
```
status         = pending|in_progress|completed
project_id     = integer (includes children if project.show_children_tasks=true)
project_id[]   = array of integers (multiple projects)
exclude_project_id = integer (exclude project and children)
parent_task_id = integer (filter by parent task for subtasks, null for root tasks)
tag            = string (single tag name)
tag[]          = array of strings (multiple tags - OR logic)
tag_match      = any|all (for multiple tags: any=OR, all=AND, default: any)
priority       = 0|1|2|3|4 (exact match)
priority_min   = 0-4 (minimum priority)
priority_max   = 0-4 (maximum priority)
due_before     = ISO date (tasks due before this date)
due_after      = ISO date (tasks due after this date)
due_on         = ISO date (tasks due on specific date)
completed_before = ISO date (tasks completed before this date)
completed_after  = ISO date (tasks completed after this date)
completed_on     = ISO date (tasks completed on specific date)
overdue        = true|false (tasks past due date and not completed)
today          = true (due today or overdue)
upcoming       = true (has due date in future)
no_due_date    = true (tasks without a due date)
is_recurring   = true|false (filter recurring vs non-recurring)
original_task_id = integer (get all instances in a recurring chain)
search         = string (searches title + description using full-text search)
sort           = due_date|priority|created_at|updated_at|completed_at|title|manual
sort_order     = asc|desc (default: asc)
page           = integer (default: 1)
per_page       = integer (default: 20, max: 100)
include        = project,tags,subtasks,user (comma-separated, eager load relationships)
```

**Note**: Specialized endpoints like `/api/v1/tasks/today` and `/api/v1/tasks/overdue` support all these same filters for additional refinement.

Example Response:
```json
{
  "data": [
    {
      "id": 123,
      "title": "Review architecture document",
      "description": "Check the Symfony todo app spec",
      "status": "pending",
      "priority": 3,
      "due_date": "2026-01-15",
      "due_time": "17:00:00",
      "is_recurring": false,
      "recurrence_rule": null,
      "position": 0,
      "created_at": "2026-01-14T10:30:00Z",
      "updated_at": "2026-01-14T10:30:00Z",
      "completed_at": null,
      "project": {
        "id": 5,
        "name": "Work",
        "color": "#e74c3c",
        "parent": {
          "id": 1,
          "name": "Professional"
        }
      },
      "tags": [
        {"id": 10, "name": "urgent", "color": "#f39c12"}
      ],
      "subtasks": [],
      "subtask_count": 0,
      "completed_subtask_count": 0,
      "links": {
        "self": "/api/v1/tasks/123",
        "project": "/api/v1/projects/5",
        "subtasks": "/api/v1/tasks?parent_task_id=123"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "total_pages": 3
  },
  "links": {
    "first": "/api/v1/tasks?page=1",
    "last": "/api/v1/tasks?page=3",
    "prev": null,
    "next": "/api/v1/tasks?page=2"
  }
}
```

**POST /api/v1/tasks** - Create new task

Request Body:
```json
{
  "title": "Review architecture document",
  "description": "Check all sections",
  "project_id": 5,
  "priority": 3,
  "due_date": "2026-01-15",
  "due_time": "17:00",
  "tags": ["urgent", "work"],  // Can use names or IDs
  "parent_task_id": null
}
```

**PATCH /api/v1/tasks/{id}/status** - Quick status update
```json
{
  "status": "completed"
}
```

**PATCH /api/v1/tasks/{id}/reschedule** - Quick date change
```json
{
  "due_date": "tomorrow",  // Supports natural language
  "due_time": "14:00"
}
```

#### Specialized Task Queries

```
GET /api/v1/tasks/today
GET /api/v1/tasks/overdue  
GET /api/v1/tasks/upcoming
GET /api/v1/tasks/no-date
```

These are convenience endpoints that return pre-filtered results. They support the same query parameters as `/api/v1/tasks` for additional filtering.

#### Projects

```
GET    /api/v1/projects
GET    /api/v1/projects/{id}
POST   /api/v1/projects
PUT    /api/v1/projects/{id}
PATCH  /api/v1/projects/{id}
DELETE /api/v1/projects/{id}
GET    /api/v1/projects/{id}/tasks
GET    /api/v1/projects/tree
```

**GET /api/v1/projects/tree** - Returns hierarchical structure

Response:
```json
{
  "data": [
    {
      "id": 1,
      "name": "Professional",
      "color": "#3498db",
      "task_count": 5,
      "children": [
        {
          "id": 5,
          "name": "Work",
          "color": "#e74c3c",
          "task_count": 12,
          "children": []
        }
      ]
    }
  ]
}
```

**GET /api/v1/projects/{id}/tasks** - Get tasks for project

Query Parameters:
```
include_children = true|false (default: project.show_children_tasks setting)
... (all standard task filters apply)
```

#### Batch Operations

```
POST /api/v1/tasks/batch
```

Request:
```json
{
  "operations": [
    {"action": "create", "data": {...}},
    {"action": "update", "id": 123, "data": {...}},
    {"action": "delete", "id": 124}
  ]
}
```

Response includes success/failure for each operation.

#### Search

```
GET /api/v1/search?q={query}
```

Searches across tasks (title, description) and projects (name). Returns grouped results.

### 5.5 API Documentation

**Tools**:
- OpenAPI 3.0 specification (auto-generated by API Platform)
- Swagger UI at `/api/v1/docs`
- Redoc at `/api/v1/redoc` (alternative docs interface)

**Documentation Requirements for AI Agents**:

1. **Clear Examples**: Every endpoint has request/response examples
2. **Use Case Documentation**: Common workflows documented:
   - "How to create a task for tomorrow"
   - "How to get all overdue tasks"
   - "How to move a task to a different project"
   - "How to mark multiple tasks complete"
   - "How to get tasks for the current week"
   - "How to create recurring tasks"

3. **Natural Language Query Guide**: 
   - Document all supported date formats
   - Examples of complex filter combinations
   - Common pitfalls and solutions

4. **Error Dictionary**: Comprehensive list of error codes with:
   - What caused the error
   - How to fix it
   - Example of correct usage

5. **Changelog**: Version history of API changes

## 6. User Interface Design

**UI Design System Reference**: All UI implementation MUST follow the specifications in `docs/UI-DESIGN-SYSTEM.md`. This document defines:
- Color system (indigo-600 primary, semantic colors)
- Typography scale (system font stack, text sizes)
- Spacing scale (4px base unit)
- Component specifications (buttons, inputs, cards, badges, dropdowns, modals, toasts)
- Task-specific component styling (task cards, priority indicators, project chips)
- Accessibility requirements (WCAG 2.1 AA compliance)
- Animation/transition standards
- Responsive design patterns

All visual specifications in this section should be cross-referenced with UI-DESIGN-SYSTEM.md for exact Tailwind CSS classes and Alpine.js patterns.

### 6.1 Layout Structure

```
┌─────────────────────────────────────────────────────┐
│  Header (Logo, Search, User Menu)                  │
├──────────────┬──────────────────────────────────────┤
│              │                                      │
│   Sidebar    │         Main Content Area           │
│              │                                      │
│  - Inbox     │  ┌────────────────────────────────┐ │
│  - Today     │  │  Quick Add (Always Visible)    │ │
│  - Upcoming  │  └────────────────────────────────┘ │
│  - Overdue   │                                      │
│  ────────    │  ┌────────────────────────────────┐ │
│  Projects:   │  │  Task List                     │ │
│    Work      │  │                                │ │
│    Personal  │  │  ☐ Review architecture doc     │ │
│    ├─ Home   │  │     #work @urgent → Tomorrow   │ │
│    └─ Health │  │                                │ │
│              │  │  ☐ Buy groceries               │ │
│  ────────    │  │     #personal → Fri            │ │
│  Tags:       │  │                                │ │
│    @urgent   │  └────────────────────────────────┘ │
│    @work     │                                      │
│              │                                      │
└──────────────┴──────────────────────────────────────┘
```

### 6.2 Quick Add Interface

**Position**: Fixed at top of main content area, always visible

**Features**:
- Large text input field
- Real-time parsing feedback (highlighted dates, projects, tags)
- Keyboard shortcut (Ctrl/Cmd + K) to focus from anywhere
- Dropdown for autocomplete (projects, tags)
- Visual indicators for parsed metadata below input
- "Add Task" button (or press Enter)

**Interaction Flow**:
1. User types: "Review proposal #work @urgent tomorrow p3"
2. As they type:
   - "#work" shows project autocomplete → selected, shown as colored chip
   - "@urgent" shows tag autocomplete → selected, shown as chip
   - "tomorrow" is highlighted as date
   - "p3" is parsed as priority, shown with icon
3. Input field now displays: "Review proposal [#work chip] [@urgent chip] [tomorrow highlight] [p3 icon]"
4. Press Enter or click "Add Task"
5. Task created, input cleared, ready for next entry

**Advanced Options** (expandable):
- Description field (markdown)
- Manual date picker (as alternative to natural language)
- Recurring options

### 6.3 Task Display

**List View** (default):
```
☐ Review architecture document          [Work] 
  @urgent • Tomorrow 5:00 PM • Priority: High
  
☐ Buy groceries for dinner              [Personal > Home]
  Friday • Priority: Medium
```

**Board View** (optional future feature):
- Kanban-style columns for status
- Drag and drop between columns

### 6.4 Filtering & Sorting UI

**Filter Panel** (collapsible sidebar or modal):
- Checkboxes for status
- Radio buttons or multi-select for projects (show hierarchy)
- Tag selector with autocomplete
- Date range pickers
- Priority range slider
- Search input

**Sort Dropdown**:
- Quick access to common sort options
- Visual indicator of current sort

**Active Filters Display**:
- Show applied filters as removable chips above task list
- "Clear all" option

### 6.5 Mobile Responsiveness

- Collapsible sidebar (hamburger menu)
- Bottom navigation for key views (Inbox, Today, Projects)
- Swipe gestures (Phase 8 - optional for MVP):
  - Swipe right: Complete task
  - Swipe left: Show task menu (edit, delete, reschedule)
- Touch-optimized Quick Add with on-screen date picker

### 6.6 Keyboard Shortcuts

Essential keyboard shortcuts for power users:

| Shortcut | Action | Context |
|----------|--------|---------|
| `Ctrl/Cmd + K` | Focus Quick Add input | Anywhere in app |
| `Ctrl/Cmd + Enter` | Submit Quick Add form | Quick Add focused |
| `Escape` | Close modal/dropdown/clear Quick Add | Anywhere |
| `/` | Focus search | Anywhere |
| `a` | Add task to bottom of current list | Project view |
| `Shift + A` | Add task to top of current list | Project view |
| `c` | Mark selected task as complete | Task selected |
| `e` | Edit selected task | Task selected |
| `Delete/Backspace` | Delete selected task | Task selected |
| `1-4` | Set priority (p1-p4) | Task selected |
| `t` | Set due date to today | Task selected |
| `y` | Set due date to tomorrow | Task selected |
| `↑` / `↓` | Navigate task list | Task list |
| `Enter` | Open task details | Task selected |
| `Ctrl/Cmd + Z` | Undo last action | After undoable action |

**Implementation Note**: Keyboard shortcuts should be clearly documented in the UI (Help modal or keyboard shortcuts cheatsheet accessible via `?` key).

## 7. Implementation Phases

### Phase 1: Core Foundation (Week 1-2)
- [ ] Symfony project setup with Docker
- [ ] Database schema and migrations (including search_vector, original_task_id)
- [ ] User authentication and API token system
- [ ] Basic CRUD for tasks and projects
- [ ] Simple task list view
- [ ] Redis setup for undo system

### Phase 2: Natural Language Parsing (Week 2-3)
- [ ] Date parsing library integration (Carbon or custom)
- [ ] Real-time parsing in Quick Add
- [ ] Project hashtag detection with autocomplete
- [ ] Tag @ symbol detection with autocomplete
- [ ] Priority parsing (p1-p4)
- [ ] Visual feedback for parsed elements (highlighting, chips)
- [ ] Unit tests for all parsing logic (50+ test cases)

### Phase 3: Project Hierarchy & Archiving (Week 3-4)
- [ ] Nested projects support (unlimited depth)
- [ ] show_children_tasks setting per project
- [ ] Project tree UI with collapsible nodes
- [ ] Task queries respecting hierarchy
- [ ] Project archiving (is_archived flag)
- [ ] Archived projects view
- [ ] Archive/unarchive endpoints

### Phase 4: Views & Filtering (Week 4-5)
- [ ] Today view
- [ ] Upcoming view (grouped by date)
- [ ] Overdue view with visual urgency indicators
- [ ] ALL tasks view with project grouping
- [ ] Filter system (backend + UI)
  - Multi-select filters for status, projects, tags, priority
  - Date range filters
  - Search integration
- [ ] Sort system (all sort options)
- [ ] Saved filters (custom views) - optional
- [ ] Active filters display with removable chips

### Phase 5: Recurring Tasks (Week 5)
- [ ] Recurrence rule parser
- [ ] Absolute vs Relative recurrence logic
- [ ] Task completion flow for recurring tasks
- [ ] New instance generation
- [ ] original_task_id tracking
- [ ] Complete forever endpoint
- [ ] End date support
- [ ] Comprehensive tests for recurring logic

### Phase 6: API Development (Week 6)
- [ ] API Platform configuration
- [ ] Task endpoints with full filtering (all query parameters)
- [ ] Project endpoints including tree endpoint
- [ ] Specialized endpoints (/today, /overdue, /upcoming)
- [ ] Batch operations
- [ ] OpenAPI 3.0 documentation
- [ ] AI-specific documentation and use case examples
- [ ] Rate limiting implementation

### Phase 7: Search & Undo System (Week 7)
- [ ] PostgreSQL Full-Text Search setup
- [ ] tsvector column and trigger
- [ ] Search indexing on task create/update
- [ ] Search endpoint with highlighting
- [ ] Undo system Redis integration
- [ ] Undo token generation and storage
- [ ] Undo endpoint
- [ ] UI toast notifications for undo

### Phase 8: Polish & Testing (Week 8)
- [ ] Subtasks support
- [ ] Keyboard shortcuts (Ctrl+K for quick add, etc.)
- [ ] Mobile responsiveness
- [ ] Swipe gestures on mobile
- [ ] Performance optimization (query analysis, caching)
- [ ] Comprehensive PHPUnit test suite
  - Unit tests (80%+ coverage)
  - Functional/API tests
  - Natural language parser tests
- [ ] CI/CD pipeline setup (GitHub Actions)
- [ ] Load testing

### Phase 9: Documentation & Deployment (Week 9)
- [ ] Complete API documentation with examples
- [ ] Setup guide for Claude Code and AI agents
- [ ] Docker compose for local development
- [ ] Production deployment configuration
- [ ] Monitoring and logging setup (Monolog, Sentry)
- [ ] README with setup instructions

## 8. AI Agent Integration Examples

### Example 1: Creating a Task

**User tells AI**: "Add 'review contract' to my work project for next Monday at 2pm with high priority"

**AI API Call**:
```json
POST /api/v1/tasks
{
  "title": "Review contract",
  "project_id": 5,
  "due_date": "2026-01-27",
  "due_time": "14:00",
  "priority": 3
}
```

### Example 2: Checking Today's Tasks

**User asks AI**: "What do I need to do today?"

**AI API Call**:
```
GET /api/v1/tasks/today?include=project,tags&sort=priority&sort_order=desc
```

**AI Response** (formatted from API data):
"You have 3 tasks due today:
1. [HIGH] Review architecture document (Work project, @urgent) - due 5pm
2. [MEDIUM] Weekly team meeting prep (Work project) - due 2pm  
3. [LOW] Buy groceries (Personal > Home project) - no time set"

### Example 3: Rescheduling Overdue Tasks

**User asks AI**: "Move all my overdue tasks to tomorrow"

**AI API Calls**:
```
1. GET /api/v1/tasks/overdue
2. POST /api/v1/tasks/batch
{
  "operations": [
    {"action": "update", "id": 101, "data": {"due_date": "tomorrow"}},
    {"action": "update", "id": 102, "data": {"due_date": "tomorrow"}},
    ...
  ]
}
```

### Example 4: Weekly Review

**User asks AI**: "Show me all tasks I completed this week grouped by project"

**AI API Call**:
```
GET /api/v1/tasks?status=completed&completed_after=2026-01-18&completed_before=2026-01-24&include=project&sort=project_id
```

**AI processes response and groups by project for presentation**

### Example 5: Project Overview

**User asks AI**: "What's the status of my Work project?"

**AI API Calls**:
```
1. GET /api/v1/projects/5
2. GET /api/v1/projects/5/tasks?include_children=true
```

**AI Response**:
"Your Work project has:
- 12 total tasks (including sub-projects)
- 3 overdue
- 5 due this week
- 4 with no due date

Breakdown by priority:
- Urgent: 2 tasks
- High: 5 tasks
- Medium: 3 tasks
- Low: 2 tasks"

## 9. Security Considerations

- **SQL Injection**: Prevented by Doctrine ORM parameter binding
- **XSS**: Output escaping in Twig templates
- **CSRF**: Symfony CSRF protection on forms
- **API Rate Limiting**: Prevent abuse
- **Input Validation**: Comprehensive validation rules
- **Token Security**: Tokens hashed in database, long and random
- **HTTPS**: Required in production
- **User Isolation**: All queries filtered by user_id

## 10. Performance Optimization

- **Database Indexes**: On frequently queried fields (user_id, project_id, due_date, status)
- **Query Optimization**: 
  - Eager loading of relationships to prevent N+1 queries
  - Use of database views for complex aggregations
- **Caching**: 
  - Project trees (Redis)
  - User settings
  - Tag lists
- **Pagination**: Default limit on list queries
- **API Response Caching**: Cache-Control headers for immutable data

## 11. Testing Strategy

### 11.1 Unit Tests (PHPUnit)

**Domain/Business Logic Tests**:
- `tests/Unit/Service/`
  - `TaskServiceTest.php` - Task CRUD operations, status transitions
  - `RecurringTaskServiceTest.php` - Recurring task generation logic
  - `ProjectServiceTest.php` - Project hierarchy operations
  - `NaturalLanguageDateParserTest.php` - Date parsing accuracy
  - `UndoServiceTest.php` - Undo operation logic

**Entity Tests**:
- `tests/Unit/Entity/`
  - `TaskTest.php` - Task entity methods, validation
  - `ProjectTest.php` - Project entity methods, hierarchy validation
  - `UserTest.php` - User entity methods

**Repository Tests**:
- `tests/Unit/Repository/`
  - `TaskRepositoryTest.php` - Complex queries, filtering, sorting
  - `ProjectRepositoryTest.php` - Tree queries, task count aggregations

**Coverage Requirements**:
- Minimum 80% code coverage for business logic
- 100% coverage for critical paths (task creation, recurring logic, undo)

### 11.2 Functional/Integration Tests (PHPUnit)

**API Endpoint Tests**:
- `tests/Functional/Api/`
  - `TaskApiTest.php`
    - Create task with all permutations of fields
    - List tasks with every filter combination
    - Update task (full and partial)
    - Delete task
    - Batch operations
  - `RecurringTaskApiTest.php`
    - Create recurring task
    - Complete recurring task (verify new instance created)
    - Complete forever
    - Test both absolute and relative recurrence
  - `ProjectApiTest.php`
    - CRUD operations
    - Hierarchy operations (nest, unnest)
    - Task queries respecting `show_children_tasks` setting
  - `SearchApiTest.php`
    - Full-text search across tasks
    - Search with filters
  - `UndoApiTest.php`
    - Undo task completion
    - Undo task deletion
    - Undo bulk operations
    - Test token expiration

**Authentication & Authorization Tests**:
- `tests/Functional/Security/`
  - `AuthenticationTest.php` - Token generation, validation
  - `AuthorizationTest.php` - User can only access own data
  - `RateLimitingTest.php` - API rate limits enforced

**Database Constraint Tests**:
- Verify cascade behaviors
- Test unique constraints
- Validate foreign key relationships

### 11.3 End-to-End Tests (Optional - Playwright/Panther)

**Critical User Flows**:
- Complete task entry flow (type, parse, save)
- Create project and assign task
- Apply filters and sort tasks
- Complete recurring task and verify next instance
- Undo recent action

### 11.4 Natural Language Parsing Tests

**Comprehensive Test Cases**:
- `tests/Unit/Parser/DateParserTest.php`
  - Test 50+ date format variations
  - Relative dates (today, tomorrow, next week)
  - Absolute dates (Jan 23, 1/23, 2026-01-23)
  - Day names (Mon, Monday, Fri)
  - Edge cases (leap years, month boundaries)
  - Recurring patterns (every Monday, every 2 weeks)

- `tests/Unit/Parser/ProjectParserTest.php`
  - Hashtag detection
  - Nested project parsing (#Parent/Child)
  - Autocomplete logic

- `tests/Unit/Parser/TagParserTest.php`
  - @ symbol detection
  - Multiple tags
  - Tag creation

### 11.5 Performance Tests

**Load Testing** (Apache JMeter or similar):
- 100 concurrent users creating tasks
- 1000 tasks query with complex filters
- Response time < 200ms for simple queries
- Response time < 1000ms for complex queries

**Database Query Performance**:
- EXPLAIN ANALYZE on complex queries
- Verify index usage
- Test with 10,000+ tasks per user

### 11.6 Test Data & Fixtures

**Fixtures** (`tests/Fixtures/`):
- `UserFixtures.php` - Test users with various configurations
- `ProjectFixtures.php` - Sample project hierarchies
- `TaskFixtures.php` - Tasks with diverse attributes
- `RecurringTaskFixtures.php` - Recurring task examples

### 11.7 Continuous Integration

**GitHub Actions Workflow**:
```yaml
name: Tests
on: [push, pull_request]
jobs:
  phpunit:
    runs-on: ubuntu-latest
    steps:
      - Checkout code
      - Setup PHP 8.2
      - Install dependencies (composer install)
      - Setup test database
      - Run migrations
      - Run PHPUnit
      - Upload coverage report
      - Fail if coverage < 80%
```

### 11.8 Test Organization

```
tests/
├── Unit/
│   ├── Entity/
│   ├── Service/
│   ├── Repository/
│   └── Parser/
├── Functional/
│   ├── Api/
│   ├── Security/
│   └── Database/
├── E2E/ (optional)
├── Fixtures/
└── bootstrap.php
```

## 12. Deployment

- **Environment**: Docker containers
  - PHP-FPM container
  - Nginx container  
  - PostgreSQL container
  - Redis container (for caching AND undo system)
- **CI/CD**: GitHub Actions
  - Run PHPUnit tests on PR
  - Check code coverage (fail if < 80%)
  - Auto-deploy to staging on merge to develop
  - Manual deploy to production from main
- **Monitoring**: 
  - Application logs (Monolog)
  - Error tracking (Sentry)
  - API metrics (response times, error rates)
  - Redis monitoring (undo token usage, cache hit rates)

## 13. Future Enhancements (Post-MVP)

- [ ] Collaborative projects (multi-user)
- [ ] Task comments and activity history
- [ ] File attachments
- [ ] Email integration (create tasks from emails)
- [ ] Calendar sync (Google Calendar, iCal)
- [ ] Mobile apps (iOS, Android)
- [ ] Desktop app (Electron)
- [ ] Webhook support for integrations
- [ ] Time tracking per task
- [ ] Task templates
- [ ] Dark mode
- [ ] Customizable themes
- [ ] Data export (JSON, CSV)
- [ ] Task automation (if-then rules)
- [ ] AI-powered task suggestions
- [ ] Voice input for task creation

## 14. Resolved Design Decisions

### 14.1 Recurring Task Logic
**Decision**: Create new task instances on completion (not upfront)

**Implementation**:
- When a recurring task is created, only ONE task exists in the database with `is_recurring=true`
- When completed, the system:
  1. Marks current task as `completed` (status='completed', completed_at=now())
  2. Creates NEW task instance with:
     - Same title, description, project, tags, priority
     - Calculated next due date based on recurrence rule
     - New ID (fresh task)
     - `is_recurring=true` and same `recurrence_rule`
     - `original_task_id` field pointing to the FIRST task in the chain
       * First task: `original_task_id = NULL`
       * All subsequent tasks: `original_task_id = <ID of first task>`
- This allows viewing completed task history while avoiding creating 100+ future tasks
- Activity log tracks all completions

**original_task_id Chain Semantics**:
- **First task** in a recurring chain: `original_task_id = NULL`
- **All subsequent instances**: `original_task_id` points to the ID of the first task
- When completing:
  - If `original_task_id IS NULL` (completing the first), set new task's `original_task_id = current_task_id`
  - If `original_task_id IS NOT NULL` (completing a recurrence), copy the value to new task
- Query all instances: `GET /api/v1/tasks?original_task_id=<first_task_id>`

**Recurrence Types**:
- **Absolute** (`every`): Next occurrence based on original schedule
  - "every Monday" → always next Monday, regardless of completion date
  - "every 15th" → always 15th of next month
- **Relative** (`every!`): Next occurrence based on completion date  
  - "every! week" → 7 days from completion
  - "every! 3 days" → 3 days from when you complete it
- **Default**: When ambiguous (e.g., user writes "every week" without "!"), default to **absolute**

**Time Zone Handling for Recurrence**:
- All recurrence calculations are performed in the **user's time zone**
- User time zone stored in `users.settings->timezone` (e.g., "America/Los_Angeles")
- Process:
  1. Calculate next occurrence in user's local time (e.g., "next Monday 2pm PST")
  2. Convert to UTC for storage in database
  3. This ensures recurring tasks respect user's local time, including during DST transitions
- API responses always return UTC timestamps (ISO 8601)

**Recurrence Rule Storage**:
- `recurrence_rule` field stores the original natural language string (e.g., "every Monday at 2pm")
- Parser converts to structured data for calculations, but original text is preserved
- If parser cannot understand a rule, return 422 error with `INVALID_RECURRENCE` code

**API Support**:
- `PATCH /api/v1/tasks/{id}/complete-forever` - Permanently complete without creating next instance
- Response includes `next_task_id` when a new instance is created

### 14.2 Subtask Completion
**Decision**: Parent tasks do NOT auto-complete when all subtasks are done

**Rationale**: User may want to add more subtasks or review before marking parent complete. Manual completion gives user full control.

### 14.3 Project Deletion
**Decision**: Projects are archived, not deleted. Tasks remain accessible.

**Implementation**:
- Projects have `is_archived` boolean flag
- Deleting a project sets `is_archived=true`
- Archived projects hidden from default views but can be viewed in "Archived Projects" section
- Tasks in archived projects remain queryable and visible
- Tasks keep their `project_id` reference to archived project
- Can unarchive projects to restore full functionality
- True deletion (with cascade to tasks) only available via special admin action with confirmation

### 14.4 Task Search
**Decision**: Use PostgreSQL Full-Text Search (FTS) for now

**Implementation**:
- Add `tsvector` column to tasks table for search indexing
- Index on title and description
- Trigger to auto-update search vector on task changes
- Simple and sufficient for single-user, can migrate to Elasticsearch if needed

### 14.5 Undo System
**Decision**: Implement time-limited undo for destructive operations

**Implementation**:
- Store recent operations in Redis with 60-second TTL
- Operations tracked: task completion, deletion, bulk updates
- Return `undo_token` in API responses for undoable operations
- `POST /api/v1/undo` endpoint accepts undo_token
- UI shows toast notification with "Undo" button for 5-10 seconds
- After token expires, undo no longer available

**Example Flow**:
```json
POST /api/v1/tasks/123/status
{"status": "completed"}

Response:
{
  "data": {...},
  "undo_token": "undo_abc123",
  "undo_expires_at": "2026-01-23T10:31:00Z"
}

// User has 60 seconds to undo
POST /api/v1/undo
{"undo_token": "undo_abc123"}
```

### 14.6 API Versioning  
**Decision**: Start with `/api/v1/` namespace

All API endpoints use `/api/v1/` prefix. Future breaking changes will introduce `/api/v2/` while maintaining `/api/v1/` for backwards compatibility.

---

## Appendix A: Natural Language Date Parsing Examples

```
"today"              → 2026-01-23
"tomorrow"           → 2026-01-24
"next Friday"        → 2026-01-30
"Fri"                → 2026-01-24 (next occurrence)
"Jan 30"             → 2026-01-30
"1/30"               → 2026-01-30
"in 3 days"          → 2026-01-26
"next week"          → 2026-01-30 (Monday of next week)
"in 2 weeks"         → 2026-02-06

Recurring:
"every Monday"       → Weekly on Monday
"every 2 weeks"      → Bi-weekly from today
"monthly on the 15th"→ Monthly on 15th
"every weekday"      → Mon-Fri
```

## Appendix B: Example API Queries for Common Use Cases

**Get tasks for today and tomorrow**:
```
GET /api/v1/tasks?due_after=2026-01-23&due_before=2026-01-24
```

**Get high-priority work tasks**:
```
GET /api/v1/tasks?project_id=5&priority_min=3
```

**Get all tasks without due dates**:
```
GET /api/v1/tasks?no_due_date=true
```

**Search for tasks about "meeting"**:
```
GET /api/v1/tasks?search=meeting
```

**Get completed tasks from last week**:
```
GET /api/v1/tasks?status=completed&completed_after=2026-01-13&completed_before=2026-01-19
```

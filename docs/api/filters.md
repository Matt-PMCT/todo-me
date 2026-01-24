# Filter Reference

The Tasks API supports extensive filtering options for listing and searching tasks.

## Quick Reference

```bash
# Basic filters
/api/v1/tasks?status=pending
/api/v1/tasks?priority=5
/api/v1/tasks?project={project-id}

# Combine filters
/api/v1/tasks?status=pending&priority=5&project={id}

# Date filters
/api/v1/tasks?due_before=2026-02-01
/api/v1/tasks?due_after=2026-01-01

# Pagination
/api/v1/tasks?page=2&limit=20

# Sorting
/api/v1/tasks?sort=due_date&order=asc
```

## Filter Parameters

### Status Filter

Filter tasks by their current status.

| Value | Description |
|-------|-------------|
| `pending` | Not started |
| `in_progress` | Currently being worked on |
| `completed` | Finished |

```bash
# Single status
/api/v1/tasks?status=pending

# Multiple statuses (comma-separated)
/api/v1/tasks?status=pending,in_progress
```

### Priority Filter

Filter by priority level (1-5, where 1 is lowest).

```bash
# Exact priority
/api/v1/tasks?priority=5

# Multiple priorities
/api/v1/tasks?priority=4,5

# Priority range
/api/v1/tasks?min_priority=3&max_priority=5
```

### Project Filter

Filter tasks belonging to a specific project.

```bash
# By project ID
/api/v1/tasks?project={project-uuid}

# Include child project tasks
/api/v1/tasks?project={project-uuid}&include_children=true

# Tasks with no project
/api/v1/tasks?project=none
```

### Tag Filter

Filter tasks with specific tags.

```bash
# Single tag
/api/v1/tasks?tag={tag-uuid}

# Multiple tags (AND - must have all)
/api/v1/tasks?tags={tag1},{tag2}

# Any tag (OR - must have at least one)
/api/v1/tasks?any_tag={tag1},{tag2}

# Tasks with no tags
/api/v1/tasks?has_tags=false
```

### Date Filters

Filter by due date.

```bash
# Due before date (exclusive)
/api/v1/tasks?due_before=2026-02-01

# Due after date (inclusive)
/api/v1/tasks?due_after=2026-01-01

# Due on specific date
/api/v1/tasks?due_date=2026-01-24

# Date range
/api/v1/tasks?due_after=2026-01-01&due_before=2026-02-01

# Tasks with no due date
/api/v1/tasks?has_due_date=false
```

### Recurrence Filter

Filter by recurrence status.

```bash
# Only recurring tasks
/api/v1/tasks?is_recurring=true

# Only non-recurring tasks
/api/v1/tasks?is_recurring=false
```

### Text Search

Full-text search across title and description.

```bash
# Search query
/api/v1/tasks?q=meeting

# Combined with other filters
/api/v1/tasks?q=meeting&status=pending
```

## Specialized View Endpoints

These endpoints provide pre-filtered views optimized for common use cases.

### Today's Tasks
```bash
GET /api/v1/tasks/today
```
Returns: Tasks due today + overdue + high priority without due date

### Upcoming Tasks
```bash
GET /api/v1/tasks/upcoming
GET /api/v1/tasks/upcoming?days=14
```
Returns: Tasks due within N days (default 7)

### Overdue Tasks
```bash
GET /api/v1/tasks/overdue
```
Returns: Past-due tasks ordered by due date

### Tasks Without Due Date
```bash
GET /api/v1/tasks/no-date
```
Returns: Tasks with no due date set

### Completed Tasks
```bash
GET /api/v1/tasks/completed
GET /api/v1/tasks/completed?since=2026-01-01
```
Returns: Completed tasks, optionally since a date

## Sorting

Control the order of results.

| Parameter | Values |
|-----------|--------|
| `sort` | `due_date`, `priority`, `created_at`, `updated_at`, `title`, `position` |
| `order` | `asc`, `desc` |

```bash
# Sort by due date ascending
/api/v1/tasks?sort=due_date&order=asc

# Sort by priority descending (highest first)
/api/v1/tasks?sort=priority&order=desc

# Default: position ascending
/api/v1/tasks
```

## Pagination

Control the number of results returned.

| Parameter | Default | Max |
|-----------|---------|-----|
| `page` | 1 | - |
| `limit` | 20 | 100 |

```bash
# Page 2 with 50 items per page
/api/v1/tasks?page=2&limit=50
```

Response includes pagination metadata:
```json
{
  "data": {
    "items": [...],
    "meta": {
      "total": 150,
      "page": 2,
      "limit": 50,
      "totalPages": 3,
      "hasNextPage": true,
      "hasPreviousPage": true
    }
  }
}
```

## Complex Filter Examples

### High Priority Tasks Due This Week
```bash
/api/v1/tasks?priority=4,5&due_after=2026-01-20&due_before=2026-01-27&status=pending
```

### All Incomplete Tasks in Project
```bash
/api/v1/tasks?project={id}&status=pending,in_progress&include_children=true
```

### Search Urgent Tasks
```bash
/api/v1/tasks?q=urgent&priority=5&status=pending&sort=due_date&order=asc
```

### Tasks Completed Last Week
```bash
/api/v1/tasks/completed?since=2026-01-17&before=2026-01-24
```

### Untagged Tasks Without Due Date
```bash
/api/v1/tasks?has_tags=false&has_due_date=false&status=pending
```

## Global Search

The search endpoint provides cross-entity search:

```bash
# Search all entity types
GET /api/v1/search?q=meeting

# Search specific type only
GET /api/v1/search?q=meeting&type=tasks
GET /api/v1/search?q=meeting&type=projects
GET /api/v1/search?q=meeting&type=tags
```

Response includes results from all types:
```json
{
  "data": {
    "tasks": [...],
    "projects": [...],
    "tags": [...],
    "meta": {
      "counts": {
        "tasks": 5,
        "projects": 2,
        "tags": 1
      }
    }
  }
}
```

# Common API Use Cases

This guide provides ready-to-use examples for common task management workflows.

## Daily Workflow

### Get Today's Tasks
```bash
curl -H "Authorization: Bearer TOKEN" \
  /api/v1/tasks/today
```

### Get Overdue Tasks
```bash
curl -H "Authorization: Bearer TOKEN" \
  /api/v1/tasks/overdue
```

### Get Upcoming Tasks (Next 7 Days)
```bash
curl -H "Authorization: Bearer TOKEN" \
  "/api/v1/tasks/upcoming?days=7"
```

### Complete a Task
```bash
curl -X PUT /api/v1/tasks/{id}/status \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "completed"}'
```

### Undo Task Completion
Use the `undoToken` from the complete response:
```bash
curl -X POST /api/v1/tasks/{id}/undo/{token} \
  -H "Authorization: Bearer TOKEN"
```

## Quick Task Capture

### Create with Natural Language
```bash
curl -X POST /api/v1/parse \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"input": "Call John tomorrow at 2pm #work p1"}'
```

### Create with Structured Data
```bash
curl -X POST /api/v1/tasks \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Call John",
    "dueDate": "2026-01-25",
    "dueTime": "14:00",
    "priority": 1,
    "tagIds": ["work-tag-uuid"]
  }'
```

## Bulk Operations

### Complete Multiple Tasks
```bash
curl -X POST /api/v1/tasks/batch \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "operations": [
      {"action": "complete", "taskId": "uuid-1"},
      {"action": "complete", "taskId": "uuid-2"},
      {"action": "complete", "taskId": "uuid-3"}
    ]
  }'
```

### Reschedule Multiple Tasks
```bash
curl -X POST /api/v1/tasks/batch \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "operations": [
      {"action": "reschedule", "taskId": "uuid-1", "data": {"due_date": "2026-02-01"}},
      {"action": "reschedule", "taskId": "uuid-2", "data": {"due_date": "2026-02-01"}}
    ]
  }'
```

### Atomic Batch (All or Nothing)
Add `?atomic=true` to rollback all changes if any operation fails:
```bash
curl -X POST "/api/v1/tasks/batch?atomic=true" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "operations": [
      {"action": "create", "data": {"title": "Task 1"}},
      {"action": "create", "data": {"title": "Task 2"}}
    ]
  }'
```

## Project Management

### List All Projects
```bash
curl -H "Authorization: Bearer TOKEN" \
  /api/v1/projects
```

### Get Project Tree (Hierarchy)
```bash
curl -H "Authorization: Bearer TOKEN" \
  /api/v1/projects/tree
```

### Get Tasks in Project
```bash
curl -H "Authorization: Bearer TOKEN" \
  "/api/v1/tasks?project={project-id}"
```

### Create Project with Sub-Projects
```bash
# Create parent
curl -X POST /api/v1/projects \
  -H "Authorization: Bearer TOKEN" \
  -d '{"name": "Work"}'

# Create child
curl -X POST /api/v1/projects \
  -H "Authorization: Bearer TOKEN" \
  -d '{"name": "Client A", "parentId": "work-project-uuid"}'
```

## Search and Filter

### Global Search
Search across tasks, projects, and tags:
```bash
curl -H "Authorization: Bearer TOKEN" \
  "/api/v1/search?q=meeting"
```

### Search Tasks Only
```bash
curl -H "Authorization: Bearer TOKEN" \
  "/api/v1/search?q=meeting&type=tasks"
```

### Filter Tasks by Multiple Criteria
```bash
curl -H "Authorization: Bearer TOKEN" \
  "/api/v1/tasks?status=pending&priority=5&project={project-id}"
```

### Tasks Without Due Date
```bash
curl -H "Authorization: Bearer TOKEN" \
  /api/v1/tasks/no-date
```

## Recurring Tasks

### Create Daily Recurring Task
```bash
curl -X POST /api/v1/tasks \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "title": "Daily standup",
    "isRecurring": true,
    "recurrenceRule": "every day"
  }'
```

### Create Weekly Recurring Task
```bash
curl -X POST /api/v1/tasks \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "title": "Weekly review",
    "isRecurring": true,
    "recurrenceRule": "every friday"
  }'
```

### Complete Forever (Stop Recurrence)
```bash
curl -X POST /api/v1/tasks/{id}/complete-forever \
  -H "Authorization: Bearer TOKEN"
```

## Autocomplete

### Project Autocomplete
```bash
curl -H "Authorization: Bearer TOKEN" \
  "/api/v1/autocomplete/projects?q=wor"
```

### Tag Autocomplete
```bash
curl -H "Authorization: Bearer TOKEN" \
  "/api/v1/autocomplete/tags?q=urg"
```

## Token Management

### Refresh Token
```bash
curl -X POST /api/v1/auth/refresh \
  -H "Authorization: Bearer EXPIRED_TOKEN"
```

### Logout (Revoke Token)
```bash
curl -X POST /api/v1/auth/revoke \
  -H "Authorization: Bearer TOKEN"
```

## Error Handling Example

Always check `success` field in response:

```javascript
const response = await fetch('/api/v1/tasks', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ title: '' }) // Invalid: empty title
});

const data = await response.json();

if (!data.success) {
  console.error(`Error ${data.error.code}: ${data.error.message}`);
  // Handle specific error codes
  if (data.error.code === 'VALIDATION_ERROR') {
    console.log('Validation errors:', data.error.details);
  }
}
```

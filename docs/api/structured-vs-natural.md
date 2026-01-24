# Structured vs Natural Language Input

Todo-Me supports both structured JSON input and natural language parsing for task creation. This guide explains when to use each approach.

## Structured Input

Use the standard task creation endpoint with explicit JSON fields:

```bash
curl -X POST /api/v1/tasks \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Review quarterly report",
    "description": "Check all financial figures",
    "priority": 4,
    "dueDate": "2026-02-15",
    "projectId": "project-uuid",
    "tagIds": ["tag-uuid-1", "tag-uuid-2"]
  }'
```

### When to Use Structured Input

- **Programmatic creation**: Building tasks from another system
- **Bulk imports**: Migrating data from other tools
- **Precise control**: When you need exact field values
- **API integrations**: When the calling system has structured data

### Available Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | Yes | Task title (max 500 chars) |
| `description` | string | No | Detailed description (max 2000 chars) |
| `status` | string | No | `pending`, `in_progress`, `completed` (default: `pending`) |
| `priority` | integer | No | 1-5, where 1 is lowest (default: 3) |
| `dueDate` | string | No | ISO date format (YYYY-MM-DD) |
| `projectId` | uuid | No | Assign to a project |
| `tagIds` | uuid[] | No | Array of tag UUIDs |
| `isRecurring` | boolean | No | Enable recurrence (default: false) |
| `recurrenceRule` | string | No | Natural language recurrence (e.g., "every Monday") |

## Natural Language Input

Use the parse endpoint to create tasks from natural language:

```bash
curl -X POST /api/v1/parse \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"input": "Call mom tomorrow at 3pm #personal p2"}'
```

### Supported Patterns

**Dates:**
- `today`, `tomorrow`, `monday`, `next friday`
- `in 3 days`, `in 2 weeks`
- `jan 15`, `2026-02-20`

**Priorities:**
- `p1` through `p5` or `!1` through `!5`
- `p1` = highest priority, `p5` = lowest

**Tags:**
- `#tagname` - adds or creates tag
- Multiple: `#work #urgent`

**Projects:**
- `@ProjectName` - assigns to project
- Matches existing projects by name

### Examples

| Input | Parsed Result |
|-------|---------------|
| `Buy groceries tomorrow` | Due tomorrow, default priority |
| `Call dentist next monday p1` | Due next Monday, priority 1 |
| `Review PR @work #code-review` | Project: work, Tag: code-review |
| `Weekly standup every monday` | Recurring every Monday |
| `Finish report by jan 30 p2 #deadline` | Due Jan 30, priority 2, tagged |

### Preview Before Creating

Use the preview endpoint to see how input will be parsed without creating a task:

```bash
curl -X POST /api/v1/parse/preview \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"input": "Meeting with client next friday at 2pm @sales"}'
```

Response:
```json
{
  "success": true,
  "data": {
    "title": "Meeting with client",
    "dueDate": "2026-01-31",
    "dueTime": "14:00",
    "project": {"name": "sales", "matched": true},
    "tags": [],
    "priority": 3,
    "isRecurring": false
  }
}
```

## Choosing the Right Approach

| Scenario | Recommended |
|----------|-------------|
| Quick capture from chat | Natural language |
| Bulk import from CSV | Structured |
| Voice assistant input | Natural language |
| Calendar sync | Structured |
| User typing in app | Natural language |
| Automated workflows | Structured |
| Migration from other tools | Structured |

## Combining Approaches

You can use the parse endpoint to extract structured data, modify it, then create with the structured endpoint:

1. Parse: `POST /api/v1/parse/preview`
2. Modify the response as needed
3. Create: `POST /api/v1/tasks` with modified data

This gives you the convenience of natural language with full control over the final result.

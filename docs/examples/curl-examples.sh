#!/bin/bash
#
# todo-me API curl Examples
#
# Replace these variables with your actual values:
API_URL="http://localhost:8080/api/v1"
API_TOKEN="your-api-token-here"

# Helper function for authenticated requests
auth_curl() {
    curl -s -H "Authorization: Bearer $API_TOKEN" \
         -H "Content-Type: application/json" \
         "$@"
}

echo "=== Authentication ==="

# Register a new user
echo -e "\n--- Register ---"
curl -s -X POST "$API_URL/auth/register" \
  -H "Content-Type: application/json" \
  -d '{"email": "agent@example.com", "password": "SecurePassword123"}' | jq .

# Login
echo -e "\n--- Login ---"
curl -s -X POST "$API_URL/auth/token" \
  -H "Content-Type: application/json" \
  -d '{"email": "agent@example.com", "password": "SecurePassword123"}' | jq .

# Refresh token
echo -e "\n--- Refresh Token ---"
auth_curl -X POST "$API_URL/auth/refresh" | jq .

# Get current user
echo -e "\n--- Current User ---"
auth_curl "$API_URL/auth/me" | jq .


echo -e "\n\n=== Tasks ==="

# Create task (standard)
echo -e "\n--- Create Task (Standard) ---"
auth_curl -X POST "$API_URL/tasks" \
  -d '{
    "title": "Review quarterly report",
    "description": "Check all figures and submit feedback",
    "priority": 4,
    "dueDate": "2026-01-25T17:00:00+00:00"
  }' | jq .

# Create task (natural language)
echo -e "\n--- Create Task (Natural Language) ---"
auth_curl -X POST "$API_URL/tasks?parse_natural_language=true" \
  -d '{"input_text": "Buy groceries tomorrow at 5pm #shopping high priority"}' | jq .

# List tasks
echo -e "\n--- List Tasks ---"
auth_curl "$API_URL/tasks?limit=10" | jq .

# List tasks with filters
echo -e "\n--- List Tasks (Filtered) ---"
auth_curl "$API_URL/tasks?status=pending&priority_min=3&limit=10" | jq .

# Today's tasks
echo -e "\n--- Today's Tasks ---"
auth_curl "$API_URL/tasks/today" | jq .

# Upcoming tasks (next 7 days)
echo -e "\n--- Upcoming Tasks ---"
auth_curl "$API_URL/tasks/upcoming?days=7" | jq .

# Overdue tasks
echo -e "\n--- Overdue Tasks ---"
auth_curl "$API_URL/tasks/overdue" | jq .

# Get single task (replace TASK_ID)
TASK_ID="your-task-uuid"
echo -e "\n--- Get Task ---"
auth_curl "$API_URL/tasks/$TASK_ID" | jq .

# Update task
echo -e "\n--- Update Task ---"
auth_curl -X PATCH "$API_URL/tasks/$TASK_ID" \
  -d '{"priority": 5, "description": "Updated description"}' | jq .

# Change task status
echo -e "\n--- Complete Task ---"
auth_curl -X PATCH "$API_URL/tasks/$TASK_ID/status" \
  -d '{"status": "completed"}' | jq .

# Delete task
echo -e "\n--- Delete Task ---"
auth_curl -X DELETE "$API_URL/tasks/$TASK_ID" | jq .


echo -e "\n\n=== Batch Operations ==="

# Batch complete multiple tasks
echo -e "\n--- Batch Complete ---"
auth_curl -X POST "$API_URL/batch" \
  -d '{
    "operations": [
      {"action": "complete", "taskId": "uuid1"},
      {"action": "complete", "taskId": "uuid2"}
    ]
  }' | jq .

# Batch update multiple tasks
echo -e "\n--- Batch Update ---"
auth_curl -X POST "$API_URL/batch" \
  -d '{
    "operations": [
      {"action": "update", "taskId": "uuid1", "data": {"priority": 5}},
      {"action": "update", "taskId": "uuid2", "data": {"priority": 5}}
    ]
  }' | jq .


echo -e "\n\n=== Search ==="

# Full-text search
echo -e "\n--- Search ---"
auth_curl "$API_URL/search?q=meeting&limit=10" | jq .


echo -e "\n\n=== Natural Language Parsing ==="

# Parse without creating
echo -e "\n--- Parse Preview ---"
auth_curl -X POST "$API_URL/parse" \
  -d '{"text": "Call dentist next Monday at 2pm"}' | jq .


echo -e "\n\n=== Projects ==="

# List projects
echo -e "\n--- List Projects ---"
auth_curl "$API_URL/projects" | jq .

# Create project
echo -e "\n--- Create Project ---"
auth_curl -X POST "$API_URL/projects" \
  -d '{"name": "Work", "description": "Work-related tasks"}' | jq .


echo -e "\n\n=== Tags ==="

# List tags
echo -e "\n--- List Tags ---"
auth_curl "$API_URL/tags" | jq .

# Create tag
echo -e "\n--- Create Tag ---"
auth_curl -X POST "$API_URL/tags" \
  -d '{"name": "urgent", "color": "#FF0000"}' | jq .


echo -e "\n\n=== Saved Filters ==="

# Save a filter
echo -e "\n--- Save Filter ---"
auth_curl -X POST "$API_URL/filters" \
  -d '{
    "name": "High Priority Pending",
    "filters": {
      "status": "pending",
      "priority_min": 4
    }
  }' | jq .

# List saved filters
echo -e "\n--- List Filters ---"
auth_curl "$API_URL/filters" | jq .


echo -e "\n\n=== Undo ==="

# Undo an operation (replace UNDO_TOKEN with actual token from delete/update response)
UNDO_TOKEN="your-undo-token"
echo -e "\n--- Undo Operation ---"
auth_curl -X POST "$API_URL/undo/$UNDO_TOKEN" | jq .


echo -e "\n\n=== Health Check ==="

# Check API health (no auth required)
echo -e "\n--- Health Check ---"
curl -s "$API_URL/health" | jq .

#!/usr/bin/env python3
"""
todo-me Python Client Example

A simple Python client for interacting with the todo-me API.
Designed for use in AI agent integrations.

Usage:
    client = TodoMeClient("https://your-domain.com/api/v1", "your-token")

    # Create a task using natural language
    task = client.create_task_natural("Buy groceries tomorrow at 5pm #shopping")

    # List today's tasks
    tasks = client.get_today_tasks()

    # Complete a task
    client.complete_task(task["id"])
"""

import requests
from typing import Optional
from datetime import datetime


class TodoMeClient:
    """Client for the todo-me API."""

    def __init__(self, base_url: str, api_token: str):
        """
        Initialize the client.

        Args:
            base_url: API base URL (e.g., "https://your-domain.com/api/v1")
            api_token: Your API token
        """
        self.base_url = base_url.rstrip("/")
        self.session = requests.Session()
        self.session.headers.update({
            "Authorization": f"Bearer {api_token}",
            "Content-Type": "application/json",
        })

    def _request(self, method: str, endpoint: str, **kwargs) -> dict:
        """Make an API request."""
        url = f"{self.base_url}/{endpoint.lstrip('/')}"
        response = self.session.request(method, url, **kwargs)
        data = response.json()

        if not data.get("success"):
            error = data.get("error", {})
            raise TodoMeError(
                code=error.get("code", "UNKNOWN_ERROR"),
                message=error.get("message", "Unknown error"),
                details=error.get("details"),
            )

        return data.get("data", {})

    # Authentication

    @classmethod
    def register(cls, base_url: str, email: str, password: str) -> "TodoMeClient":
        """Register a new user and return an authenticated client."""
        url = f"{base_url.rstrip('/')}/auth/register"
        response = requests.post(url, json={"email": email, "password": password})
        data = response.json()

        if not data.get("success"):
            error = data.get("error", {})
            raise TodoMeError(
                code=error.get("code"),
                message=error.get("message"),
            )

        return cls(base_url, data["data"]["token"])

    @classmethod
    def login(cls, base_url: str, email: str, password: str) -> "TodoMeClient":
        """Login and return an authenticated client."""
        url = f"{base_url.rstrip('/')}/auth/token"
        response = requests.post(url, json={"email": email, "password": password})
        data = response.json()

        if not data.get("success"):
            error = data.get("error", {})
            raise TodoMeError(
                code=error.get("code"),
                message=error.get("message"),
            )

        return cls(base_url, data["data"]["token"])

    def refresh_token(self) -> str:
        """Refresh the API token. Returns the new token."""
        data = self._request("POST", "/auth/refresh")
        new_token = data["token"]
        self.session.headers["Authorization"] = f"Bearer {new_token}"
        return new_token

    # Tasks

    def list_tasks(
        self,
        status: Optional[str] = None,
        priority_min: Optional[int] = None,
        priority_max: Optional[int] = None,
        project_id: Optional[str] = None,
        tag_ids: Optional[list[str]] = None,
        search: Optional[str] = None,
        due_before: Optional[datetime] = None,
        due_after: Optional[datetime] = None,
        page: int = 1,
        limit: int = 20,
    ) -> dict:
        """List tasks with optional filters."""
        params = {"page": page, "limit": limit}

        if status:
            params["status"] = status
        if priority_min:
            params["priority_min"] = priority_min
        if priority_max:
            params["priority_max"] = priority_max
        if project_id:
            params["project_ids"] = project_id
        if tag_ids:
            params["tag_ids"] = ",".join(tag_ids)
        if search:
            params["search"] = search
        if due_before:
            params["due_before"] = due_before.isoformat()
        if due_after:
            params["due_after"] = due_after.isoformat()

        return self._request("GET", "/tasks", params=params)

    def get_today_tasks(self) -> dict:
        """Get tasks due today and overdue tasks."""
        return self._request("GET", "/tasks/today")

    def get_upcoming_tasks(self, days: int = 7) -> dict:
        """Get tasks due in the next N days."""
        return self._request("GET", "/tasks/upcoming", params={"days": days})

    def get_overdue_tasks(self) -> dict:
        """Get overdue tasks."""
        return self._request("GET", "/tasks/overdue")

    def get_task(self, task_id: str) -> dict:
        """Get a single task by ID."""
        return self._request("GET", f"/tasks/{task_id}")

    def create_task(
        self,
        title: str,
        description: Optional[str] = None,
        priority: int = 3,
        due_date: Optional[datetime] = None,
        project_id: Optional[str] = None,
        tag_ids: Optional[list[str]] = None,
    ) -> dict:
        """Create a task with explicit fields."""
        data = {"title": title, "priority": priority}

        if description:
            data["description"] = description
        if due_date:
            data["dueDate"] = due_date.isoformat()
        if project_id:
            data["projectId"] = project_id
        if tag_ids:
            data["tagIds"] = tag_ids

        return self._request("POST", "/tasks", json=data)

    def create_task_natural(self, text: str) -> dict:
        """Create a task using natural language."""
        return self._request(
            "POST",
            "/tasks?parse_natural_language=true",
            json={"input_text": text},
        )

    def update_task(self, task_id: str, **updates) -> dict:
        """Update a task. Returns task and undo token."""
        return self._request("PATCH", f"/tasks/{task_id}", json=updates)

    def delete_task(self, task_id: str) -> dict:
        """Delete a task. Returns undo token."""
        return self._request("DELETE", f"/tasks/{task_id}")

    def complete_task(self, task_id: str) -> dict:
        """Mark a task as completed."""
        return self._request(
            "PATCH",
            f"/tasks/{task_id}/status",
            json={"status": "completed"},
        )

    def uncomplete_task(self, task_id: str) -> dict:
        """Mark a task as pending (uncomplete)."""
        return self._request(
            "PATCH",
            f"/tasks/{task_id}/status",
            json={"status": "pending"},
        )

    # Batch Operations

    def batch_complete(self, task_ids: list[str]) -> dict:
        """Complete multiple tasks at once."""
        operations = [
            {"action": "complete", "taskId": tid}
            for tid in task_ids
        ]
        return self._request("POST", "/batch", json={"operations": operations})

    def batch_delete(self, task_ids: list[str]) -> dict:
        """Delete multiple tasks at once."""
        operations = [
            {"action": "delete", "taskId": tid}
            for tid in task_ids
        ]
        return self._request("POST", "/batch", json={"operations": operations})

    # Search

    def search(self, query: str, limit: int = 20) -> dict:
        """Search tasks by keyword."""
        return self._request("GET", "/search", params={"q": query, "limit": limit})

    # Parse

    def parse_natural(self, text: str) -> dict:
        """Parse natural language without creating a task."""
        return self._request("POST", "/parse", json={"text": text})

    # Undo

    def undo(self, undo_token: str) -> dict:
        """Undo a previous operation using the undo token."""
        return self._request("POST", f"/undo/{undo_token}")

    # Projects

    def list_projects(self) -> dict:
        """List all projects."""
        return self._request("GET", "/projects")

    def create_project(
        self,
        name: str,
        description: Optional[str] = None,
        parent_id: Optional[str] = None,
    ) -> dict:
        """Create a new project."""
        data = {"name": name}
        if description:
            data["description"] = description
        if parent_id:
            data["parentId"] = parent_id
        return self._request("POST", "/projects", json=data)

    # Tags

    def list_tags(self) -> dict:
        """List all tags."""
        return self._request("GET", "/tags")


class TodoMeError(Exception):
    """Exception raised for API errors."""

    def __init__(self, code: str, message: str, details: Optional[dict] = None):
        self.code = code
        self.message = message
        self.details = details
        super().__init__(f"{code}: {message}")


# Example usage
if __name__ == "__main__":
    # Replace with your actual values
    API_URL = "http://localhost:8080/api/v1"
    API_TOKEN = "your-api-token"

    client = TodoMeClient(API_URL, API_TOKEN)

    # Create a task using natural language
    result = client.create_task_natural("Review PR tomorrow at 10am #work high priority")
    print(f"Created task: {result['task']['title']}")
    print(f"Due: {result['task'].get('dueDate')}")
    print(f"Priority: {result['task']['priority']}")

    # List today's tasks
    today = client.get_today_tasks()
    print(f"\nToday's tasks: {len(today.get('tasks', []))}")
    for task in today.get("tasks", []):
        status = "[x]" if task["status"] == "completed" else "[ ]"
        print(f"  {status} {task['title']}")

    # Search for tasks
    results = client.search("meeting")
    print(f"\nSearch results: {len(results.get('tasks', []))}")

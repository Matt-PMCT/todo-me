"""FastMCP server with Todo-Me tools."""

from mcp.server.fastmcp import FastMCP
from starlette.requests import Request
from starlette.responses import JSONResponse
from starlette.routing import Route

from .api_client import TodoApiClient
from .config import MCP_HOST, MCP_PORT, TODO_API_KEY, TODO_API_URL

mcp = FastMCP("todo-me", host=MCP_HOST, port=MCP_PORT)

api = TodoApiClient(base_url=TODO_API_URL, api_key=TODO_API_KEY)


# ---------------------------------------------------------------------------
# Health check (for Docker healthcheck / monitoring)
# ---------------------------------------------------------------------------


async def _health(request: Request) -> JSONResponse:
    return JSONResponse({"status": "ok"})


mcp._custom_starlette_routes = [Route("/health", endpoint=_health)]


# ---------------------------------------------------------------------------
# Core tools
# ---------------------------------------------------------------------------


@mcp.tool()
async def list_todos(
    status: str | None = None,
    priority_min: int | None = None,
    priority_max: int | None = None,
    project_ids: str | None = None,
    tag_ids: str | None = None,
    tag_mode: str | None = None,
    due_before: str | None = None,
    due_after: str | None = None,
    search: str | None = None,
    sort: str | None = None,
    direction: str | None = None,
    page: int = 1,
    limit: int = 20,
) -> dict:
    """List todo items with optional filters.

    Args:
        status: Filter by status — "pending", "in_progress", or "completed".
        priority_min: Minimum priority (1-5, where 1 is lowest).
        priority_max: Maximum priority (1-5, where 5 is urgent).
        project_ids: Comma-separated project UUIDs.
        tag_ids: Comma-separated tag UUIDs.
        tag_mode: How to combine tags — "AND" or "OR" (default "OR").
        due_before: ISO-8601 date. Return tasks due before this date.
        due_after: ISO-8601 date. Return tasks due after this date.
        search: Full-text search query.
        sort: Sort field — "due_date", "priority", "created_at", "updated_at",
              "completed_at", "title", or "position".
        direction: Sort direction — "asc" or "desc".
        page: Page number (default 1).
        limit: Results per page (default 20, max 100).

    Returns:
        Dict with 'items' (list of tasks) and 'meta' (pagination info).
    """
    params: dict = {}
    if status is not None:
        params["status"] = status
    if priority_min is not None:
        params["priority_min"] = priority_min
    if priority_max is not None:
        params["priority_max"] = priority_max
    if project_ids is not None:
        params["project_ids"] = project_ids
    if tag_ids is not None:
        params["tag_ids"] = tag_ids
    if tag_mode is not None:
        params["tag_mode"] = tag_mode
    if due_before is not None:
        params["due_before"] = due_before
    if due_after is not None:
        params["due_after"] = due_after
    if search is not None:
        params["search"] = search
    if sort is not None:
        params["sort"] = sort
    if direction is not None:
        params["direction"] = direction
    params["page"] = page
    params["limit"] = limit

    return await api.get("/api/v1/tasks", params=params)


@mcp.tool()
async def get_todo(task_id: str) -> dict:
    """Get a single todo item by ID.

    Args:
        task_id: The UUID of the task.

    Returns:
        The full task object including subtask counts.
    """
    return await api.get(f"/api/v1/tasks/{task_id}")


@mcp.tool()
async def create_todo(
    title: str,
    description: str | None = None,
    priority: int | None = None,
    due_date: str | None = None,
    project_id: str | None = None,
    tag_ids: list[str] | None = None,
    parse_natural_language: bool = False,
) -> dict:
    """Create a new todo item.

    Args:
        title: The task title. If parse_natural_language is True, this can be
               a natural-language string like "Buy milk tomorrow priority high #groceries".
        description: Optional longer description.
        priority: Priority level 1-5 (1 = lowest, 5 = urgent).
        due_date: ISO-8601 date string (e.g. "2025-12-31").
        project_id: UUID of the project to assign the task to.
        tag_ids: List of tag UUIDs to attach.
        parse_natural_language: If True, parse the title as natural language
                                 to extract due date, priority, tags, etc.

    Returns:
        The created task object.
    """
    body: dict = {"title": title}
    if description is not None:
        body["description"] = description
    if priority is not None:
        body["priority"] = priority
    if due_date is not None:
        body["dueDate"] = due_date
    if project_id is not None:
        body["projectId"] = project_id
    if tag_ids is not None:
        body["tagIds"] = tag_ids

    params = {"parse_natural_language": "true"} if parse_natural_language else None
    return await api.post("/api/v1/tasks", json=body, params=params)


@mcp.tool()
async def update_todo(
    task_id: str,
    title: str | None = None,
    description: str | None = None,
    priority: int | None = None,
    due_date: str | None = None,
    project_id: str | None = None,
    tag_ids: list[str] | None = None,
) -> dict:
    """Update an existing todo item.

    Args:
        task_id: The UUID of the task to update.
        title: New title.
        description: New description.
        priority: New priority (1-5).
        due_date: New due date (ISO-8601) or empty string to clear.
        project_id: New project UUID or empty string to unassign.
        tag_ids: New list of tag UUIDs (replaces existing tags).

    Returns:
        The updated task object. Includes an undoToken for reversal (60s TTL).
    """
    body: dict = {}
    if title is not None:
        body["title"] = title
    if description is not None:
        body["description"] = description
    if priority is not None:
        body["priority"] = priority
    if due_date is not None:
        body["dueDate"] = due_date
    if project_id is not None:
        body["projectId"] = project_id
    if tag_ids is not None:
        body["tagIds"] = tag_ids

    return await api.patch(f"/api/v1/tasks/{task_id}", json=body)


@mcp.tool()
async def complete_todo(task_id: str) -> dict:
    """Mark a todo item as completed.

    Args:
        task_id: The UUID of the task to complete.

    Returns:
        The updated task object. Includes an undoToken for reversal (60s TTL).
    """
    return await api.patch(
        f"/api/v1/tasks/{task_id}/status", json={"status": "completed"}
    )


@mcp.tool()
async def delete_todo(task_id: str) -> dict:
    """Delete a todo item.

    This is a hard delete. An undoToken is returned that allows reversal
    within 60 seconds.

    Args:
        task_id: The UUID of the task to delete.

    Returns:
        Confirmation with an undoToken for reversal (60s TTL).
    """
    return await api.delete(f"/api/v1/tasks/{task_id}")


@mcp.tool()
async def list_tags(
    search: str | None = None,
    page: int = 1,
    limit: int = 50,
) -> dict:
    """List available tags.

    Useful for discovering tag IDs before creating or updating todos.

    Args:
        search: Optional name search filter.
        page: Page number (default 1).
        limit: Results per page (default 50, max 100).

    Returns:
        Dict with 'items' (list of tags) and 'meta' (pagination info).
    """
    params: dict = {"page": page, "limit": limit}
    if search is not None:
        params["search"] = search
    return await api.get("/api/v1/tags", params=params)


# ---------------------------------------------------------------------------
# Undo
# ---------------------------------------------------------------------------


@mcp.tool()
async def undo(token: str) -> dict:
    """Undo a previous operation using an undo token.

    Undo tokens are returned by update_todo, complete_todo, delete_todo,
    and reschedule_todo. They are single-use and expire after 60 seconds.

    Args:
        token: The undo token from a previous operation.

    Returns:
        Dict with entityType, entityId, message, and restored entity summary.
    """
    return await api.post("/api/v1/undo", json={"token": token})


# ---------------------------------------------------------------------------
# Smart views
# ---------------------------------------------------------------------------


@mcp.tool()
async def get_today(
    sort: str | None = None,
    direction: str | None = None,
    page: int = 1,
    limit: int = 20,
) -> dict:
    """Get tasks due today and any overdue tasks.

    This is the daily view — tasks that need attention right now.
    Combines tasks due today with any that are past due.

    Args:
        sort: Sort field — "due_date", "priority", "created_at", "updated_at",
              "completed_at", "title", or "position".
        direction: Sort direction — "asc" or "desc".
        page: Page number (default 1).
        limit: Results per page (default 20, max 100).

    Returns:
        Dict with 'items' (list of tasks) and 'meta' (pagination info).
    """
    params: dict = {"page": page, "limit": limit}
    if sort is not None:
        params["sort"] = sort
    if direction is not None:
        params["direction"] = direction
    return await api.get("/api/v1/tasks/today", params=params)


@mcp.tool()
async def get_overdue(
    sort: str | None = None,
    direction: str | None = None,
    page: int = 1,
    limit: int = 20,
) -> dict:
    """Get overdue tasks (past due date, not yet completed).

    Args:
        sort: Sort field — "due_date", "priority", "created_at", "updated_at",
              "completed_at", "title", or "position".
        direction: Sort direction — "asc" or "desc".
        page: Page number (default 1).
        limit: Results per page (default 20, max 100).

    Returns:
        Dict with 'items' (list of tasks) and 'meta' (pagination info).
    """
    params: dict = {"page": page, "limit": limit}
    if sort is not None:
        params["sort"] = sort
    if direction is not None:
        params["direction"] = direction
    return await api.get("/api/v1/tasks/overdue", params=params)


@mcp.tool()
async def get_upcoming(
    days: int = 7,
    sort: str | None = None,
    direction: str | None = None,
    page: int = 1,
    limit: int = 20,
) -> dict:
    """Get tasks due within the next N days.

    Useful for planning ahead. Does not include overdue tasks — use
    get_today or get_overdue for those.

    Args:
        days: Number of days to look ahead (1-90, default 7).
        sort: Sort field — "due_date", "priority", "created_at", "updated_at",
              "completed_at", "title", or "position".
        direction: Sort direction — "asc" or "desc".
        page: Page number (default 1).
        limit: Results per page (default 20, max 100).

    Returns:
        Dict with 'items' (list of tasks) and 'meta' (pagination info).
    """
    params: dict = {"days": days, "page": page, "limit": limit}
    if sort is not None:
        params["sort"] = sort
    if direction is not None:
        params["direction"] = direction
    return await api.get("/api/v1/tasks/upcoming", params=params)


# ---------------------------------------------------------------------------
# Discovery
# ---------------------------------------------------------------------------


@mcp.tool()
async def search(
    q: str,
    type: str | None = None,
    page: int = 1,
    limit: int = 20,
) -> dict:
    """Search across tasks, projects, and tags.

    Uses full-text search for tasks and partial matching for projects/tags.
    This is distinct from the search filter on list_todos — this searches
    across all entity types simultaneously.

    Args:
        q: Search query (minimum 2 characters).
        type: Limit to entity type — "all", "tasks", "projects", or "tags"
              (default "all").
        page: Page number (default 1).
        limit: Results per page (default 20, max 100).

    Returns:
        Dict with search results grouped by type, plus pagination meta.
    """
    params: dict = {"q": q, "page": page, "limit": limit}
    if type is not None:
        params["type"] = type
    return await api.get("/api/v1/search", params=params)


@mcp.tool()
async def list_projects(
    include_archived: bool = False,
    page: int = 1,
    limit: int = 20,
) -> dict:
    """List projects with task counts.

    Useful for discovering project IDs when creating or updating tasks.

    Args:
        include_archived: Include archived projects (default False).
        page: Page number (default 1).
        limit: Results per page (default 20, max 100).

    Returns:
        Dict with 'items' (list of projects with task counts) and 'meta'
        (pagination info).
    """
    params: dict = {"page": page, "limit": limit}
    if include_archived:
        params["include_archived"] = "true"
    return await api.get("/api/v1/projects", params=params)


# ---------------------------------------------------------------------------
# Subtasks
# ---------------------------------------------------------------------------


@mcp.tool()
async def list_subtasks(task_id: str) -> dict:
    """Get subtasks of a parent task.

    Args:
        task_id: The UUID of the parent task.

    Returns:
        Dict with 'tasks' (list of subtasks), 'total' count, and
        'completedCount'.
    """
    return await api.get(f"/api/v1/tasks/{task_id}/subtasks")


@mcp.tool()
async def create_subtask(
    parent_task_id: str,
    title: str,
    description: str | None = None,
    priority: int | None = None,
    due_date: str | None = None,
) -> dict:
    """Create a subtask under a parent task.

    Subtasks support one level of nesting only — you cannot create a subtask
    of a subtask. Subtasks inherit the parent's project automatically.

    Args:
        parent_task_id: The UUID of the parent task.
        title: The subtask title.
        description: Optional longer description.
        priority: Priority level 1-5 (1 = lowest, 5 = urgent).
        due_date: ISO-8601 date string (e.g. "2026-04-15").

    Returns:
        The created subtask object.
    """
    body: dict = {"title": title}
    if description is not None:
        body["description"] = description
    if priority is not None:
        body["priority"] = priority
    if due_date is not None:
        body["dueDate"] = due_date
    return await api.post(f"/api/v1/tasks/{parent_task_id}/subtasks", json=body)


# ---------------------------------------------------------------------------
# Rescheduling
# ---------------------------------------------------------------------------


@mcp.tool()
async def reschedule_todo(task_id: str, due_date: str) -> dict:
    """Reschedule a task using natural language or an ISO date.

    Accepts natural language like "next Monday", "in 3 days", "tomorrow",
    or ISO-8601 dates like "2026-04-15".

    Args:
        task_id: The UUID of the task to reschedule.
        due_date: New due date — natural language expression or ISO-8601 date.

    Returns:
        The updated task object. Includes an undoToken for reversal (60s TTL).
    """
    return await api.patch(
        f"/api/v1/tasks/{task_id}/reschedule", json={"due_date": due_date}
    )

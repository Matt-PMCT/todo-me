"""Tests for MCP tool functions."""

from unittest.mock import AsyncMock, patch

import pytest

from todo_me_mcp import server


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


@pytest.fixture
def mock_api():
    """Patch the module-level ``api`` object in server.py."""
    mock = AsyncMock()
    with patch.object(server, "api", mock):
        yield mock


# ---------------------------------------------------------------------------
# list_todos
# ---------------------------------------------------------------------------


class TestListTodos:
    async def test_basic_call(self, mock_api):
        mock_api.get.return_value = {"items": [], "meta": {"total": 0}}
        result = await server.list_todos()
        mock_api.get.assert_called_once_with(
            "/api/v1/tasks", params={"page": 1, "limit": 20}
        )
        assert result == {"items": [], "meta": {"total": 0}}

    async def test_all_filters(self, mock_api):
        mock_api.get.return_value = {"items": []}
        await server.list_todos(
            status="pending",
            priority_min=2,
            priority_max=4,
            project_ids="uuid-1,uuid-2",
            tag_ids="tag-1",
            tag_mode="AND",
            due_before="2025-12-31",
            due_after="2025-01-01",
            search="buy milk",
            sort="due_date",
            direction="asc",
            page=2,
            limit=50,
        )
        call_params = mock_api.get.call_args[1]["params"]
        assert call_params["status"] == "pending"
        assert call_params["priority_min"] == 2
        assert call_params["priority_max"] == 4
        assert call_params["project_ids"] == "uuid-1,uuid-2"
        assert call_params["tag_ids"] == "tag-1"
        assert call_params["tag_mode"] == "AND"
        assert call_params["due_before"] == "2025-12-31"
        assert call_params["due_after"] == "2025-01-01"
        assert call_params["search"] == "buy milk"
        assert call_params["sort"] == "due_date"
        assert call_params["direction"] == "asc"
        assert call_params["page"] == 2
        assert call_params["limit"] == 50

    async def test_none_params_excluded(self, mock_api):
        mock_api.get.return_value = {"items": []}
        await server.list_todos(status="completed")
        call_params = mock_api.get.call_args[1]["params"]
        assert "priority_min" not in call_params
        assert "tag_ids" not in call_params


# ---------------------------------------------------------------------------
# get_todo
# ---------------------------------------------------------------------------


class TestGetTodo:
    async def test_fetches_by_id(self, mock_api):
        mock_api.get.return_value = {"id": "abc", "title": "Test"}
        result = await server.get_todo("abc")
        mock_api.get.assert_called_once_with("/api/v1/tasks/abc")
        assert result["id"] == "abc"


# ---------------------------------------------------------------------------
# create_todo
# ---------------------------------------------------------------------------


class TestCreateTodo:
    async def test_minimal(self, mock_api):
        mock_api.post.return_value = {"id": "new", "title": "Buy milk"}
        result = await server.create_todo(title="Buy milk")
        mock_api.post.assert_called_once_with(
            "/api/v1/tasks", json={"title": "Buy milk"}, params=None
        )
        assert result["title"] == "Buy milk"

    async def test_full_params(self, mock_api):
        mock_api.post.return_value = {"id": "new"}
        await server.create_todo(
            title="Task",
            description="Details",
            priority=3,
            due_date="2025-06-15",
            project_id="proj-1",
            tag_ids=["tag-1", "tag-2"],
        )
        body = mock_api.post.call_args[1]["json"]
        assert body["title"] == "Task"
        assert body["description"] == "Details"
        assert body["priority"] == 3
        assert body["dueDate"] == "2025-06-15"
        assert body["projectId"] == "proj-1"
        assert body["tagIds"] == ["tag-1", "tag-2"]

    async def test_natural_language(self, mock_api):
        mock_api.post.return_value = {"id": "new"}
        await server.create_todo(
            title="Buy milk tomorrow #groceries", parse_natural_language=True
        )
        params = mock_api.post.call_args[1]["params"]
        assert params == {"parse_natural_language": "true"}


# ---------------------------------------------------------------------------
# update_todo
# ---------------------------------------------------------------------------


class TestUpdateTodo:
    async def test_partial_update(self, mock_api):
        mock_api.patch.return_value = {"id": "abc", "title": "Updated"}
        result = await server.update_todo("abc", title="Updated")
        mock_api.patch.assert_called_once_with(
            "/api/v1/tasks/abc", json={"title": "Updated"}
        )
        assert result["title"] == "Updated"

    async def test_only_changed_fields_sent(self, mock_api):
        mock_api.patch.return_value = {"id": "abc"}
        await server.update_todo("abc", priority=5)
        body = mock_api.patch.call_args[1]["json"]
        assert body == {"priority": 5}
        assert "title" not in body
        assert "dueDate" not in body


# ---------------------------------------------------------------------------
# complete_todo
# ---------------------------------------------------------------------------


class TestCompleteTodo:
    async def test_sets_status_completed(self, mock_api):
        mock_api.patch.return_value = {"id": "abc", "status": "completed"}
        result = await server.complete_todo("abc")
        mock_api.patch.assert_called_once_with(
            "/api/v1/tasks/abc/status", json={"status": "completed"}
        )
        assert result["status"] == "completed"


# ---------------------------------------------------------------------------
# delete_todo
# ---------------------------------------------------------------------------


class TestDeleteTodo:
    async def test_calls_delete(self, mock_api):
        mock_api.delete.return_value = {"undoToken": "tok123"}
        result = await server.delete_todo("abc")
        mock_api.delete.assert_called_once_with("/api/v1/tasks/abc")
        assert result["undoToken"] == "tok123"


# ---------------------------------------------------------------------------
# list_tags
# ---------------------------------------------------------------------------


class TestListTags:
    async def test_default_params(self, mock_api):
        mock_api.get.return_value = {"items": [], "meta": {"total": 0}}
        result = await server.list_tags()
        mock_api.get.assert_called_once_with(
            "/api/v1/tags", params={"page": 1, "limit": 50}
        )
        assert result["meta"]["total"] == 0

    async def test_search_filter(self, mock_api):
        mock_api.get.return_value = {"items": [{"name": "groceries"}]}
        await server.list_tags(search="groc")
        call_params = mock_api.get.call_args[1]["params"]
        assert call_params["search"] == "groc"

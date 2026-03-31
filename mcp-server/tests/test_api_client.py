"""Tests for the TodoApiClient."""

import pytest
import httpx

from todo_me_mcp.api_client import ApiError, TodoApiClient


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


def _mock_response(status_code: int, json_body: dict) -> httpx.Response:
    return httpx.Response(
        status_code=status_code,
        json=json_body,
        request=httpx.Request("GET", "http://test"),
    )


# ---------------------------------------------------------------------------
# URL joining
# ---------------------------------------------------------------------------


class TestUrlJoining:
    def test_base_url_without_trailing_slash(self):
        client = TodoApiClient("http://localhost:8083", "tk")
        assert client._url("/api/v1/tasks") == "http://localhost:8083/api/v1/tasks"

    def test_base_url_with_trailing_slash(self):
        client = TodoApiClient("http://localhost:8083/", "tk")
        assert client._url("/api/v1/tasks") == "http://localhost:8083/api/v1/tasks"

    def test_base_url_with_path_prefix(self):
        client = TodoApiClient("https://pmct.work/todo-me", "tk")
        assert client._url("/api/v1/tasks") == "https://pmct.work/todo-me/api/v1/tasks"

    def test_base_url_with_path_prefix_trailing_slash(self):
        client = TodoApiClient("https://pmct.work/todo-me/", "tk")
        assert client._url("/api/v1/tasks") == "https://pmct.work/todo-me/api/v1/tasks"


# ---------------------------------------------------------------------------
# Response handling
# ---------------------------------------------------------------------------


class TestResponseHandling:
    def test_success_returns_data(self):
        client = TodoApiClient("http://test", "tk")
        resp = _mock_response(200, {
            "success": True,
            "data": {"items": [{"id": "1", "title": "Test"}], "meta": {"total": 1}},
        })
        result = client._handle_response(resp)
        assert result == {"items": [{"id": "1", "title": "Test"}], "meta": {"total": 1}}

    def test_success_false_raises(self):
        client = TodoApiClient("http://test", "tk")
        resp = _mock_response(200, {
            "success": False,
            "error": {"message": "Something went wrong"},
        })
        with pytest.raises(ApiError, match="Something went wrong"):
            client._handle_response(resp)

    def test_empty_data_returns_empty_dict(self):
        client = TodoApiClient("http://test", "tk")
        resp = _mock_response(200, {"success": True})
        result = client._handle_response(resp)
        assert result == {}


# ---------------------------------------------------------------------------
# Error handling
# ---------------------------------------------------------------------------


class TestErrorHandling:
    def test_401_raises_auth_error(self):
        client = TodoApiClient("http://test", "tk")
        resp = _mock_response(401, {})
        with pytest.raises(ApiError, match="Authentication failed") as exc_info:
            client._handle_response(resp)
        assert exc_info.value.status_code == 401

    def test_403_raises_permission_error(self):
        client = TodoApiClient("http://test", "tk")
        resp = _mock_response(403, {})
        with pytest.raises(ApiError, match="Permission denied") as exc_info:
            client._handle_response(resp)
        assert exc_info.value.status_code == 403

    def test_404_raises_with_api_message(self):
        client = TodoApiClient("http://test", "tk")
        resp = _mock_response(404, {
            "success": False,
            "error": {"message": "Task not found with ID: abc-123"},
        })
        with pytest.raises(ApiError, match="Task not found") as exc_info:
            client._handle_response(resp)
        assert exc_info.value.status_code == 404

    def test_422_raises_with_details(self):
        client = TodoApiClient("http://test", "tk")
        resp = _mock_response(422, {
            "success": False,
            "error": {
                "message": "Validation failed",
                "details": {"title": "Title is required"},
            },
        })
        with pytest.raises(ApiError, match="Validation failed") as exc_info:
            client._handle_response(resp)
        assert exc_info.value.status_code == 422
        assert exc_info.value.details == {"title": "Title is required"}

    def test_429_raises_rate_limit(self):
        client = TodoApiClient("http://test", "tk")
        resp = _mock_response(429, {})
        with pytest.raises(ApiError, match="Rate limit exceeded") as exc_info:
            client._handle_response(resp)
        assert exc_info.value.status_code == 429

    def test_500_raises_generic_error(self):
        client = TodoApiClient("http://test", "tk")
        resp = _mock_response(500, {"error": {"message": "Internal: stack trace here"}})
        with pytest.raises(ApiError, match="Todo-Me API error") as exc_info:
            client._handle_response(resp)
        assert exc_info.value.status_code == 500

    def test_500_does_not_leak_internal_message(self):
        client = TodoApiClient("http://test", "tk")
        resp = _mock_response(502, {"error": {"message": "upstream timeout"}})
        with pytest.raises(ApiError) as exc_info:
            client._handle_response(resp)
        assert "upstream timeout" not in str(exc_info.value)
        assert "Todo-Me API error" in str(exc_info.value)


# ---------------------------------------------------------------------------
# Headers
# ---------------------------------------------------------------------------


class TestHeaders:
    def test_authorization_header(self):
        client = TodoApiClient("http://test", "tm_abc123")
        headers = client._headers()
        assert headers["Authorization"] == "Bearer tm_abc123"

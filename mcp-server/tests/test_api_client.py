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
# Non-JSON responses and transport failures (issue #127)
# ---------------------------------------------------------------------------


def _raw_response(status_code: int, text: str = "", **headers: str) -> httpx.Response:
    return httpx.Response(
        status_code=status_code,
        text=text,
        headers=headers,
        request=httpx.Request("GET", "http://test/api/v1/tasks"),
    )


class TestNonJsonResponses:
    def test_html_502_maps_to_api_error_without_leaking_body(self):
        client = TodoApiClient("http://test", "tk")
        resp = _raw_response(502, "<html>Bad Gateway secret-upstream</html>", **{"content-type": "text/html"})
        with pytest.raises(ApiError, match=r"Todo-Me API error \(HTTP 502\)") as exc_info:
            client._handle_response(resp)
        assert exc_info.value.status_code == 502
        assert "secret-upstream" not in str(exc_info.value)

    def test_empty_200_body_raises_descriptive_error(self):
        client = TodoApiClient("http://test", "tk")
        resp = _raw_response(200, "", **{"content-type": "text/html"})
        with pytest.raises(ApiError, match="non-JSON response \\(HTTP 200, text/html\\)"):
            client._handle_response(resp)

    def test_html_200_login_page_raises_descriptive_error(self):
        client = TodoApiClient("http://test", "tk")
        resp = _raw_response(200, "<html>Login</html>", **{"content-type": "text/html"})
        with pytest.raises(ApiError, match="non-JSON response"):
            client._handle_response(resp)

    def test_redirect_reports_location(self):
        client = TodoApiClient("http://test", "tk")
        resp = _raw_response(302, "", location="https://example.com/login")
        with pytest.raises(ApiError, match="redirected \\(HTTP 302\\) to https://example.com/login") as exc_info:
            client._handle_response(resp)
        assert exc_info.value.status_code == 302

    def test_failure_is_logged_with_body_snippet(self, caplog):
        client = TodoApiClient("http://test", "tk")
        resp = _raw_response(503, "<html>maintenance</html>")
        with caplog.at_level("ERROR"), pytest.raises(ApiError):
            client._handle_response(resp)
        assert "HTTP 503" in caplog.text
        assert "maintenance" in caplog.text


class TestTransportErrors:
    @staticmethod
    def _client_with(handler) -> TodoApiClient:
        client = TodoApiClient("http://test", "tk")
        client._client = httpx.AsyncClient(transport=httpx.MockTransport(handler), timeout=30.0)
        return client

    async def test_timeout_has_non_empty_message(self):
        def handler(request: httpx.Request) -> httpx.Response:
            raise httpx.ReadTimeout("", request=request)

        client = self._client_with(handler)
        with pytest.raises(ApiError, match="GET /api/v1/search timed out after 30s"):
            await client.get("/api/v1/search")

    async def test_connect_error_names_the_server(self):
        def handler(request: httpx.Request) -> httpx.Response:
            raise httpx.ConnectError("refused", request=request)

        client = self._client_with(handler)
        with pytest.raises(ApiError, match="Could not reach the Todo-Me API at http://test .*ConnectError"):
            await client.get("/api/v1/tasks")

    async def test_verbs_use_the_right_method(self):
        seen: list[str] = []

        def handler(request: httpx.Request) -> httpx.Response:
            seen.append(request.method)
            return httpx.Response(200, json={"success": True, "data": {"ok": True}})

        client = self._client_with(handler)
        await client.get("/x")
        await client.post("/x", json={})
        await client.patch("/x", json={})
        await client.delete("/x")
        assert seen == ["GET", "POST", "PATCH", "DELETE"]


# ---------------------------------------------------------------------------
# Headers
# ---------------------------------------------------------------------------


class TestHeaders:
    def test_authorization_header(self):
        client = TodoApiClient("http://test", "tm_abc123")
        assert client._client.headers["Authorization"] == "Bearer tm_abc123"

    def test_timeout_is_set(self):
        client = TodoApiClient("http://test", "tk")
        assert client._client.timeout.connect == 30.0

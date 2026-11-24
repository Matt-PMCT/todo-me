"""Async HTTP client for the Todo-Me REST API."""

import logging

import httpx

logger = logging.getLogger(__name__)

_SNIPPET_LENGTH = 200


class ApiError(Exception):
    """Raised when the Todo-Me API returns an error response."""

    def __init__(self, message: str, status_code: int = 0, details: dict | None = None):
        super().__init__(message)
        self.status_code = status_code
        self.details = details or {}


class TodoApiClient:
    """Thin async wrapper around the Todo-Me REST API.

    Handles base-path joining, authorization, response unwrapping, and
    error translation.
    """

    def __init__(self, base_url: str, api_key: str):
        self._base_url = base_url.rstrip("/")
        self._client = httpx.AsyncClient(
            headers={"Authorization": f"Bearer {api_key}"},
            timeout=30.0,
        )

    def _url(self, path: str) -> str:
        return f"{self._base_url}{path}"

    async def get(self, path: str, params: dict | None = None) -> dict:
        return await self._request("GET", path, params=params)

    async def post(self, path: str, json: dict | None = None, params: dict | None = None) -> dict:
        return await self._request("POST", path, json=json, params=params)

    async def patch(self, path: str, json: dict | None = None) -> dict:
        return await self._request("PATCH", path, json=json)

    async def delete(self, path: str) -> dict:
        return await self._request("DELETE", path)

    async def _request(self, method: str, path: str, **kwargs) -> dict:
        try:
            response = await self._client.request(method, self._url(path), **kwargs)
        except httpx.TimeoutException as exc:
            logger.error("%s %s timed out: %r", method, path, exc)
            raise ApiError(f"{method} {path} timed out after {self._client.timeout.read:g}s.") from exc
        except httpx.TransportError as exc:
            logger.error("%s %s failed: %r", method, path, exc)
            raise ApiError(
                f"Could not reach the Todo-Me API at {self._base_url} ({type(exc).__name__}: {exc})."
            ) from exc
        return self._handle_response(response)

    # ------------------------------------------------------------------
    # Response handling
    # ------------------------------------------------------------------

    _ERROR_MESSAGES: dict[int, str] = {
        401: "Authentication failed. Check your API key.",
        403: "Permission denied for this operation.",
        429: "Rate limit exceeded. Try again shortly.",
    }

    def _handle_response(self, response: httpx.Response) -> dict:
        if response.status_code in self._ERROR_MESSAGES:
            raise ApiError(
                self._ERROR_MESSAGES[response.status_code],
                status_code=response.status_code,
            )

        if response.status_code >= 500:
            self._log_failure(response)
            raise ApiError(
                f"Todo-Me API error (HTTP {response.status_code}).",
                status_code=response.status_code,
            )

        if 300 <= response.status_code < 400:
            self._log_failure(response)
            location = response.headers.get("location", "unknown")
            raise ApiError(
                f"Todo-Me API redirected (HTTP {response.status_code}) to {location}. "
                "Check TODO_API_URL (scheme, host and path prefix).",
                status_code=response.status_code,
            )

        try:
            body = response.json()
        except ValueError as exc:
            self._log_failure(response)
            raise ApiError(
                f"Todo-Me API returned a non-JSON response (HTTP {response.status_code}, "
                f"{response.headers.get('content-type', 'no content-type')}). "
                "Check TODO_API_URL points at the Todo-Me API.",
                status_code=response.status_code,
            ) from exc

        if response.status_code == 404:
            error_msg = body.get("error", {}).get("message", "Resource not found.")
            raise ApiError(error_msg, status_code=404)

        if response.status_code == 422:
            error = body.get("error", {})
            raise ApiError(
                error.get("message", "Validation error."),
                status_code=422,
                details=error.get("details", {}),
            )

        if not body.get("success", False):
            error = body.get("error", {})
            raise ApiError(
                error.get("message", "Unknown API error."),
                status_code=response.status_code,
            )

        return body.get("data", {})

    @staticmethod
    def _log_failure(response: httpx.Response) -> None:
        logger.error(
            "%s %s -> HTTP %s: %s",
            response.request.method,
            response.request.url.path,
            response.status_code,
            response.text[:_SNIPPET_LENGTH],
        )

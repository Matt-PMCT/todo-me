"""Async HTTP client for the Todo-Me REST API."""

import httpx


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
        self._api_key = api_key

    def _url(self, path: str) -> str:
        return f"{self._base_url}{path}"

    def _headers(self) -> dict[str, str]:
        return {"Authorization": f"Bearer {self._api_key}"}

    async def get(self, path: str, params: dict | None = None) -> dict:
        async with httpx.AsyncClient() as client:
            response = await client.get(
                self._url(path), headers=self._headers(), params=params
            )
        return self._handle_response(response)

    async def post(self, path: str, json: dict | None = None, params: dict | None = None) -> dict:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                self._url(path), headers=self._headers(), json=json, params=params
            )
        return self._handle_response(response)

    async def patch(self, path: str, json: dict | None = None) -> dict:
        async with httpx.AsyncClient() as client:
            response = await client.patch(
                self._url(path), headers=self._headers(), json=json
            )
        return self._handle_response(response)

    async def delete(self, path: str) -> dict:
        async with httpx.AsyncClient() as client:
            response = await client.delete(
                self._url(path), headers=self._headers()
            )
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

        body = response.json()

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

        if response.status_code >= 500:
            raise ApiError("Todo-Me API error.", status_code=response.status_code)

        if not body.get("success", False):
            error = body.get("error", {})
            raise ApiError(
                error.get("message", "Unknown API error."),
                status_code=response.status_code,
            )

        return body.get("data", {})

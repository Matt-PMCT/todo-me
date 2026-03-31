"""Entry point for ``python -m todo_me_mcp``."""

import sys

from .config import MCP_HOST, MCP_PORT, MCP_TRANSPORT, TODO_API_KEY

if not TODO_API_KEY:
    print(
        "ERROR: TODO_API_KEY is required. Set it in your environment or .env file.",
        file=sys.stderr,
    )
    sys.exit(1)

from .server import mcp  # noqa: E402 — import after validation

if MCP_TRANSPORT == "http":
    mcp.run(transport="streamable-http", host=MCP_HOST, port=MCP_PORT)
else:
    mcp.run(transport="stdio")

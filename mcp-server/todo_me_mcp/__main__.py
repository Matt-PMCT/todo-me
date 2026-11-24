"""Entry point for ``python -m todo_me_mcp``."""

import logging
import sys

from .config import MCP_LOG_LEVEL, MCP_TRANSPORT, TODO_API_KEY

logging.basicConfig(level=MCP_LOG_LEVEL.upper(), stream=sys.stderr)

if not TODO_API_KEY:
    print(
        "ERROR: TODO_API_KEY is required. Set it in your environment or .env file.",
        file=sys.stderr,
    )
    sys.exit(1)

from .server import mcp  # noqa: E402 — import after validation

mcp.run(transport="streamable-http" if MCP_TRANSPORT == "http" else "stdio")

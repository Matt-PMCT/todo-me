"""Entry point for ``python -m todo_me_mcp``."""

from .config import MCP_HOST, MCP_PORT, MCP_TRANSPORT
from .server import mcp

if MCP_TRANSPORT == "http":
    mcp.run(transport="streamable-http", host=MCP_HOST, port=MCP_PORT)
else:
    mcp.run(transport="stdio")

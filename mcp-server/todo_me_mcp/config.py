"""Configuration loaded from environment variables."""

import os

from dotenv import load_dotenv

load_dotenv()

TODO_API_URL: str = os.environ.get("TODO_API_URL", "http://localhost:8083")
TODO_API_KEY: str = os.environ.get("TODO_API_KEY", "")

MCP_TRANSPORT: str = os.environ.get("MCP_TRANSPORT", "stdio")
MCP_HOST: str = os.environ.get("MCP_HOST", "127.0.0.1")
MCP_PORT: int = int(os.environ.get("MCP_PORT", "8000"))
MCP_LOG_LEVEL: str = os.environ.get("MCP_LOG_LEVEL", "INFO")

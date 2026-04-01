# Todo-Me MCP Server

An [MCP](https://modelcontextprotocol.io) server that exposes the Todo-Me REST API as tools for AI assistants (Claude Desktop, Claude Code, claude.ai, ChatGPT, and any MCP-compatible client).

## Tools (16)

### Core

| Tool | Description |
|------|-------------|
| `list_todos` | List tasks with filters (status, priority, due date, project, tags, search) |
| `get_todo` | Get a single task by ID |
| `create_todo` | Create a new task (supports natural language parsing) |
| `update_todo` | Update a task's title, description, priority, due date, project, or tags |
| `complete_todo` | Mark a task as completed |
| `delete_todo` | Delete a task (60s undo window) |
| `list_tags` | List available tags for use with task creation/updates |

### Extended

| Tool | Description |
|------|-------------|
| `undo` | Undo a previous operation using a token from update/complete/delete/reschedule |
| `get_today` | Get tasks due today and any overdue tasks |
| `get_overdue` | Get overdue tasks (past due, not completed) |
| `get_upcoming` | Get tasks due within the next N days (default 7, max 90) |
| `search` | Global full-text search across tasks, projects, and tags |
| `list_projects` | List projects with task counts (for discovering project IDs) |
| `reschedule_todo` | Reschedule a task using natural language ("next Monday") or ISO date |
| `list_subtasks` | Get subtasks of a parent task |
| `create_subtask` | Create a subtask under a parent task (max 1 level nesting) |

## Quick Start (Local Install)

The `mcp-server/` folder is **self-contained** — you only need this folder, not the full Todo-Me application. Copy or clone it to any machine with Python 3.12+.

### 1. Copy and install

```bash
# Copy just the mcp-server folder to your machine
cp -r mcp-server /path/to/your/mcp-server
cd /path/to/your/mcp-server

# Create a virtual environment and install
python3 -m venv .venv
.venv/bin/pip install -e .
```

### 2. Get an API token

Create one in **Settings > API Tokens** in the Todo-Me web UI. Tokens start with `tm_`.

### 3. Configure your AI client

The examples below use the full path to the venv Python so the client can find the package. Adjust `/path/to/your/mcp-server` to wherever you copied the folder.

#### Claude Desktop / CoWork

Add to your Claude Desktop config (`~/Library/Application Support/Claude/claude_desktop_config.json` on macOS, `%APPDATA%\Claude\claude_desktop_config.json` on Windows):

```json
{
  "mcpServers": {
    "todo-me": {
      "command": "/path/to/your/mcp-server/.venv/bin/python",
      "args": ["-m", "todo_me_mcp"],
      "env": {
        "TODO_API_URL": "https://pmct.work/todo-me",
        "TODO_API_KEY": "tm_your_token_here"
      }
    }
  }
}
```

#### Claude Code

```bash
claude mcp add --transport stdio --scope user \
  --env TODO_API_URL=https://pmct.work/todo-me \
  --env TODO_API_KEY=tm_your_token_here \
  todo-me -- /path/to/your/mcp-server/.venv/bin/python -m todo_me_mcp
```

Or add to your project's `.mcp.json`:

```json
{
  "mcpServers": {
    "todo-me": {
      "command": "/path/to/your/mcp-server/.venv/bin/python",
      "args": ["-m", "todo_me_mcp"],
      "env": {
        "TODO_API_URL": "https://pmct.work/todo-me",
        "TODO_API_KEY": "tm_your_token_here"
      }
    }
  }
}
```

### Docker (Remote Transport)

For remote access (e.g., claude.ai custom connectors), run with Docker on the server:

```bash
docker compose -f docker/docker-compose.yml up mcp -d
```

The server listens on `127.0.0.1:8084` behind the Nginx reverse proxy at `https://pmct.work/todo-me/mcp/`.

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `TODO_API_URL` | `http://localhost:8083` | Base URL to Todo-Me (include path prefix if any) |
| `TODO_API_KEY` | *(empty)* | Named API token (`tm_` prefix) |
| `MCP_TRANSPORT` | `stdio` | Transport mode: `stdio` or `http` |
| `MCP_HOST` | `127.0.0.1` | Bind address (http transport only) |
| `MCP_PORT` | `8000` | Listen port (http transport only) |
| `MCP_LOG_LEVEL` | `INFO` | Logging level |

## Development

```bash
# Install with dev dependencies
pip install -e ".[dev]"

# Run tests
pytest tests/ -v
```

## Architecture

See [docs/MCP-SERVER-ARCHITECTURE.md](../docs/MCP-SERVER-ARCHITECTURE.md) for the full architecture document.

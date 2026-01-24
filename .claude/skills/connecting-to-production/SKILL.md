---
name: connecting-to-production
description: Connects to production server (192.168.4.4) for diagnostics, updates, and maintenance. This server hosts both Better Trails and Todo-Me applications. Triggers when user requests server status checks, deployments, service restarts, or production troubleshooting.
---

# Production Server Access

**Platform:** Shared Production Infrastructure (pmct.work)

Connect to the production server using sshpass for automated SSH authentication. This server hosts multiple applications with path-based routing.

## When to Use

Automatically invoke when:

- User mentions "production server", "check server", or "server status"
- User wants to deploy updates to production
- User needs to restart services or check logs
- User asks to troubleshoot production issues
- User mentions the server IP (192.168.4.4)
- User mentions "pmct.work"

## Connection Details

See [CONNECTION.md](CONNECTION.md) for:

- Server hostname, username, and credentials
- SSH connection method with password file approach
- Common command examples for both applications

## Dual Application Deployment

This server hosts **two Symfony applications** with path-based routing:

| Application | URL | Local Port | Container | Deploy Dir |
|-------------|-----|------------|-----------|------------|
| **Better Trails** | https://pmct.work/ | 8082 | `better-trails-web` | `/opt/better-trails-web` |
| **Todo-Me** | https://pmct.work/todo-me/ | 8083 | `todo-me-web` | `/opt/todo-me` |

### Clean Separation Principles

1. **Isolated Containers:** Each app runs in its own Docker container with dedicated Redis
2. **Separate Volumes:** Each app has its own persistent volumes for sessions and logs
3. **Independent Deployments:** Update one app without affecting the other
4. **Shared Nginx:** Single nginx config handles routing to both apps via path prefix
5. **Separate Git Repos:** Each app has its own clone in `~/<app-name>`

## Server Architecture

Production uses a **hybrid architecture**:

| Component | Type | Details |
|-----------|------|---------|
| **Better Trails Web** | Docker container | `better-trails-web`, port 8082 |
| **Better Trails Web Redis** | Docker container | `better-trails-web-redis` |
| **Todo-Me Web** | Docker container | `todo-me-web`, port 8083 |
| **Todo-Me Redis** | Docker container | `todo-me-redis` |
| **API** | Systemd service | `/opt/better-trails-api`, port 8000 |
| **PostgreSQL (API)** | Docker container | `better-trails-postgres-api`, port 5433 |
| **PostgreSQL (Tiles)** | Docker container | `tiles-postgres`, port 5434 |
| **Martin** | Docker container | `tiles-martin`, port 3000 |
| **Hillshade** | Docker container | `tiles-hillshade`, port 8080 |
| **Nginx** | Systemd service | Reverse proxy, SSL termination |

## Nginx Routing Configuration

```nginx
# Better Trails (root path)
location / {
    proxy_pass http://127.0.0.1:8082;
    # ... standard proxy headers
}

# Todo-Me (path prefix)
location /todo-me/ {
    proxy_pass http://127.0.0.1:8083/;  # Note trailing slash strips prefix
    proxy_set_header X-Forwarded-Prefix /todo-me;
    # ... standard proxy headers
}
```

**Important:** The `X-Forwarded-Prefix` header tells Symfony to generate URLs with the correct prefix.

## Key Directories

| Path | Purpose |
|------|---------|
| `/opt/better-trails-web/` | Better Trails deployed code |
| `/opt/better-trails-api/` | API deployed code |
| `/opt/better-trails-tiles/` | Tiles infrastructure |
| `/opt/todo-me/` | Todo-Me deployed code |
| `~/better-trails/` | Better Trails git clone |
| `~/todo-me/` | Todo-Me git clone |

## Deployment Workflow

### For Todo-Me Updates

```bash
# 1. SSH to server
# 2. Pull latest code
cd ~/todo-me && git pull

# 3. Run update script (creates backup, rebuilds, health checks)
./scripts/deploy/update-web.sh

# 4. Verify
curl -s https://pmct.work/todo-me/health
```

### For Better Trails Updates

```bash
cd ~/better-trails && git pull
./scripts/deploy/update-web.sh
curl -s https://pmct.work/health
```

## Sudoers Configuration

The user `matt` has passwordless sudo configured in `/etc/sudoers.d/`:

| File | Permissions |
|------|-------------|
| `better-trails` | `systemctl * better-trails-api*`, `journalctl -u better-trails-api*` |
| `better-trails-web` | nginx management for web app |
| `todo-me` | `systemctl * todo-me*`, `systemctl * nginx`, `journalctl -u todo-me*`, `journalctl -u nginx*` |

Docker commands work without sudo (matt is in docker group).

## Security Notes

1. **Password in file:** Always use file-based password (`-f`) not command-line (`-p`)
2. **Clean up:** Remove password files after use
3. **Local network:** Server is on local network (192.168.4.x)
4. **Minimal permissions:** User has limited sudo access
5. **Container isolation:** Each app runs in isolated Docker network

## Error Handling

If connection fails:

1. Check VPN/network connectivity to 192.168.4.x
2. Verify sshpass is installed: `which sshpass`
3. Test password file was created: `cat /tmp/.sshpw`
4. Try with verbose mode for debugging

## Related Skills

- `/deploying-api` - API version management and deployment
- `/creating-migrations` - Database migration procedures

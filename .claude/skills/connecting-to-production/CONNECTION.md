# Production Server Connection Details

## Server Details

| Property | Value |
|----------|-------|
| **Hostname** | 192.168.4.4 |
| **Username** | matt |
| **OS** | Ubuntu 24.04 |
| **Domain** | pmct.work |

## SSH Connection Method

**Always use the password file approach to handle special characters:**

```bash
# Step 1: Create password file (handles special characters properly)
cat <<'PWEOF' > /tmp/.sshpw
PM02$$ub!!
PWEOF
chmod 600 /tmp/.sshpw

# Step 2: Use sshpass with the file
sshpass -f /tmp/.sshpw ssh -o StrictHostKeyChecking=no matt@192.168.4.4 'command here'

# Step 3: Clean up when done
rm -f /tmp/.sshpw
```

**Important:** The password contains `$$` and `!!` which bash interprets as variables/history expansion. Using a heredoc with single-quoted delimiter (`'PWEOF'`) prevents expansion.

## Application URLs

| Application | URL | Status |
|-------------|-----|--------|
| **Better Trails** | https://pmct.work/ | Live |
| **Todo-Me** | https://pmct.work/todo-me/ | Planned |

## Common Commands

### Check All Docker Containers

```bash
sshpass -f /tmp/.sshpw ssh matt@192.168.4.4 'docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"'
```

### Check System Resources

```bash
sshpass -f /tmp/.sshpw ssh matt@192.168.4.4 'df -h / && echo && free -h'
```

### Check Nginx Status

```bash
sshpass -f /tmp/.sshpw ssh matt@192.168.4.4 'systemctl status nginx --no-pager | head -15'
```

### View Nginx Configuration

```bash
sshpass -f /tmp/.sshpw ssh matt@192.168.4.4 'cat /etc/nginx/sites-enabled/pmct.work'
```

### Check Directory Structure

```bash
sshpass -f /tmp/.sshpw ssh matt@192.168.4.4 'ls -la /opt/'
```

## Current Server State

### Deployed Applications (as of exploration)

```
/opt/
├── better-trails-api/    # FastAPI backend (systemd service)
├── better-trails-tiles/  # Tile infrastructure
├── better-trails-web/    # Symfony web app (Docker)
└── todo-me/              # (planned) Todo-Me app
```

### Running Docker Containers

| Container | Port | Purpose |
|-----------|------|---------|
| `better-trails-web` | 127.0.0.1:8082 | Symfony web app |
| `better-trails-web-redis` | - | Session/cache for web |
| `better-trails-redis` | 127.0.0.1:6379 | Redis for API |
| `tiles-martin` | 127.0.0.1:3000 | Vector tile server |
| `tiles-hillshade` | 127.0.0.1:8080 | Raster hillshade tiles |
| `tiles-postgres` | 0.0.0.0:5434 | Tiles database |
| `better-trails-postgres-api` | 0.0.0.0:5433 | API database |

### Reserved Ports for Todo-Me

| Service | Suggested Port |
|---------|---------------|
| `todo-me-web` | 127.0.0.1:8083 |
| `todo-me-postgres` | 127.0.0.1:5435 |
| `todo-me-redis` | (internal only) |

## Nginx Routing

The server uses path-based routing via nginx:

| Path | Backend | Purpose |
|------|---------|---------|
| `/` | 127.0.0.1:8082 | Better Trails web |
| `/api/` | 127.0.0.1:8000 | Better Trails API |
| `/hillshade/` | 127.0.0.1:8080 | Raster tiles |
| `/fonts/` | 127.0.0.1:3000 | Map fonts |
| `/{layer}/...` | 127.0.0.1:3000 | Vector tiles |
| `/todo-me/` | 127.0.0.1:8083 | Todo-Me (planned) |

### Adding Todo-Me to Nginx

The nginx config at `/etc/nginx/sites-available/pmct.work` needs a new location block:

```nginx
# Todo-Me application
location /todo-me/ {
    limit_req zone=web_limit burst=50 nodelay;

    proxy_pass http://127.0.0.1:8083/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Prefix /todo-me;
    proxy_set_header Connection "";

    proxy_connect_timeout 30s;
    proxy_send_timeout 60s;
    proxy_read_timeout 60s;

    proxy_no_cache 1;
    proxy_cache_bypass 1;
}

# Todo-Me static assets
location /todo-me/build/ {
    proxy_pass http://127.0.0.1:8083/build/;
    proxy_http_version 1.1;
    proxy_set_header Connection "";
    add_header Cache-Control "public, max-age=31536000, immutable";
}
```

## Symfony Configuration for Path Prefix

Todo-Me needs to handle the `/todo-me` prefix. Configure in `config/packages/framework.yaml`:

```yaml
framework:
    trusted_proxies: '%env(TRUSTED_PROXIES)%'
    trusted_headers: ['x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto', 'x-forwarded-prefix']
```

And in `.env.prod`:

```
TRUSTED_PROXIES=127.0.0.1
```

## Database Access

### Better Trails API Database

```bash
sshpass -f /tmp/.sshpw ssh matt@192.168.4.4 'docker exec better-trails-postgres-api psql -U postgres -d better_trails -c "SELECT version();"'
```

### Todo-Me Database (when deployed)

```bash
sshpass -f /tmp/.sshpw ssh matt@192.168.4.4 'docker exec todo-me-postgres psql -U postgres -d todo_me -c "SELECT version();"'
```

## Logs

### Nginx Logs

```bash
sshpass -f /tmp/.sshpw ssh matt@192.168.4.4 'tail -50 /var/log/nginx/better-trails-access.log'
sshpass -f /tmp/.sshpw ssh matt@192.168.4.4 'tail -50 /var/log/nginx/better-trails-error.log'
```

### Docker Container Logs

```bash
# Better Trails
sshpass -f /tmp/.sshpw ssh matt@192.168.4.4 'docker logs better-trails-web --tail 50'

# Todo-Me (when deployed)
sshpass -f /tmp/.sshpw ssh matt@192.168.4.4 'docker logs todo-me-web --tail 50'
```

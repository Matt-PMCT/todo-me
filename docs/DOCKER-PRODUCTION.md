# Docker Production Deployment Guide

This document describes how to deploy the todo-me application using Docker in production environments.

## Overview

The application uses Docker Compose with two configuration files:

- `docker/docker-compose.yml` - Base configuration for development
- `docker/docker-compose.prod.yml` - Production overrides

## Security Features

### Development vs Production

| Feature | Development | Production |
|---------|-------------|------------|
| PostgreSQL port | Localhost only (127.0.0.1:5432) | Internal network only |
| Redis port | Localhost only (127.0.0.1:6379) | Internal network only |
| Redis authentication | Disabled | Required |
| OPcache validation | Enabled | Disabled |
| Resource limits | None | Configured |
| Health checks | Basic | Comprehensive |

### Port Binding

**Development:** Database and Redis ports are bound to localhost (127.0.0.1) to prevent external access while allowing local development tools.

**Production:** Ports are not exposed to the host at all. Services communicate only through the internal Docker network.

### Redis Authentication

In production, Redis requires password authentication:

```bash
# Set the Redis password in your environment
export REDIS_PASSWORD="your-secure-password"
```

Update your application's Redis connection string:
```
REDIS_URL="redis://:${REDIS_PASSWORD}@redis:6379"
```

## Deployment Steps

### 1. Prepare Environment Variables

Create a `.env.docker` file in the `docker/` directory with production values:

```bash
# Database
POSTGRES_DB=todo_db
POSTGRES_USER=todo_user
POSTGRES_PASSWORD=<strong-password>

# Redis
REDIS_PASSWORD=<strong-password>

# Application
APP_ENV=prod
APP_SECRET=<generated-secret>
CORS_ALLOW_ORIGIN='^https://your-domain\.com$'
```

### 2. Start Services

```bash
cd docker/
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

### 3. Verify Health

```bash
# Check all services are healthy
docker-compose ps

# Check individual health
docker inspect --format='{{.State.Health.Status}}' todo-postgres
docker inspect --format='{{.State.Health.Status}}' todo-redis
```

### 4. Run Migrations

```bash
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

## Resource Limits

Production configuration includes resource limits to prevent runaway containers:

| Service | CPU Limit | Memory Limit | Memory Reservation |
|---------|-----------|--------------|-------------------|
| PHP | 2 cores | 512MB | 256MB |
| Nginx | 1 core | 256MB | 128MB |
| PostgreSQL | 2 cores | 1GB | 512MB |
| Redis | 1 core | 512MB | 256MB |

Adjust these values based on your server capacity and expected load.

## Health Checks

All services have health checks configured:

### PostgreSQL
- Command: `pg_isready`
- Interval: 10 seconds
- Retries: 5

### Redis
- Command: `redis-cli ping` (with auth in production)
- Interval: 10 seconds
- Retries: 5

### PHP-FPM
- Requires php-fpm-healthcheck script
- Interval: 30 seconds
- Start period: 30 seconds

### Nginx
- HTTP check on `/health` endpoint
- Interval: 30 seconds

## Secrets Management

For enhanced security, consider using Docker secrets instead of environment variables:

```yaml
# docker-compose.prod.yml
secrets:
  db_password:
    file: ./secrets/db_password.txt
  redis_password:
    file: ./secrets/redis_password.txt

services:
  db:
    secrets:
      - db_password
    environment:
      POSTGRES_PASSWORD_FILE: /run/secrets/db_password
```

Create secret files:
```bash
mkdir -p docker/secrets
echo "your-db-password" > docker/secrets/db_password.txt
echo "your-redis-password" > docker/secrets/redis_password.txt
chmod 600 docker/secrets/*
```

## Troubleshooting

### Database Connection Issues

If the application can't connect to PostgreSQL:

1. Verify PostgreSQL is healthy:
   ```bash
   docker-compose exec db pg_isready -U todo_user
   ```

2. Check environment variables:
   ```bash
   docker-compose exec php printenv | grep DATABASE
   ```

### Redis Connection Issues

1. Verify Redis is healthy:
   ```bash
   docker-compose exec redis redis-cli ping
   ```

2. In production, include password:
   ```bash
   docker-compose exec redis redis-cli -a "${REDIS_PASSWORD}" ping
   ```

### Memory Issues

If containers are being killed (OOM):

1. Check current memory usage:
   ```bash
   docker stats
   ```

2. Increase limits in `docker-compose.prod.yml`

## Monitoring

Consider adding monitoring tools:

- **Prometheus/Grafana** for metrics
- **ELK Stack** for log aggregation
- **Sentry** for error tracking

Example Prometheus configuration:
```yaml
  prometheus:
    image: prom/prometheus:latest
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
    networks:
      - todo-network
```

## Backup Strategy

### PostgreSQL Backups

```bash
# Create backup
docker-compose exec db pg_dump -U todo_user todo_db > backup.sql

# Restore from backup
docker-compose exec -T db psql -U todo_user todo_db < backup.sql
```

### Redis Persistence

Redis is configured with AOF persistence (`appendonly yes`). Data is stored in the `redis_data` volume.

For backups:
```bash
docker-compose exec redis redis-cli BGSAVE
docker cp todo-redis:/data/dump.rdb ./backup/
```

## SSL/TLS Configuration

For production, add SSL termination. Options include:

1. **Reverse Proxy (recommended):** Use nginx/traefik in front with SSL certificates
2. **Load Balancer:** AWS ALB, CloudFlare, etc.
3. **Direct SSL:** Configure nginx container with certificates

Example with Traefik:
```yaml
  traefik:
    image: traefik:v2.10
    command:
      - "--providers.docker=true"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.myresolver.acme.tlschallenge=true"
    ports:
      - "443:443"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
```

## Checklist

Before going live, verify:

- [ ] All environment variables set correctly
- [ ] Database password is strong
- [ ] Redis password is strong
- [ ] APP_SECRET is unique and random
- [ ] CORS_ALLOW_ORIGIN is properly configured
- [ ] Ports are not exposed to public network
- [ ] Health checks are passing
- [ ] Backups are configured
- [ ] SSL/TLS is enabled
- [ ] Monitoring is set up
- [ ] Log aggregation is configured

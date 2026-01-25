# Monitoring Guide

This guide covers monitoring setup for the todo-me application.

## Health Checks

### Endpoints

The application provides three health check endpoints:

| Endpoint | Purpose | Auth Required |
|----------|---------|---------------|
| `/api/v1/health` | Full health check (DB + Redis) | No |
| `/api/v1/health/live` | Liveness probe | No |
| `/api/v1/health/ready` | Readiness probe (DB only) | No |

### Full Health Check

```bash
curl http://localhost:8080/api/v1/health
```

Response (healthy):
```json
{
  "status": "healthy",
  "services": {
    "database": "healthy",
    "redis": "healthy"
  },
  "timestamp": "2026-01-24T12:00:00+00:00"
}
```

Response (unhealthy - returns HTTP 503):
```json
{
  "status": "unhealthy",
  "services": {
    "database": "healthy",
    "redis": "unhealthy"
  },
  "timestamp": "2026-01-24T12:00:00+00:00"
}
```

### Kubernetes/Container Probes

For container orchestration:

```yaml
# Example Kubernetes deployment
spec:
  containers:
    - name: todo-me
      livenessProbe:
        httpGet:
          path: /api/v1/health/live
          port: 80
        initialDelaySeconds: 10
        periodSeconds: 10
      readinessProbe:
        httpGet:
          path: /api/v1/health/ready
          port: 80
        initialDelaySeconds: 5
        periodSeconds: 5
```

## Error Tracking with Sentry

### Setup

1. Create a Sentry account at https://sentry.io
2. Create a new PHP project
3. Copy the DSN

4. Configure the application:
   ```bash
   # In .env.local or environment
   SENTRY_DSN="https://your-key@sentry.io/project-id"
   ```

5. Install the Sentry bundle (if not already installed):
   ```bash
   composer require sentry/sentry-symfony
   ```

### Configuration

The Sentry configuration is in `config/packages/sentry.yaml`:

```yaml
when@prod:
    sentry:
        dsn: '%env(default::SENTRY_DSN)%'
        options:
            sample_rate: 1.0          # Capture 100% of errors
            traces_sample_rate: 0.1   # Capture 10% of performance traces
            environment: '%kernel.environment%'
            send_default_pii: false   # Privacy: don't send user data
```

### What Gets Captured

- All uncaught exceptions
- Fatal errors
- Performance traces (configurable sampling)
- Request context (URL, method, headers - excluding sensitive data)

### Privacy Considerations

By default, Sentry is configured to NOT send personally identifiable information:
- User emails are not sent
- Request bodies are not logged
- Sensitive headers are filtered

To enable user tracking (with consent):
```yaml
sentry:
    options:
        send_default_pii: true
```

## Logging

### Log Files

Logs are written to:
- `var/log/dev.log` (development)
- `var/log/prod.log` (production)

### Log Levels

Configure in `config/packages/monolog.yaml`:

```yaml
when@prod:
    monolog:
        handlers:
            main:
                type: fingers_crossed
                action_level: error
                handler: nested
            nested:
                type: stream
                path: "%kernel.logs_dir%/%kernel.environment%.log"
                level: debug
```

### Structured Logging

The application uses structured JSON logging for important events:

```php
$this->logger->info('User logged in', [
    'user_id' => $user->getId(),
    'ip' => $request->getClientIp(),
]);
```

## Metrics

### Application Metrics

Track these key metrics:

| Metric | Description | Alert Threshold |
|--------|-------------|-----------------|
| Response time (p95) | 95th percentile latency | > 500ms |
| Error rate | % of 5xx responses | > 1% |
| Request rate | Requests per second | Varies |
| Database connections | Active connections | > 80% of pool |
| Redis memory | Memory usage | > 80% of limit |

### Prometheus Integration

For Prometheus monitoring, add the prometheus bundle:

```bash
composer require artprima/prometheus-metrics-bundle
```

Example Prometheus scrape config:
```yaml
scrape_configs:
  - job_name: 'todo-me'
    static_configs:
      - targets: ['localhost:8080']
    metrics_path: '/metrics'
```

## Alerting

### Recommended Alerts

1. **Service Health**
   - Alert when `/api/v1/health` returns 503
   - Alert when any service is unhealthy for > 1 minute

2. **Error Rate**
   - Alert when error rate > 1% over 5 minutes
   - Alert when error rate > 5% over 1 minute

3. **Latency**
   - Alert when p95 latency > 1 second
   - Alert when p99 latency > 2 seconds

4. **Resource Usage**
   - Alert when CPU > 80% for > 5 minutes
   - Alert when memory > 80%
   - Alert when disk > 80%

### Example Alert Rules (Prometheus)

```yaml
groups:
  - name: todo-me
    rules:
      - alert: ServiceUnhealthy
        expr: probe_success{job="todo-me-health"} == 0
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "todo-me service is unhealthy"

      - alert: HighErrorRate
        expr: rate(http_requests_total{status=~"5.."}[5m]) / rate(http_requests_total[5m]) > 0.01
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High error rate detected"
```

## Dashboard

### Recommended Dashboard Panels

1. **Service Status**
   - Health check status (up/down)
   - Uptime percentage

2. **Traffic**
   - Requests per second
   - Request breakdown by endpoint
   - Geographic distribution (if applicable)

3. **Performance**
   - Response time histogram
   - Latency percentiles (p50, p95, p99)
   - Slow queries

4. **Errors**
   - Error rate over time
   - Error breakdown by type
   - Recent errors list

5. **Resources**
   - CPU usage
   - Memory usage
   - Database connections
   - Redis memory

## Troubleshooting

### Common Issues

**Health check fails intermittently**
- Check database connection pool limits
- Check Redis connection timeouts
- Verify network connectivity between containers

**High latency**
- Check slow query log
- Verify indexes are being used
- Check Redis cache hit rate

**Memory issues**
- Check for memory leaks in long-running processes
- Verify OPcache settings
- Check entity manager for unclosed connections

### Debug Commands

```bash
# Check database connectivity
docker compose exec php php bin/console doctrine:query:sql "SELECT 1"

# Check Redis connectivity
docker compose exec redis redis-cli ping

# View recent logs
docker compose logs --tail=100 php

# Check PHP-FPM status
docker compose exec php php-fpm-healthcheck
```

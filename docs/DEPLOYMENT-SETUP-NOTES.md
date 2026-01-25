# Deployment Setup Notes

## Known Gaps in Docker Compose Setup

### Issue: Missing Post-Deployment Steps

**Status:** IDENTIFIED - Phase 14 Production Validation

The Docker Compose files (`docker-compose.yml` and `docker-compose.prod.yml`) do not include post-deployment initialization steps that must be run after containers start:

1. **Composer Install** - PHP dependencies must be installed
   ```bash
   docker compose exec -T php composer install
   ```
   **Issue:** DebugBundle is a dev dependency needed for console scripts, so `--no-dev` cannot be used.
   **Action:** Either include dev dependencies or update Dockerfile to install Composer packages during image build.

2. **Database Migrations** - Schema must be initialized
   ```bash
   docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction
   ```
   **Execution time:** ~500ms, 23 migrations executed (as of Phase 13)

3. **Cache Operations** - Symfony cache must be cleared and warmed
   ```bash
   docker compose exec -T php php bin/console cache:clear --env=prod
   docker compose exec -T php php bin/console cache:warmup --env=prod
   ```

### Why Not In Docker Compose

These steps are intentionally separate from the compose configuration because:

1. **Image Build vs Runtime Initialization**: Composer install is an image-time concern (should be in Dockerfile), while migrations are database-state concerns (post-deployment).

2. **Deployment Orchestration**: Better handled by deployment scripts or orchestration tools (Kubernetes init containers, systemd services, deployment hooks).

3. **Idempotency**: These commands should be re-runnable without side effects, making them suitable for deployment scripts rather than compose hooks.

### Recommended Solutions

#### Short Term (Current)
- Manually run setup steps after deploying (as documented in deployment scripts)
- Document in deployment procedures

#### Medium Term
- Create a dedicated setup script: `scripts/deploy/initialize-db.sh`
- Add to deployment procedures after container startup

#### Long Term
- Update Dockerfile to include `composer install` during image build
- Add database initialization to image build if using Docker image tagging strategy
- Use Kubernetes or other orchestration for init container patterns

## Production Deployment Checklist

See: `docs/PHASE-14-PRODUCTION-VALIDATION-PLAN.md`

### Environment Configuration
- [x] `.env.prod` created with SMTP configuration
- [x] `config/packages/framework.yaml` updated for reverse proxy trust headers
- [x] Docker containers built and running
- [x] Database migrations executed
- [x] Cache warmed

### Nginx Configuration
- [ ] Todo-Me location blocks added to `/etc/nginx/sites-available/pmct.work`
- [ ] Nginx configuration tested
- [ ] Nginx reloaded

### Health Checks
- [x] Web health endpoint responds
- [ ] API health endpoint fully healthy (Redis config needed)
- [x] Database healthy
- [x] Redis healthy

## Notes for Future Phases

1. **Dockerfile Optimization**: Add composer install to Dockerfile to eliminate runtime dependency
2. **Environment Secrets**: Consider using Docker secrets for credentials instead of .env files
3. **Redis Configuration**: Verify Redis AUTH password is properly configured in PHP container
4. **Deployment Script**: Create `scripts/deploy/setup-production.sh` for one-command initialization

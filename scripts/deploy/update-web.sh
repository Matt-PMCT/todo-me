#!/bin/bash
#
# Todo-Me Web Application Update Script
#
# Updates the production web application with the latest code.
# Run this script from the ~/todo-me directory on the production server.
#
# Usage: ./scripts/deploy/update-web.sh [--no-backup] [--quick | --full]
#
# Deploy modes:
#   (default)    Fast deploy: builds with the Docker layer cache (seconds when nothing in the
#                Dockerfiles/build context changed) and only recreates containers whose image or
#                config changed. Automatically becomes a full rebuild when the last full rebuild
#                is older than FULL_REBUILD_DAYS (default 14) or has never been recorded, so OS
#                security patches in the base images and apt layers are still picked up.
#   --full       Force a full rebuild now: pulls fresh base images and builds with --no-cache.
#   --quick      Code-only deploy: skip the build and container restarts entirely.
#
# Options:
#   --no-backup  Skip creating a backup before update
#
# Before changing anything it records the running image IDs to backups/images-before-*.txt
# (rollback info) and warns if any image is older than STALE_DAYS (default 30).
#
# Environment:
#   APP_URL             Public URL shown in the final "Verify at" message
#                       (default: http://localhost:8083/)
#   FULL_REBUILD_DAYS   Max age of the last full rebuild before one is forced (default: 14)
#

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
DOCKER_DIR="$PROJECT_DIR/docker"
COMPOSE_CMD="docker compose -f docker-compose.yml -f docker-compose.prod.yml"

# Color codes
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Parse arguments
SKIP_BACKUP=false
QUICK_UPDATE=false
FORCE_FULL=false
while [[ $# -gt 0 ]]; do
    case $1 in
        --no-backup)
            SKIP_BACKUP=true
            shift
            ;;
        --quick)
            QUICK_UPDATE=true
            shift
            ;;
        --full)
            FORCE_FULL=true
            shift
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

if [ "$QUICK_UPDATE" = true ] && [ "$FORCE_FULL" = true ]; then
    echo -e "${RED}--quick and --full cannot be combined${NC}"
    exit 1
fi

log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Change to project directory
cd "$PROJECT_DIR"

echo ""
echo "=========================================="
echo "Todo-Me Web Application Update"
echo "=========================================="
echo ""

# Step 1: Create backup (unless skipped)
if [ "$SKIP_BACKUP" = false ]; then
    log "Creating backup..."
    if [ -f "$SCRIPT_DIR/../backup.sh" ]; then
        bash "$SCRIPT_DIR/../backup.sh" || error "Backup failed - aborting deploy (re-run with --no-backup to skip)"
    else
        error "Backup script not found - aborting deploy (re-run with --no-backup to skip)"
    fi
else
    log "Skipping backup (--no-backup flag set)"
fi

# Record running image IDs (for rollback) and warn about stale images
mkdir -p "$PROJECT_DIR/backups"
IMAGE_RECORD="$PROJECT_DIR/backups/images-before-$(date +%Y%m%d_%H%M%S).txt"
bash "$SCRIPT_DIR/../image-status.sh" --record "$IMAGE_RECORD" || warning "Could not record current image IDs"
if ! bash "$SCRIPT_DIR/../image-status.sh" --check; then
    if [ "$QUICK_UPDATE" = true ]; then
        warning "Images are stale and --quick skips the rebuild. Consider a deploy without --quick (or --full)."
    fi
fi

# Step 2: Pull latest code
log "Pulling latest code from git..."
git fetch origin
BEFORE_HASH=$(git rev-parse HEAD)
git pull origin main
AFTER_HASH=$(git rev-parse HEAD)

if [ "$BEFORE_HASH" = "$AFTER_HASH" ]; then
    warning "No new commits to deploy"
    if [ "$QUICK_UPDATE" = true ]; then
        log "Quick update requested, clearing cache only..."
    fi
else
    log "Updated from ${BEFORE_HASH:0:7} to ${AFTER_HASH:0:7}"
    # Show what changed
    echo ""
    git log --oneline "$BEFORE_HASH..$AFTER_HASH"
    echo ""
fi

# PHP-FPM workers run as the "www" user, whose uid/gid must match the host owner of this
# checkout (the project is bind-mounted), otherwise workers cannot write var/log or var/cache.
export WWW_UID="$(id -u)" WWW_GID="$(id -g)"
if [ "$WWW_UID" -eq 0 ]; then
    export WWW_UID=1000 WWW_GID=1000
fi
mkdir -p "$PROJECT_DIR/var/cache" "$PROJECT_DIR/var/log"

# Web port exposure is chosen once per server and remembered in docker/.env (read by Compose as
# WEB_BIND). 127.0.0.1 = only a reverse proxy on this host can reach the app; 0.0.0.0 = whole LAN.
# Existing servers are never changed silently: without a TTY we only print a notice.
WEB_BIND_CHANGED=false
if ! grep -q '^WEB_BIND=' "$PROJECT_DIR/docker/.env" 2>/dev/null; then
    if [ -t 0 ]; then
        echo ""
        echo "Port 8083 (web) can be reachable from the whole network, or only from this machine"
        echo "(use 'local' when a reverse proxy such as nginx/Cloudflare runs on this host)."
        read -r -p "Expose port 8083 to [local/all]? (default: local) " WEB_BIND_CHOICE
        case "$WEB_BIND_CHOICE" in
            all|a|A) WEB_BIND_VALUE=0.0.0.0 ;;
            *) WEB_BIND_VALUE=127.0.0.1 ;;
        esac
        echo "WEB_BIND=$WEB_BIND_VALUE" >> "$PROJECT_DIR/docker/.env"
        WEB_BIND_CHANGED=true
        log "Saved WEB_BIND=$WEB_BIND_VALUE to docker/.env (edit that file to change it later)"
    else
        warning "WEB_BIND is not set in docker/.env, so port 8083 stays exposed on all interfaces. Run this script interactively once to choose."
    fi
fi

# Change to docker directory for compose commands
cd "$DOCKER_DIR"

# Step 3: Decide how to build. A full (no-cache, freshly pulled) rebuild is the only way to pick up
# OS package fixes baked into cached layers, so it happens on request (--full) or automatically
# once the last one is older than FULL_REBUILD_DAYS. Everything else uses the Docker layer cache,
# which is itself the change detector (Dockerfile, build context, build args).
FULL_REBUILD_DAYS="${FULL_REBUILD_DAYS:-14}"
FULL_BUILD_STAMP="$PROJECT_DIR/backups/.last-full-build"

choose_build_mode() {
    if [ "$QUICK_UPDATE" = true ]; then
        BUILD_MODE=quick
        BUILD_REASON="--quick requested"
    elif [ "$FORCE_FULL" = true ]; then
        BUILD_MODE=full
        BUILD_REASON="--full requested"
    elif ! LAST_FULL=$(cat "$FULL_BUILD_STAMP" 2>/dev/null) || ! [[ "$LAST_FULL" =~ ^[0-9]+$ ]]; then
        BUILD_MODE=full
        BUILD_REASON="no record of a previous full rebuild"
    elif [ $(( ($(date +%s) - LAST_FULL) / 86400 )) -ge "$FULL_REBUILD_DAYS" ]; then
        BUILD_MODE=full
        BUILD_REASON="last full rebuild was $(( ($(date +%s) - LAST_FULL) / 86400 )) days ago (limit: $FULL_REBUILD_DAYS)"
    else
        BUILD_MODE=fast
        BUILD_REASON="last full rebuild was $(( ($(date +%s) - LAST_FULL) / 86400 )) days ago (limit: $FULL_REBUILD_DAYS)"
    fi
}
choose_build_mode

# Step 3: Build and (re)start containers
if [ "$BUILD_MODE" = quick ]; then
    log "Quick update mode - skipping container rebuild"
    if [ "$WEB_BIND_CHANGED" = true ]; then
        log "Applying the new web port binding..."
        $COMPOSE_CMD up -d web
    fi
else
    if [ "$BUILD_MODE" = full ]; then
        log "Full rebuild ($BUILD_REASON): pulling fresh base images and building without cache..."
        $COMPOSE_CMD pull web db redis
        $COMPOSE_CMD build --pull --no-cache
    else
        log "Fast deploy ($BUILD_REASON): building with the layer cache..."
        $COMPOSE_CMD build
    fi

    log "Starting containers (only those whose image or config changed are recreated)..."
    $COMPOSE_CMD up -d

    if [ "$BUILD_MODE" = full ]; then
        date +%s > "$FULL_BUILD_STAMP"
        sleep 5
    fi

    # Wait for containers to be healthy
    log "Waiting for containers to be healthy..."
    RETRIES=30
    while [ $RETRIES -gt 0 ]; do
        PHP_HEALTH=$(docker inspect --format='{{.State.Health.Status}}' todo-php 2>/dev/null || echo "unknown")
        if [ "$PHP_HEALTH" = "healthy" ]; then
            break
        fi
        echo -n "."
        sleep 2
        ((RETRIES--))
    done
    echo ""

    if [ "$PHP_HEALTH" != "healthy" ]; then
        warning "PHP container health check timed out, continuing anyway"
    fi
fi

# Run app commands as the same user as the PHP-FPM workers so generated files (cache, logs) are
# writable by them. Images built before the pool-user change still run workers as www-data, so
# keep the legacy behaviour (root) until the next full deploy rebuilds the image.
if $COMPOSE_CMD exec -T php test -f /usr/local/etc/php-fpm.d/zz-www-user.conf; then
    EXEC_USER=www
    # Idempotent self-heal for existing servers: earlier deploys ran as root and left root-owned files
    log "Ensuring var/ and build output are owned by the PHP-FPM user..."
    # The php service runs with cap_drop ALL (no CAP_CHOWN), so repair ownership in a one-shot
    # container that gets CHOWN only for this step. Only mis-owned files are touched, so this is a
    # silent no-op once everything belongs to www.
    if ! $COMPOSE_CMD run --rm --no-deps -T -u root --cap-add CHOWN --entrypoint sh php -c \
        'rc=0; for d in var vendor public/assets public/build; do [ -e "$d" ] || continue; find "$d" ! -user www -exec chown www:www {} + || rc=1; done; exit $rc'; then
        warning "Could not fix file ownership of var/, vendor/ or public/ - composer or cache steps may fail. Fix on the host: sudo chown -R $(id -un):$(id -gn) var vendor public/assets public/build"
    fi
else
    EXEC_USER=root
    warning "PHP image predates the www worker user; run a full deploy (no --quick) to rebuild it"
fi
APP_EXEC="$COMPOSE_CMD exec -T -u $EXEC_USER php"

# Step 4: Run composer install only when needed: dependency files changed, vendor/ is missing,
# a full rebuild just happened, or there were no new commits (re-run after a failed deploy).
NEED_COMPOSER=false
if [ "$BUILD_MODE" = full ] || [ ! -f "$PROJECT_DIR/vendor/autoload.php" ]; then
    NEED_COMPOSER=true
elif git diff --name-only "$BEFORE_HASH" "$AFTER_HASH" | grep -qE '^composer\.(json|lock)$'; then
    NEED_COMPOSER=true
elif [ "$BEFORE_HASH" = "$AFTER_HASH" ] && [ "$BUILD_MODE" != quick ]; then
    NEED_COMPOSER=true
fi

if [ "$NEED_COMPOSER" = true ]; then
    log "Installing/updating dependencies..."
    $APP_EXEC composer install --no-dev --optimize-autoloader --no-interaction
else
    log "Dependencies unchanged - skipping composer install"
fi

# Step 5: Run database migrations
log "Running database migrations..."
$APP_EXEC php bin/console doctrine:migrations:migrate --no-interaction --env=prod || warning "No migrations to run"

# Step 6: Compile assets
log "Compiling asset map..."
$APP_EXEC php bin/console asset-map:compile --env=prod

# Step 7: Clear cache
log "Clearing production cache..."
$APP_EXEC php bin/console cache:clear --env=prod

# Step 8: Warm up cache
log "Warming up cache..."
$APP_EXEC php bin/console cache:warmup --env=prod

# Step 9: Health check
log "Running health check..."
cd "$PROJECT_DIR"

# Check via curl if available
HEALTH_URL="http://localhost:8083/"
if curl -sf "$HEALTH_URL" > /dev/null 2>&1; then
    success "Application is responding"
else
    warning "Could not verify application health via HTTP"
fi

# Confirm image freshness after a full rebuild
if [ "$BUILD_MODE" = full ]; then
    bash "$SCRIPT_DIR/../image-status.sh" --check || warning "Some images are still older than expected"
fi

# Show container status
echo ""
log "Container status:"
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" | grep todo

echo ""
echo "=========================================="
success "Deployment complete!"
echo "=========================================="
echo ""
echo "Deployed commit: $(git rev-parse --short HEAD)"
echo "Commit message:  $(git log -1 --pretty=%s)"
echo ""
echo "Verify at: ${APP_URL:-http://localhost:8083/}"
echo ""

#!/bin/bash
#
# Todo-Me Web Application Update Script
#
# Updates the production web application with the latest code.
# Run this script from the ~/todo-me directory on the production server.
#
# Usage: ./scripts/deploy/update-web.sh [--no-backup] [--quick]
#
# Options:
#   --no-backup  Skip creating a backup before update
#   --quick      Code-only deploy: skip rebuild, just update code and clear cache
#                (no flag = full deploy: pulls fresh base images and rebuilds)
#
# Environment:
#   APP_URL      Public URL shown in the final "Verify at" message
#                (default: http://localhost:8083/)
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
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

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

# Change to docker directory for compose commands
cd "$DOCKER_DIR"

# Step 3: Rebuild containers (unless quick update)
if [ "$QUICK_UPDATE" = false ]; then
    log "Pulling latest base images..."
    $COMPOSE_CMD pull web db redis

    log "Rebuilding Docker containers..."
    $COMPOSE_CMD build --pull --no-cache

    log "Restarting containers..."
    $COMPOSE_CMD up -d

    # Wait for containers to be healthy
    log "Waiting for containers to be healthy..."
    sleep 5

    # Check health status
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
else
    log "Quick update mode - skipping container rebuild"
fi

# Step 4: Run composer install (in case dependencies changed)
if [ "$QUICK_UPDATE" = false ]; then
    log "Installing/updating dependencies..."
    $COMPOSE_CMD exec -T php composer install --no-dev --optimize-autoloader --no-interaction
fi

# Step 5: Run database migrations
log "Running database migrations..."
$COMPOSE_CMD exec -T php php bin/console doctrine:migrations:migrate --no-interaction --env=prod || warning "No migrations to run"

# Step 6: Compile assets
log "Compiling asset map..."
$COMPOSE_CMD exec -T php php bin/console asset-map:compile --env=prod

# Step 7: Clear cache
log "Clearing production cache..."
$COMPOSE_CMD exec -T php php bin/console cache:clear --env=prod

# Step 8: Warm up cache
log "Warming up cache..."
$COMPOSE_CMD exec -T php php bin/console cache:warmup --env=prod

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

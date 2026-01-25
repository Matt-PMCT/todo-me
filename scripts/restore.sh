#!/bin/bash
#
# Restore Script for todo-me
#
# Restores PostgreSQL database and optionally Redis data from backups.
#
# Usage: ./scripts/restore.sh <backup_timestamp>
# Example: ./scripts/restore.sh 20260124_120000
#
# The script will look for:
#   - backups/postgres_<timestamp>.sql.gz
#   - backups/redis_<timestamp>.rdb.gz

set -e

# Configuration
BACKUP_DIR="${BACKUP_DIR:-./backups}"
DOCKER_COMPOSE="docker compose -f docker/docker-compose.yml"

# Colors
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
NC='\033[0m'

# Check arguments
if [ -z "$1" ]; then
    echo "Usage: $0 <backup_timestamp>"
    echo ""
    echo "Available backups:"
    ls -1 "$BACKUP_DIR"/manifest_*.json 2>/dev/null | sed 's/.*manifest_/  /' | sed 's/.json//' || echo "  No backups found"
    exit 1
fi

TIMESTAMP="$1"
POSTGRES_BACKUP="$BACKUP_DIR/postgres_${TIMESTAMP}.sql.gz"
REDIS_BACKUP="$BACKUP_DIR/redis_${TIMESTAMP}.rdb.gz"
MANIFEST="$BACKUP_DIR/manifest_${TIMESTAMP}.json"

echo "=========================================="
echo "todo-me Restore"
echo "Restoring from: $TIMESTAMP"
echo "=========================================="

# Verify backup files exist
if [ ! -f "$POSTGRES_BACKUP" ]; then
    echo -e "${RED}Error: PostgreSQL backup not found:${NC} $POSTGRES_BACKUP"
    exit 1
fi

echo -e "${GREEN}Found PostgreSQL backup:${NC} $POSTGRES_BACKUP"

if [ -f "$REDIS_BACKUP" ]; then
    echo -e "${GREEN}Found Redis backup:${NC} $REDIS_BACKUP"
else
    echo -e "${YELLOW}Redis backup not found, skipping Redis restore${NC}"
fi

if [ -f "$MANIFEST" ]; then
    echo ""
    echo "Backup manifest:"
    cat "$MANIFEST"
fi

# Confirm restore
echo ""
echo -e "${YELLOW}WARNING: This will overwrite the current database!${NC}"
read -p "Are you sure you want to continue? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Restore cancelled"
    exit 0
fi

# Restore PostgreSQL
restore_postgres() {
    echo ""
    echo "Restoring PostgreSQL..."

    # Drop existing connections
    $DOCKER_COMPOSE exec -T db psql -U todo_user -d postgres -c "
        SELECT pg_terminate_backend(pg_stat_activity.pid)
        FROM pg_stat_activity
        WHERE pg_stat_activity.datname = 'todo_db'
        AND pid <> pg_backend_pid();
    " 2>/dev/null || true

    # Drop and recreate database
    echo "Recreating database..."
    $DOCKER_COMPOSE exec -T db psql -U todo_user -d postgres -c "DROP DATABASE IF EXISTS todo_db;"
    $DOCKER_COMPOSE exec -T db psql -U todo_user -d postgres -c "CREATE DATABASE todo_db;"

    # Restore from backup
    echo "Restoring data..."
    gunzip -c "$POSTGRES_BACKUP" | $DOCKER_COMPOSE exec -T db psql -U todo_user -d todo_db

    echo -e "${GREEN}PostgreSQL restored successfully${NC}"
}

# Restore Redis
restore_redis() {
    if [ ! -f "$REDIS_BACKUP" ]; then
        return
    fi

    echo ""
    echo "Restoring Redis..."

    # Stop Redis
    $DOCKER_COMPOSE stop redis

    # Copy backup file
    gunzip -c "$REDIS_BACKUP" > /tmp/dump.rdb
    $DOCKER_COMPOSE cp /tmp/dump.rdb redis:/data/dump.rdb
    rm /tmp/dump.rdb

    # Start Redis
    $DOCKER_COMPOSE start redis

    # Wait for Redis to load data
    sleep 3

    echo -e "${GREEN}Redis restored successfully${NC}"
}

# Clear application cache
clear_cache() {
    echo ""
    echo "Clearing application cache..."

    $DOCKER_COMPOSE exec -T php php bin/console cache:clear --no-warmup 2>/dev/null || true
    $DOCKER_COMPOSE exec -T php php bin/console cache:warmup 2>/dev/null || true

    echo "Cache cleared"
}

# Run restore
restore_postgres
restore_redis
clear_cache

echo ""
echo "=========================================="
echo -e "${GREEN}Restore completed successfully${NC}"
echo "=========================================="

# Verify
echo ""
echo "Verifying restore..."
$DOCKER_COMPOSE exec -T db psql -U todo_user -d todo_db -c "SELECT COUNT(*) as task_count FROM task;" 2>/dev/null || echo "Could not verify task count"

echo ""
echo "Done. Please verify the application is working correctly."

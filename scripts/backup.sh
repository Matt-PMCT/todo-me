#!/bin/bash
#
# Backup Script for todo-me
#
# Creates backups of PostgreSQL database and Redis data.
# Backups are compressed and timestamped.
#
# Usage: ./scripts/backup.sh [backup_dir]

set -e

# Configuration
BACKUP_DIR="${1:-./backups}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DOCKER_COMPOSE="docker compose -f docker/docker-compose.yml"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

echo "=========================================="
echo "todo-me Backup"
echo "Timestamp: $TIMESTAMP"
echo "=========================================="

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Backup PostgreSQL
backup_postgres() {
    echo ""
    echo "Backing up PostgreSQL..."

    POSTGRES_BACKUP="$BACKUP_DIR/postgres_${TIMESTAMP}.sql.gz"

    if $DOCKER_COMPOSE exec -T db pg_dump -U todo_user todo_db | gzip > "$POSTGRES_BACKUP"; then
        echo -e "${GREEN}PostgreSQL backup created:${NC} $POSTGRES_BACKUP"
        ls -lh "$POSTGRES_BACKUP"
    else
        echo -e "${RED}PostgreSQL backup failed${NC}"
        return 1
    fi
}

# Backup Redis
backup_redis() {
    echo ""
    echo "Backing up Redis..."

    REDIS_BACKUP="$BACKUP_DIR/redis_${TIMESTAMP}.rdb"

    # Trigger Redis background save
    $DOCKER_COMPOSE exec -T redis redis-cli BGSAVE

    # Wait for save to complete
    sleep 2

    # Copy the dump file
    if $DOCKER_COMPOSE cp redis:/data/dump.rdb "$REDIS_BACKUP" 2>/dev/null; then
        gzip "$REDIS_BACKUP"
        echo -e "${GREEN}Redis backup created:${NC} ${REDIS_BACKUP}.gz"
        ls -lh "${REDIS_BACKUP}.gz"
    else
        echo "Redis backup skipped (no data or container not running)"
    fi
}

# Create manifest
create_manifest() {
    echo ""
    echo "Creating backup manifest..."

    MANIFEST="$BACKUP_DIR/manifest_${TIMESTAMP}.json"

    cat > "$MANIFEST" << EOF
{
    "timestamp": "$TIMESTAMP",
    "date": "$(date -Iseconds)",
    "version": "$(cat VERSION 2>/dev/null || echo 'unknown')",
    "files": {
        "postgres": "postgres_${TIMESTAMP}.sql.gz",
        "redis": "redis_${TIMESTAMP}.rdb.gz"
    }
}
EOF

    echo -e "${GREEN}Manifest created:${NC} $MANIFEST"
}

# Cleanup old backups (keep last 7 days)
cleanup_old_backups() {
    echo ""
    echo "Cleaning up old backups..."

    find "$BACKUP_DIR" -name "postgres_*.sql.gz" -mtime +7 -delete 2>/dev/null || true
    find "$BACKUP_DIR" -name "redis_*.rdb.gz" -mtime +7 -delete 2>/dev/null || true
    find "$BACKUP_DIR" -name "manifest_*.json" -mtime +7 -delete 2>/dev/null || true

    echo "Old backups cleaned up (keeping last 7 days)"
}

# Run backup
backup_postgres
backup_redis
create_manifest
cleanup_old_backups

echo ""
echo "=========================================="
echo -e "${GREEN}Backup completed successfully${NC}"
echo "Backup directory: $BACKUP_DIR"
echo "=========================================="

# List recent backups
echo ""
echo "Recent backups:"
ls -lt "$BACKUP_DIR" | head -10

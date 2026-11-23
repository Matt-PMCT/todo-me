#!/bin/bash
#
# Image status for the running todo-* containers.
#
# Usage:
#   ./scripts/image-status.sh [--check] [--quiet]   Warn if any running image is older than STALE_DAYS
#   ./scripts/image-status.sh --record FILE         Save current image IDs (for rollback) to FILE
#
# Environment:
#   STALE_DAYS   Age in days after which an image counts as stale (default: 30)
#
# --check exits 1 when something is stale, 0 otherwise. --quiet prints only warnings, so a cron
# job produces output (and therefore a notification) only when action is needed. This script
# never deploys anything.

set -eo pipefail

STALE_DAYS="${STALE_DAYS:-30}"
MODE=check
QUIET=false
RECORD_FILE=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --check) MODE=check; shift ;;
        --quiet) QUIET=true; shift ;;
        --record)
            MODE=record
            RECORD_FILE="${2:?--record needs a file path}"
            shift 2
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 2
            ;;
    esac
done

YELLOW='\033[1;33m'
GREEN='\033[0;32m'
NC='\033[0m'

CONTAINERS=$(docker ps --filter name=todo- --format '{{.Names}}' | sort)

if [ -z "$CONTAINERS" ]; then
    [ "$QUIET" = true ] || echo "No running todo-* containers found."
    exit 0
fi

record() {
    mkdir -p "$(dirname "$RECORD_FILE")"
    {
        echo "# container image-id image-name container-created ($(date -Iseconds))"
        for c in $CONTAINERS; do
            docker inspect --format "$c {{.Image}} {{.Config.Image}} {{.Created}}" "$c"
        done
    } > "$RECORD_FILE"

    echo "Saved current image IDs to $RECORD_FILE"
    echo "To roll back an image: docker tag <image-id> <image-name>, then run"
    echo "  docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --no-build"
    echo "Do not run 'docker image prune' until the new release is verified."
}

check() {
    local stale=0 now
    now=$(date +%s)

    [ "$QUIET" = true ] || printf '%-16s %-28s %s\n' CONTAINER IMAGE "AGE (days)"

    for c in $CONTAINERS; do
        local image created age
        image=$(docker inspect --format '{{.Config.Image}}' "$c")
        created=$(docker image inspect --format '{{.Created}}' "$(docker inspect --format '{{.Image}}' "$c")")
        age=$(( (now - $(date -d "$created" +%s)) / 86400 ))

        if [ "$age" -gt "$STALE_DAYS" ]; then
            stale=$((stale + 1))
            echo -e "${YELLOW}STALE${NC} $c ($image) is $age days old (limit: $STALE_DAYS)"
        elif [ "$QUIET" != true ]; then
            printf '%-16s %-28s %s\n' "$c" "$image" "$age"
        fi
    done

    if [ "$stale" -gt 0 ]; then
        echo -e "${YELLOW}$stale image(s) are stale.${NC} Run a full deploy (no --quick): ./scripts/deploy/update-web.sh"
        return 1
    fi

    [ "$QUIET" = true ] || echo -e "${GREEN}All images are within $STALE_DAYS days.${NC}"
}

if [ "$MODE" = record ]; then
    record
else
    check
fi

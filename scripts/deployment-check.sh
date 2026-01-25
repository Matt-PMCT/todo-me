#!/bin/bash
#
# Deployment Check Script
#
# Validates environment configuration before deployment.
# Run this script to ensure all required settings are configured.

set -e

echo "=========================================="
echo "todo-me Deployment Check"
echo "=========================================="

ERRORS=0
WARNINGS=0

# Color codes
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

error() {
    echo -e "${RED}ERROR:${NC} $1"
    ((ERRORS++))
}

warning() {
    echo -e "${YELLOW}WARNING:${NC} $1"
    ((WARNINGS++))
}

success() {
    echo -e "${GREEN}OK:${NC} $1"
}

# Check if running in Docker context
check_docker() {
    echo ""
    echo "Checking Docker..."

    if ! command -v docker &> /dev/null; then
        error "Docker is not installed"
        return
    fi
    success "Docker is installed"

    if ! docker compose version &> /dev/null; then
        error "Docker Compose is not available"
        return
    fi
    success "Docker Compose is available"
}

# Check environment file
check_env() {
    echo ""
    echo "Checking environment configuration..."

    # Check for .env.local
    if [ -f ".env.local" ]; then
        success ".env.local exists"
    else
        warning ".env.local not found, using defaults"
    fi

    # Load environment
    if [ -f ".env" ]; then
        source .env
    fi
    if [ -f ".env.local" ]; then
        source .env.local
    fi

    # Check APP_ENV
    if [ "$APP_ENV" = "prod" ]; then
        success "APP_ENV is set to production"
    else
        warning "APP_ENV is '${APP_ENV:-dev}', should be 'prod' for production"
    fi

    # Check APP_SECRET
    if [ -z "$APP_SECRET" ] || [ "$APP_SECRET" = "your_generated_secret_here" ]; then
        error "APP_SECRET is not configured"
    elif [ ${#APP_SECRET} -lt 32 ]; then
        warning "APP_SECRET seems short (< 32 chars)"
    else
        success "APP_SECRET is configured"
    fi

    # Check DATABASE_URL
    if [ -z "$DATABASE_URL" ]; then
        error "DATABASE_URL is not configured"
    else
        success "DATABASE_URL is configured"
        # Check for default password
        if [[ "$DATABASE_URL" == *"todo_password"* ]]; then
            warning "DATABASE_URL appears to use default password"
        fi
    fi

    # Check REDIS_URL
    if [ -z "$REDIS_URL" ]; then
        warning "REDIS_URL is not configured"
    else
        success "REDIS_URL is configured"
    fi

    # Check CORS_ALLOW_ORIGIN
    if [ -z "$CORS_ALLOW_ORIGIN" ]; then
        warning "CORS_ALLOW_ORIGIN is not configured"
    elif [[ "$CORS_ALLOW_ORIGIN" == *"localhost"* ]]; then
        warning "CORS_ALLOW_ORIGIN includes localhost (OK for dev, not for prod)"
    else
        success "CORS_ALLOW_ORIGIN is configured"
    fi
}

# Check services connectivity
check_services() {
    echo ""
    echo "Checking services..."

    # Check if docker-compose services are running
    if docker compose -f docker/docker-compose.yml ps --quiet 2>/dev/null | grep -q .; then
        success "Docker services are running"

        # Check PostgreSQL
        if docker compose -f docker/docker-compose.yml exec -T db pg_isready -U todo_user &>/dev/null; then
            success "PostgreSQL is responding"
        else
            error "PostgreSQL is not responding"
        fi

        # Check Redis
        if docker compose -f docker/docker-compose.yml exec -T redis redis-cli ping &>/dev/null; then
            success "Redis is responding"
        else
            error "Redis is not responding"
        fi

        # Check health endpoint
        if curl -sf http://localhost:8080/api/v1/health &>/dev/null; then
            success "Health endpoint is responding"
        else
            warning "Health endpoint is not responding (services may still be starting)"
        fi
    else
        warning "Docker services are not running"
    fi
}

# Check file permissions
check_permissions() {
    echo ""
    echo "Checking permissions..."

    # Check var directory
    if [ -d "var" ]; then
        if [ -w "var" ]; then
            success "var/ directory is writable"
        else
            error "var/ directory is not writable"
        fi
    fi

    # Check for sensitive files
    if [ -f ".env.local" ]; then
        PERMS=$(stat -c "%a" .env.local 2>/dev/null || stat -f "%OLp" .env.local 2>/dev/null)
        if [ "$PERMS" = "600" ] || [ "$PERMS" = "640" ]; then
            success ".env.local has secure permissions"
        else
            warning ".env.local should have restricted permissions (600 or 640)"
        fi
    fi
}

# Check SSL configuration
check_ssl() {
    echo ""
    echo "Checking SSL configuration..."

    if [ -f "docker/nginx/https.conf" ]; then
        success "HTTPS configuration file exists"

        # Check for certificate files
        if grep -q "ssl_certificate" docker/nginx/https.conf; then
            CERT_PATH=$(grep "ssl_certificate " docker/nginx/https.conf | head -1 | awk '{print $2}' | tr -d ';')
            if [ -f "$CERT_PATH" ]; then
                success "SSL certificate exists at $CERT_PATH"
            else
                warning "SSL certificate not found at $CERT_PATH"
            fi
        fi
    else
        warning "HTTPS configuration not found (using HTTP only)"
    fi
}

# Check migrations
check_migrations() {
    echo ""
    echo "Checking database migrations..."

    if docker compose -f docker/docker-compose.yml ps --quiet 2>/dev/null | grep -q .; then
        PENDING=$(docker compose -f docker/docker-compose.yml exec -T php php bin/console doctrine:migrations:status --no-ansi 2>/dev/null | grep -c "not migrated" || echo "0")
        if [ "$PENDING" = "0" ]; then
            success "All migrations are applied"
        else
            warning "$PENDING migrations pending"
        fi
    fi
}

# Run all checks
check_docker
check_env
check_services
check_permissions
check_ssl
check_migrations

# Summary
echo ""
echo "=========================================="
echo "Summary"
echo "=========================================="
echo -e "Errors:   ${RED}$ERRORS${NC}"
echo -e "Warnings: ${YELLOW}$WARNINGS${NC}"

if [ $ERRORS -gt 0 ]; then
    echo ""
    echo -e "${RED}Deployment check failed. Fix errors before deploying.${NC}"
    exit 1
elif [ $WARNINGS -gt 0 ]; then
    echo ""
    echo -e "${YELLOW}Deployment check passed with warnings. Review before deploying.${NC}"
    exit 0
else
    echo ""
    echo -e "${GREEN}All checks passed. Ready for deployment.${NC}"
    exit 0
fi

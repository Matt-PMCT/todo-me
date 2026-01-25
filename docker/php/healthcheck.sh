#!/bin/bash
#
# PHP-FPM Health Check Script
#
# This script checks if PHP-FPM is running and responding.
# Used by Docker health checks.

set -e

# Check if PHP-FPM process is running
if ! pgrep -x "php-fpm" > /dev/null; then
    echo "PHP-FPM process not running"
    exit 1
fi

# Check if PHP-FPM is accepting connections
# Uses cgi-fcgi to send a status request
SCRIPT_NAME=/ping \
SCRIPT_FILENAME=/ping \
REQUEST_METHOD=GET \
cgi-fcgi -bind -connect 127.0.0.1:9000 2>/dev/null || {
    echo "PHP-FPM not accepting connections"
    exit 1
}

echo "PHP-FPM healthy"
exit 0

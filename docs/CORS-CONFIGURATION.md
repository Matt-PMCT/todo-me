# CORS Configuration Guide

This document describes the CORS (Cross-Origin Resource Sharing) configuration for the todo-me application.

## Overview

The application uses [NelmioCorsBundle](https://symfony.com/bundles/NelmioCorsBundle/current/index.html) to handle CORS requests. The configuration is located in `config/packages/nelmio_cors.yaml`.

## Configuration

### Current Setup

```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: ['GET', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE']
        allow_headers: ['Content-Type', 'Authorization', 'X-API-Key']
        expose_headers: ['Link', 'X-RateLimit-Remaining', 'X-Request-ID']
        max_age: 3600
```

**Important:** The `origin_regex: true` setting means the `CORS_ALLOW_ORIGIN` environment variable is interpreted as a **regular expression pattern**, not a literal domain.

## Environment Variable: CORS_ALLOW_ORIGIN

### Regex Pattern Requirements

When using `origin_regex: true`, your pattern must follow these rules:

1. **Always anchor patterns** with `^` (start) and `$` (end) to prevent partial matches
2. **Escape special characters** like `.` (dots) in domain names
3. **Use `https?://`** to match both HTTP and HTTPS if needed

### Common Pattern Examples

#### Single Domain (Production)

```bash
# Only allow https://app.example.com
CORS_ALLOW_ORIGIN='^https://app\.example\.com$'
```

#### Multiple Specific Domains

```bash
# Allow app.example.com and admin.example.com
CORS_ALLOW_ORIGIN='^https://(app|admin)\.example\.com$'
```

#### All Subdomains

```bash
# Allow any subdomain of example.com
CORS_ALLOW_ORIGIN='^https://[a-zA-Z0-9-]+\.example\.com$'
```

#### Development (Localhost)

```bash
# Allow localhost with any port
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

#### Multiple Environments

```bash
# Allow production and staging domains
CORS_ALLOW_ORIGIN='^https://(app\.example\.com|staging\.example\.com)$'
```

### Anti-Patterns (DO NOT USE)

These patterns are INSECURE and should be avoided:

```bash
# INSECURE: Missing anchors - allows evil.example.com.attacker.com
CORS_ALLOW_ORIGIN='https://example\.com'

# INSECURE: Unescaped dot - allows exampleXcom.attacker.com
CORS_ALLOW_ORIGIN='^https://example.com$'

# INSECURE: Wildcard without domain validation
CORS_ALLOW_ORIGIN='^https://.*$'

# INSECURE: Allow all origins (never use in production)
CORS_ALLOW_ORIGIN='.*'
```

## Security Checklist

Before deploying to production, verify:

- [ ] Pattern starts with `^` anchor
- [ ] Pattern ends with `$` anchor
- [ ] All dots (`.`) in domain names are escaped (`\.`)
- [ ] Pattern explicitly specifies the protocol (`https://`)
- [ ] No overly permissive wildcards
- [ ] Pattern tested against both valid and invalid origins

## Testing Your Configuration

### Manual Testing

Test your regex pattern locally before deploying:

```bash
# Test pattern matching in PHP
php -r "
\$pattern = '^https://app\.example\.com\$';
\$origins = [
    'https://app.example.com',        // Should match
    'https://app.example.com/',       // Should NOT match
    'https://evil.example.com',       // Should NOT match
    'https://app.example.com.evil.com', // Should NOT match
];
foreach (\$origins as \$origin) {
    \$matches = preg_match('#'.\$pattern.'#', \$origin);
    echo \$origin . ': ' . (\$matches ? 'MATCH' : 'NO MATCH') . PHP_EOL;
}
"
```

### Automated Testing

The application includes functional tests for CORS behavior. Run them with:

```bash
php bin/phpunit tests/Functional/Security/CorsConfigurationTest.php
```

## Allowed Headers

The following headers are allowed in requests:

| Header | Purpose |
|--------|---------|
| `Content-Type` | Request body format (JSON) |
| `Authorization` | Bearer token authentication |
| `X-API-Key` | API key authentication |

## Exposed Headers

The following response headers are exposed to client applications:

| Header | Purpose |
|--------|---------|
| `Link` | Pagination links (HATEOAS) |
| `X-RateLimit-Remaining` | Remaining rate limit quota |
| `X-Request-ID` | Request tracking identifier |

## Max Age

The `max_age: 3600` setting means preflight OPTIONS requests are cached for 1 hour (3600 seconds) by the browser.

## Alternative: Explicit Domain List

For maximum security, consider using explicit domain lists instead of regex:

```yaml
nelmio_cors:
    defaults:
        origin_regex: false  # Disable regex
        allow_origin:
            - 'https://app.example.com'
            - 'https://admin.example.com'
```

This approach eliminates the risk of regex misconfiguration but requires updating configuration for each new domain.

## Troubleshooting

### CORS Errors in Browser Console

1. **"No 'Access-Control-Allow-Origin' header"**: The origin doesn't match your pattern
2. **"Preflight request failed"**: Check that OPTIONS method is allowed
3. **"Header X-Custom not allowed"**: Add the header to `allow_headers`

### Debugging Pattern Matches

Add logging to see which origins are being checked:

```php
// In a custom event listener (for debugging only)
$origin = $request->headers->get('Origin');
$this->logger->debug('CORS Origin check', ['origin' => $origin]);
```

## Related Documentation

- [NelmioCorsBundle Documentation](https://symfony.com/bundles/NelmioCorsBundle/current/index.html)
- [MDN CORS Guide](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)
- [OWASP CORS Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/REST_Security_Cheat_Sheet.html#cors)

# Security Documentation

This document describes the security measures implemented in the todo-me application.

## Authentication

### API Token Authentication

The API uses token-based authentication with two supported methods:

1. **Bearer Token** (recommended)
   ```
   Authorization: Bearer <token>
   ```

2. **X-API-Key Header**
   ```
   X-API-Key: <token>
   ```

### Token Properties

| Property | Value |
|----------|-------|
| Format | 64-character hex string (256 bits) |
| Generation | Cryptographically secure random bytes |
| Default TTL | 48 hours |
| Refresh window | Up to 7 days after expiration |

### Token Lifecycle

1. **Generation**: Tokens are generated using `random_bytes(32)` converted to hex
2. **Storage**: Hashed tokens stored in database (user record)
3. **Expiration**: Tokens expire after 48 hours (configurable via `API_TOKEN_TTL_HOURS`)
4. **Refresh**: Expired tokens can be refreshed within 7 days
5. **Revocation**: Tokens can be explicitly revoked via `/api/v1/auth/revoke`

## Password Security

### Hashing

Passwords are hashed using Symfony's automatic algorithm selection:

```yaml
# config/packages/security.yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
```

The `'auto'` setting uses the best available algorithm for the current PHP version (bcrypt or argon2), with Symfony managing cost factors and algorithm upgrades automatically.

### Requirements

- Minimum 12 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- Cannot be a common password
- Cannot contain the user's email or username

## Two-Factor Authentication

### TOTP Implementation

Two-factor authentication uses Time-based One-Time Passwords (TOTP) per RFC 6238:

| Property | Value |
|----------|-------|
| Algorithm | SHA-1 |
| Digits | 6 |
| Period | 30 seconds |
| Drift tolerance | ±1 period |
| Issuer | TodoMe |

Users scan a QR code with an authenticator app (Google Authenticator, Authy, etc.) to register their device.

### Backup Codes

When 2FA is enabled, users receive 10 backup codes for account recovery:

- Each code is 8 alphanumeric characters (format: `XXXX-XXXX`)
- Codes are single-use and marked as consumed after verification
- Codes are hashed before storage using the same hasher as passwords
- Users can regenerate codes at any time (invalidates previous codes)

### Challenge Token Flow

When a user with 2FA enabled attempts to log in:

1. User submits email/password to `/auth/token`
2. Server validates credentials and returns a challenge token (valid 5 minutes)
3. User submits challenge token + TOTP code to `/auth/token`
4. Server validates TOTP (or backup code) and returns API token

This flow ensures the password is verified before requesting the second factor.

### Recovery

Users who lose access to their authenticator can recover via email:

1. User requests recovery at `/2fa/recovery/request`
2. Server sends recovery link to registered email (valid 24 hours)
3. User clicks link and completes recovery at `/2fa/recovery/complete`
4. 2FA is disabled and API token is invalidated

Users must then log in again and can re-enable 2FA if desired.

## Rate Limiting

### Limits by Endpoint Type

| Endpoint | Limit | Window |
|----------|-------|--------|
| Login (`/auth/token`) | 5 attempts | 1 minute |
| Registration | 10 attempts | 1 hour |
| Authenticated requests | 1000 requests | 1 hour |
| Anonymous requests | 1000 requests | 1 hour |

### Rate Limit Headers

Responses include rate limit information:
- `X-RateLimit-Remaining`: Requests remaining in window
- `X-RateLimit-Reset`: Unix timestamp when limit resets
- `Retry-After`: Seconds until retry (when limited)

### Bypass for Testing

Test environment has 100x higher limits to allow integration testing.

## Multi-Tenant Data Isolation

### Ownership Model

All user-scoped entities implement `UserOwnedInterface`:

```php
interface UserOwnedInterface
{
    public function getOwner(): ?User;
    public function setOwner(?User $owner): static;
}
```

### Enforcement

The `OwnershipChecker` service validates ownership on all mutations:

```php
// Automatically validates ownership
$task = $this->taskService->findByIdOrFail($id, $user);
```

### Database Level

Repository queries always filter by owner:

```php
public function findByOwner(User $user): array
{
    return $this->createQueryBuilder('t')
        ->where('t.owner = :owner')
        ->setParameter('owner', $user)
        ->getQuery()
        ->getResult();
}
```

## Input Validation

### Request Validation

All API requests are validated using Symfony Validator:

```php
$errors = $this->validator->validate($dto);
if (count($errors) > 0) {
    throw ValidationException::fromConstraintViolationList($errors);
}
```

### Sanitization

- HTML entities are escaped in output
- SQL injection prevented by parameterized queries (Doctrine)
- XSS prevented by Twig auto-escaping

### UUID Validation

All entity IDs are validated as proper UUIDs:

```php
requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}']
```

## CORS Configuration

### Development

```yaml
# Allows localhost development
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

### Production

Configure specific origins:

```yaml
CORS_ALLOW_ORIGIN='^https://your-domain\.com$'
```

### Headers

The application supports:
- `Access-Control-Allow-Origin`: Configured origins
- `Access-Control-Allow-Methods`: GET, POST, PUT, PATCH, DELETE, OPTIONS
- `Access-Control-Allow-Headers`: Content-Type, Authorization, X-API-Key

## HTTP Security Headers

The nginx configuration includes security headers:

```nginx
# Prevent clickjacking
add_header X-Frame-Options "DENY" always;

# Prevent MIME type sniffing
add_header X-Content-Type-Options "nosniff" always;

# Control referrer information
add_header Referrer-Policy "strict-origin-when-cross-origin" always;

# Content Security Policy
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' data:; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';" always;
```

## Session Security (Web UI)

For the web interface:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        web:
            stateless: false
            remember_me:
                secret: '%kernel.secret%'
                lifetime: 604800 # 1 week
                secure: true
                httponly: true
                samesite: strict
```

## Secrets Management

### Environment Variables

Sensitive values are stored in environment variables:
- `APP_SECRET` - Symfony secret for CSRF and cookies
- `DATABASE_URL` - Database credentials
- `REDIS_URL` - Redis credentials (if authenticated)
- `SENTRY_DSN` - Sentry project DSN

### Production Recommendations

1. Use Docker secrets or external secret managers
2. Never commit secrets to version control
3. Rotate secrets periodically
4. Use different secrets for each environment

## Logging Security

### Sensitive Data

The application avoids logging sensitive data:
- Passwords are never logged
- API tokens are masked or hashed in logs
- Email addresses are hashed for privacy

```php
$this->logger->info('Login attempt', [
    'email_hash' => ApiLogger::hashEmail($email),
]);
```

### Audit Trail

Important security events are logged:
- Successful/failed login attempts
- Token generation and revocation
- Account creation and deletion
- Permission denied errors

## Database Security

### Connection Security

- Use SSL/TLS connections in production
- Restrict database user permissions
- Use connection pooling with limits

### Data at Rest

PostgreSQL supports transparent data encryption (TDE) for data at rest. Enable at the database level if required.

## GDPR Compliance

### Data Export

Users can export all their data via `GET /api/v1/users/me/export`

### Account Deletion

Users can delete their account via `DELETE /api/v1/users/me` with password confirmation.

### Data Minimization

- Only collect necessary data
- No tracking or analytics by default
- User data isolated by ownership

## Vulnerability Reporting

To report a security vulnerability:

1. Do NOT open a public GitHub issue
2. Email security concerns to the maintainers
3. Include detailed reproduction steps
4. Allow reasonable time for patching before disclosure

## Security Checklist

### Development

- [ ] Use HTTPS in development (self-signed cert OK)
- [ ] Review code for OWASP Top 10 vulnerabilities
- [ ] Run static analysis tools
- [ ] Keep dependencies updated

### Deployment

- [ ] Enable HTTPS with valid certificate
- [ ] Set strong `APP_SECRET`
- [ ] Configure CORS for specific origins
- [ ] Enable rate limiting
- [ ] Configure firewall rules
- [ ] Set up monitoring and alerting
- [ ] Implement backup strategy
- [ ] Review and rotate secrets

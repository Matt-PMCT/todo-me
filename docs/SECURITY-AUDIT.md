# Security Audit Checklist

Use this checklist for security audits of the todo-me application.

## Authentication & Authorization

- [ ] **Token Generation**: Tokens use cryptographically secure random generation
- [ ] **Token Storage**: Tokens stored securely (not in plain text for any sensitive operation)
- [ ] **Token Expiration**: Tokens expire after configured TTL
- [ ] **Token Refresh**: Refresh window is reasonable (7 days)
- [ ] **Password Hashing**: bcrypt with cost >= 12
- [ ] **Password Requirements**: Minimum length enforced
- [ ] **Rate Limiting**: Login attempts are rate limited
- [ ] **Session Fixation**: Sessions regenerated on login
- [ ] **Ownership Validation**: All resources validate owner before access

## Input Validation

- [ ] **SQL Injection**: All queries use parameterized statements
- [ ] **XSS Prevention**: Output properly escaped in templates
- [ ] **CSRF Protection**: CSRF tokens for state-changing forms
- [ ] **Command Injection**: No shell commands with user input
- [ ] **Path Traversal**: File paths validated and sandboxed
- [ ] **JSON Validation**: Request bodies validated against schema
- [ ] **UUID Validation**: Entity IDs validated as proper UUIDs
- [ ] **Integer Overflow**: Numeric inputs have reasonable bounds
- [ ] **Mass Assignment**: Only allowed fields are updateable

## HTTP Security

- [ ] **HTTPS Enforced**: HTTP redirects to HTTPS in production
- [ ] **HSTS Header**: Strict-Transport-Security set
- [ ] **X-Frame-Options**: Clickjacking protection enabled
- [ ] **X-Content-Type-Options**: MIME sniffing prevention
- [ ] **Content-Security-Policy**: CSP headers configured
- [ ] **Referrer-Policy**: Appropriate referrer control
- [ ] **CORS Configuration**: Specific origins, not wildcard
- [ ] **Cookie Security**: Secure, HttpOnly, SameSite flags

## Data Protection

- [ ] **Encryption in Transit**: TLS 1.2+ for all connections
- [ ] **Database Encryption**: Consider encryption at rest
- [ ] **Sensitive Data Logging**: No passwords/tokens in logs
- [ ] **Error Messages**: No sensitive info in error responses
- [ ] **Backup Encryption**: Backups encrypted at rest
- [ ] **Data Retention**: Clear policy for data retention

## Infrastructure

- [ ] **Firewall Rules**: Only necessary ports exposed
- [ ] **Container Security**: Non-root users in containers
- [ ] **Dependency Updates**: Dependencies regularly updated
- [ ] **Secret Management**: Secrets not in code/version control
- [ ] **Access Logging**: HTTP access logs enabled
- [ ] **Error Logging**: Application errors logged (not exposed)
- [ ] **Monitoring**: Health checks and alerts configured

## GDPR & Privacy

- [ ] **Data Export**: Users can export their data
- [ ] **Account Deletion**: Users can delete their account
- [ ] **Data Minimization**: Only necessary data collected
- [ ] **Consent Management**: Clear consent for data processing
- [ ] **Privacy Policy**: Clear privacy documentation

## API Security

- [ ] **Rate Limiting**: All endpoints rate limited
- [ ] **Authentication Required**: Protected endpoints require auth
- [ ] **Pagination Limits**: List endpoints have max page size
- [ ] **Request Size Limits**: Large requests rejected
- [ ] **Response Filtering**: No sensitive data in responses
- [ ] **Deprecation Warnings**: Old endpoints properly deprecated

## Code Quality

- [ ] **Static Analysis**: PHP static analysis tools run
- [ ] **Dependency Scanning**: Check for known vulnerabilities
- [ ] **Code Review**: Security-focused code review process
- [ ] **Unit Tests**: Security-critical code has tests
- [ ] **Integration Tests**: Auth flows have integration tests

## Audit Log

| Date | Auditor | Findings | Status |
|------|---------|----------|--------|
| | | | |

## Remediation Tracking

| Issue | Severity | Found | Fixed | Verified |
|-------|----------|-------|-------|----------|
| | | | | |

## Notes

Use this document to track security audits over time. Update the audit log after each review and track any findings through remediation.

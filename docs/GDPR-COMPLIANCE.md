# GDPR Compliance Documentation

This document describes how todo-me supports GDPR (General Data Protection Regulation) compliance.

## Overview

todo-me is designed with privacy in mind and supports key GDPR requirements:

- **Right to Access**: Users can view all their data
- **Right to Data Portability**: Users can export their data in JSON format
- **Right to Erasure**: Users can delete their account and all associated data
- **Data Minimization**: Only necessary data is collected

## Data Collected

### User Account Data

| Field | Purpose | Retention |
|-------|---------|-----------|
| Email | Authentication, account recovery | Until deletion |
| Password hash | Authentication | Until deletion |
| Username | Display name | Until deletion |
| API token | API authentication | Until revocation/expiration |

### User Content Data

| Entity | Data Stored | Retention |
|--------|-------------|-----------|
| Tasks | Title, description, dates, status, priority | Until deletion |
| Projects | Name, description, hierarchy | Until deletion |
| Tags | Name, color | Until deletion |
| Saved Filters | Name, filter criteria | Until deletion |

### Technical Data

| Data | Purpose | Retention |
|------|---------|-----------|
| Request logs | Debugging, security | 30 days (configurable) |
| Error logs | Bug fixing | 30 days (configurable) |

## User Rights Implementation

### Right to Access

Users can view all their data through the API:

```bash
# Get user profile
curl -H "Authorization: Bearer TOKEN" \
  https://your-domain.com/api/v1/auth/me

# Get all tasks
curl -H "Authorization: Bearer TOKEN" \
  https://your-domain.com/api/v1/tasks?limit=1000

# Get all projects
curl -H "Authorization: Bearer TOKEN" \
  https://your-domain.com/api/v1/projects

# Get all tags
curl -H "Authorization: Bearer TOKEN" \
  https://your-domain.com/api/v1/tags
```

### Right to Data Portability

Users can export all their data in machine-readable JSON format:

```bash
curl -H "Authorization: Bearer TOKEN" \
  https://your-domain.com/api/v1/users/me/export
```

Response includes:
- User profile information
- All projects (with hierarchy)
- All tasks (with relationships)
- All tags
- All saved filters
- Export timestamp

Example response:
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "uuid",
      "email": "user@example.com",
      "username": "user_abc123",
      "createdAt": "2026-01-01T00:00:00+00:00"
    },
    "projects": [...],
    "tasks": [...],
    "tags": [...],
    "savedFilters": [...],
    "exportedAt": "2026-01-24T12:00:00+00:00"
  }
}
```

### Right to Erasure

Users can permanently delete their account and all associated data:

```bash
curl -X DELETE -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"password": "current-password"}' \
  https://your-domain.com/api/v1/users/me
```

**What gets deleted:**
- User account
- All projects (including sub-projects)
- All tasks (including subtasks)
- All tags
- All saved filters
- API tokens
- Any cached data in Redis

**Requirements:**
- User must provide current password for confirmation
- Action is immediate and irreversible
- No "soft delete" - data is permanently removed

### Right to Rectification

Users can update their data through standard API endpoints:

- Update tasks: `PATCH /api/v1/tasks/{id}`
- Update projects: `PATCH /api/v1/projects/{id}`
- Update tags: `PATCH /api/v1/tags/{id}`

## Data Processing

### Legal Basis

The legal basis for processing user data is:

1. **Contract Performance**: Processing necessary to provide the todo management service
2. **Legitimate Interest**: Security logging and error tracking

### Data Processors

If you deploy todo-me and use third-party services:

| Service | Purpose | Data Shared |
|---------|---------|-------------|
| Hosting Provider | Infrastructure | All application data |
| Sentry (optional) | Error tracking | Error details, no PII by default |

### Data Transfer

todo-me itself does not transfer data outside your infrastructure. Third-party services may transfer data according to their privacy policies.

## Security Measures

### Technical Measures

- Passwords hashed with bcrypt
- API tokens cryptographically generated
- HTTPS enforced in production
- Rate limiting on authentication
- SQL injection prevention via parameterized queries
- XSS prevention via output escaping

### Organizational Measures

- Role-based access (each user sees only their data)
- No shared accounts
- API token expiration
- Secure session management

## Consent

### Registration

By registering an account, users consent to:
- Storage of their email for authentication
- Processing of their tasks, projects, and tags
- Logging for security and debugging

### Marketing

todo-me does not include marketing features. No consent management for marketing is needed.

## Data Protection Officer

For self-hosted deployments, the deploying organization should designate a DPO if required by GDPR.

## Privacy Policy Template

When deploying todo-me, include a privacy policy covering:

1. **Data Controller**: Your organization's details
2. **Data Collected**: Reference this document
3. **Purpose**: Task management service
4. **Legal Basis**: Contract performance
5. **Data Retention**: Until user deletion
6. **User Rights**: Access, export, deletion
7. **Contact**: How users can exercise rights

## Breach Notification

In case of a data breach:

1. Assess the scope and severity
2. Notify supervisory authority within 72 hours (if required)
3. Notify affected users (if high risk)
4. Document the breach and response

## Checklist for Deployment

- [ ] HTTPS enabled
- [ ] Privacy policy published
- [ ] User export endpoint tested
- [ ] User deletion endpoint tested
- [ ] Log retention configured
- [ ] Backup encryption enabled
- [ ] Access controls verified
- [ ] DPO designated (if required)

## Questions

For questions about GDPR compliance in your todo-me deployment, consult with a legal professional familiar with data protection regulations in your jurisdiction.

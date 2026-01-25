# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Phase 11: Two-Factor Authentication
  - TOTP-based 2FA with authenticator app support
  - Backup codes for account recovery (10 single-use codes)
  - Challenge token flow for 2FA login
  - Email-based 2FA recovery

- Phase 9: Documentation & Deployment
  - Comprehensive README with Quick Start guide
  - CONTRIBUTING.md with development guidelines
  - OpenAPI documentation for all API endpoints
  - AI agent integration guide with examples
  - Health check endpoint
  - GDPR compliance features (data export, account deletion)
  - Security hardening documentation
  - Production deployment scripts

## [0.8.0] - 2026-01-24

### Added
- Phase 8: Polish & Testing
  - Comprehensive test suite with functional and unit tests
  - Full-text search with PostgreSQL tsvector
  - Response caching for list endpoints
  - Input sanitization
  - Performance optimizations

### Fixed
- Duplicate database indexes
- FTS test reliability
- Due time serialization for tasks

## [0.7.0] - 2026-01-23

### Added
- Phase 7: Recurring Tasks
  - Daily, weekly, monthly, and yearly recurrence patterns
  - Natural language recurrence parsing ("every Monday", "daily at 9am")
  - Recurrence chain tracking
  - Complete recurring task forever option
  - Recurring task history view

### Fixed
- FTS test edge cases
- Due time serialization consistency
- Toast notification reload behavior

## [0.6.0] - 2026-01-22

### Added
- Phase 6: Advanced Task Features
  - Subtasks with parent-child relationships
  - Task templates
  - Batch operations API
  - Task reordering with drag-and-drop
  - Keyboard shortcuts for common actions

## [0.5.0] - 2026-01-21

### Added
- Phase 5: Saved Filters & Views
  - Saved filters with custom names
  - Today view (due today + overdue)
  - Upcoming view (next 7 days)
  - Overdue view
  - No date view
  - Completed tasks view

## [0.4.0] - 2026-01-20

### Added
- Phase 4: Natural Language Processing
  - Natural language task creation
  - Date parsing ("tomorrow", "next Monday", "Jan 15")
  - Time parsing ("at 3pm", "at 14:30")
  - Priority extraction ("high priority", "p1")
  - Tag extraction ("#work", "#personal")
  - Project extraction ("@work", "@personal")
  - Parse preview endpoint

## [0.3.0] - 2026-01-19

### Added
- Phase 3: Projects & Tags
  - Project CRUD operations
  - Hierarchical projects (parent/child)
  - Project archiving
  - Tag CRUD operations
  - Custom tag colors
  - Task-tag associations
  - Filter tasks by project and tags

## [0.2.0] - 2026-01-18

### Added
- Phase 2: Task Enhancements
  - Task priorities (1-5 scale)
  - Due dates with time support
  - Task status workflow (pending, in_progress, completed)
  - Undo support for task updates and deletions
  - Advanced filtering (status, priority, date range)
  - Sorting options
  - Pagination

## [0.1.0] - 2026-01-17

### Added
- Phase 1: Foundation
  - User registration and authentication
  - API token authentication (Bearer and X-API-Key)
  - Token refresh and revocation
  - Basic task CRUD operations
  - PostgreSQL database with Doctrine ORM
  - Redis for caching and undo tokens
  - Docker Compose development environment
  - Rate limiting for API endpoints
  - Consistent JSON API response format

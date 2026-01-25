# Project Progress

This document tracks the completion status of all implementation phases for the Todo-Me application.

## Phase Completion Status

| Phase | Name | Status | Notes |
|-------|------|--------|-------|
| 1 | Core Infrastructure | Complete | Docker, Symfony setup, PostgreSQL, Redis |
| 2 | Authentication | Complete | User registration, login, API tokens |
| 3 | Task Management | Complete | CRUD operations, status, priority |
| 4 | Projects & Tags | Complete | Organization features |
| 5 | Filtering & Search | Complete | Full-text search, saved filters |
| 6 | Drag & Drop | Complete | Task reordering, position management |
| 7 | Recurring Tasks | Complete | Recurrence rules, auto-generation |
| 8 | UI Enhancements | Complete | Keyboard shortcuts, subtask UI |
| 9 | Mobile Experience | Complete | Swipe gestures, bottom navigation |
| 10 | Security | Complete | 2FA, rate limiting, CORS |
| 11 | Performance | Complete | Caching, query optimization |
| 12 | Notifications | Complete | Email, push notifications |
| 13 | API Tokens & Sessions | Complete | Token management, activity logging |
| 14 | Production Readiness | Complete | CI/CD, monitoring, deployment |

## Feature Reference

### Keyboard Shortcuts

All keyboard shortcuts for the web interface:

#### Navigation
| Key | Action |
|-----|--------|
| `/` | Focus search |
| `j` or `Down` | Navigate to next task |
| `k` or `Up` | Navigate to previous task |
| `Enter` | Open selected task |

#### Task Actions
| Key | Action |
|-----|--------|
| `n` or `Ctrl+K` | Focus quick add input |
| `Ctrl+Enter` | Submit quick add form |
| `c` | Complete selected task |
| `e` | Edit selected task |
| `Delete` | Delete selected task |
| `t` | Set due date to today |
| `Ctrl+Z` | Undo last action |

#### General
| Key | Action |
|-----|--------|
| `?` | Show keyboard help modal |
| `Esc` | Close modal/dropdown |

**Note:** On macOS, use `Cmd` instead of `Ctrl`.

### Mobile Gestures

Touch gestures for mobile devices:

| Gesture | Action |
|---------|--------|
| Swipe right | Complete task |
| Swipe left | Reveal delete action |

### Code Quality Tools

The project uses the following code quality tools:

- **PHPStan** (Level 8): Static analysis with Symfony extension
- **PHP-CS-Fixer**: PSR-12 + Symfony coding standards

Run checks:
```bash
composer phpstan      # Static analysis
composer cs-check     # Code style check
composer cs-fix       # Auto-fix code style
composer lint         # Run both
```

### API Documentation

OpenAPI/Swagger documentation is available at `/api/doc` when the application is running.

All 18 API controllers have OpenAPI attributes:
- AuthController
- TaskController
- ProjectController
- TagController
- NotificationController
- PushController
- TwoFactorController
- (and others)

## Test Coverage

Run the full test suite:
```bash
docker compose -f docker/docker-compose.yml exec php vendor/bin/phpunit --testdox-text var/test-results.txt
```

Current test count: 4,474+ tests

## Recent Updates

### Phase 8 & 9 Completion (Latest)
- Added 5 new keyboard shortcuts (n, c, e, Delete, t)
- Enhanced subtask UI with expandable lists and progress bars
- Added mobile swipe gestures
- Added mobile bottom navigation bar
- Set up PHPStan (Level 8) and PHP-CS-Fixer
- Added OpenAPI documentation to NotificationController, PushController, TwoFactorController

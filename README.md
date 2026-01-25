# todo-me

A self-hosted task management application with REST API and web UI, built with Symfony 7.

## Features

- **Task Management**: Create, update, delete, and organize tasks with priorities, due dates, and statuses
- **Projects**: Organize tasks into hierarchical projects with parent/child relationships
- **Tags**: Flexible tagging system with custom colors
- **Natural Language Input**: Create tasks using natural language ("Buy milk tomorrow at 2pm #shopping")
- **Recurring Tasks**: Support for daily, weekly, monthly, and yearly recurring tasks
- **Subtasks**: Nested task support for breaking down complex tasks
- **Advanced Filtering**: Filter by status, priority, project, tags, due date, and more
- **Saved Filters**: Save and reuse complex filter combinations
- **Full-Text Search**: Search across task titles and descriptions
- **Batch Operations**: Perform bulk updates on multiple tasks
- **Undo Support**: Undo task updates and deletions within 60 seconds
- **REST API**: Complete API for integration with external tools and AI agents
- **Web UI**: Clean, responsive interface built with Alpine.js and Tailwind CSS

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 8.4, Symfony 7.0, Doctrine ORM 3.0 |
| Database | PostgreSQL 15 |
| Cache | Redis 7 |
| Web Server | Nginx 1.24 |
| Frontend | Twig, Alpine.js, Tailwind CSS |
| Containerization | Docker, Docker Compose |

## Quick Start

### Prerequisites

- Docker and Docker Compose
- Git

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/todo-me.git
   cd todo-me
   ```

2. **Copy environment file**
   ```bash
   cp .env.local.example .env.local
   ```

3. **Generate a secret key and update .env.local**
   ```bash
   php -r "echo bin2hex(random_bytes(16));"
   # Copy the output to APP_SECRET in .env.local
   ```

4. **Start the Docker containers**
   ```bash
   docker compose -f docker/docker-compose.yml up -d
   ```

5. **Install dependencies**
   ```bash
   docker compose -f docker/docker-compose.yml exec php composer install
   ```

6. **Run database migrations**
   ```bash
   docker compose -f docker/docker-compose.yml exec php php bin/console doctrine:migrations:migrate --no-interaction
   ```

7. **Access the application**
   - Web UI: http://localhost:8080
   - API: http://localhost:8080/api/v1

## API Documentation

Interactive API documentation is available at `/api/v1/docs` when the application is running.

### Authentication

The API supports two authentication methods:

**Bearer Token (recommended)**
```bash
curl -H "Authorization: Bearer YOUR_API_TOKEN" \
  http://localhost:8080/api/v1/tasks
```

**X-API-Key Header**
```bash
curl -H "X-API-Key: YOUR_API_TOKEN" \
  http://localhost:8080/api/v1/tasks
```

### Getting Started with the API

1. **Register a new user**
   ```bash
   curl -X POST http://localhost:8080/api/v1/auth/register \
     -H "Content-Type: application/json" \
     -d '{"email": "user@example.com", "password": "SecurePassword123"}'
   ```

2. **Login to get a token**
   ```bash
   curl -X POST http://localhost:8080/api/v1/auth/token \
     -H "Content-Type: application/json" \
     -d '{"email": "user@example.com", "password": "SecurePassword123"}'
   ```

3. **Create a task**
   ```bash
   curl -X POST http://localhost:8080/api/v1/tasks \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"title": "My first task", "priority": 3}'
   ```

### API Response Format

All API endpoints return a consistent JSON structure:

```json
{
  "success": true,
  "data": { },
  "error": null,
  "meta": {
    "requestId": "uuid",
    "timestamp": "2026-01-24T12:00:00+00:00"
  }
}
```

### Key Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/auth/register` | POST | Register new user |
| `/api/v1/auth/token` | POST | Login and get token |
| `/api/v1/auth/refresh` | POST | Refresh expired token |
| `/api/v1/auth/revoke` | POST | Logout (revoke token) |
| `/api/v1/tasks` | GET | List tasks with filters |
| `/api/v1/tasks` | POST | Create task |
| `/api/v1/tasks/{id}` | GET | Get task details |
| `/api/v1/tasks/{id}` | PUT/PATCH | Update task |
| `/api/v1/tasks/{id}` | DELETE | Delete task |
| `/api/v1/tasks/today` | GET | Tasks due today |
| `/api/v1/tasks/upcoming` | GET | Upcoming tasks |
| `/api/v1/tasks/overdue` | GET | Overdue tasks |
| `/api/v1/projects` | GET/POST | List/create projects |
| `/api/v1/projects/{id}` | GET/PUT/DELETE | Manage project |
| `/api/v1/search` | GET | Full-text search |
| `/api/v1/batch` | POST | Batch operations |
| `/api/v1/parse` | POST | Parse natural language |

## Project Structure

```
todo-me/
├── config/                 # Symfony configuration
│   ├── packages/          # Bundle configs
│   └── routes/            # Routing configs
├── docker/                # Docker configuration
│   ├── nginx/             # Nginx config
│   └── php/               # PHP-FPM config
├── docs/                  # Documentation
├── migrations/            # Database migrations
├── public/                # Web root
├── src/
│   ├── Controller/
│   │   ├── Api/          # REST API controllers
│   │   └── Web/          # Web UI controllers
│   ├── DTO/              # Data Transfer Objects
│   ├── Entity/           # Doctrine entities
│   ├── EventListener/    # Event listeners
│   ├── EventSubscriber/  # Event subscribers
│   ├── Exception/        # Custom exceptions
│   ├── Interface/        # Interfaces
│   ├── Repository/       # Doctrine repositories
│   ├── Security/         # Authentication
│   └── Service/          # Business logic
├── templates/             # Twig templates
├── tests/
│   ├── Functional/       # Functional tests
│   └── Unit/             # Unit tests
└── var/                   # Cache, logs
```

## Development

### Running Tests

```bash
# Enter the PHP container
docker compose -f docker/docker-compose.yml exec php bash

# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/Functional

# Run single test class
vendor/bin/phpunit --filter=TaskServiceTest
```

### Code Style

The project follows PSR-12 coding standards. Use PHP-CS-Fixer to maintain consistency:

```bash
vendor/bin/php-cs-fixer fix src/
```

### Database Commands

```bash
# Create a new migration
php bin/console doctrine:migrations:generate

# Run migrations
php bin/console doctrine:migrations:migrate

# Rollback last migration
php bin/console doctrine:migrations:migrate prev
```

### Cache Management

```bash
# Clear cache
php bin/console cache:clear

# Warmup cache
php bin/console cache:warmup
```

## Production Deployment

See [docs/DOCKER-PRODUCTION.md](docs/DOCKER-PRODUCTION.md) for detailed production deployment instructions.

### Quick Production Checklist

- [ ] Set strong `APP_SECRET`
- [ ] Set strong database password
- [ ] Set strong Redis password
- [ ] Configure CORS for your domain
- [ ] Enable SSL/TLS
- [ ] Set up monitoring
- [ ] Configure backups

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_ENV` | Environment (dev, test, prod) | `dev` |
| `APP_SECRET` | Symfony secret key | - |
| `DATABASE_URL` | PostgreSQL connection string | - |
| `REDIS_URL` | Redis connection string | - |
| `CORS_ALLOW_ORIGIN` | CORS allowed origins regex | `^https?://localhost` |
| `API_TOKEN_TTL_HOURS` | API token lifetime in hours | `48` |
| `SENTRY_DSN` | Sentry error tracking DSN | - |

## Security

- All passwords are hashed using bcrypt
- API tokens expire after 48 hours (configurable)
- Rate limiting on authentication endpoints
- CORS protection
- Multi-tenant data isolation

See [docs/SECURITY.md](docs/SECURITY.md) for security documentation.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

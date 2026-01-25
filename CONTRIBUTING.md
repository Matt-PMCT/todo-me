# Contributing to todo-me

Thank you for your interest in contributing to todo-me! This document provides guidelines and instructions for contributing.

## Development Environment

### Prerequisites

- Docker and Docker Compose
- Git
- PHP 8.4+ (for IDE support)
- Composer (for IDE support)

### Setup

1. Fork and clone the repository:
   ```bash
   git clone https://github.com/yourusername/todo-me.git
   cd todo-me
   ```

2. Copy the environment file:
   ```bash
   cp .env.local.example .env.local
   ```

3. Start Docker containers:
   ```bash
   docker compose -f docker/docker-compose.yml up -d
   ```

4. Install dependencies:
   ```bash
   docker compose -f docker/docker-compose.yml exec php composer install
   ```

5. Run migrations:
   ```bash
   docker compose -f docker/docker-compose.yml exec php php bin/console doctrine:migrations:migrate --no-interaction
   ```

6. Run tests to verify setup:
   ```bash
   docker compose -f docker/docker-compose.yml exec php vendor/bin/phpunit
   ```

## Branch Naming Convention

Use descriptive branch names with the following prefixes:

| Prefix | Purpose | Example |
|--------|---------|---------|
| `feature/` | New features | `feature/task-comments` |
| `fix/` | Bug fixes | `fix/login-validation` |
| `docs/` | Documentation | `docs/api-examples` |
| `refactor/` | Code refactoring | `refactor/task-service` |
| `test/` | Test additions | `test/project-api` |
| `chore/` | Maintenance tasks | `chore/update-dependencies` |

## Commit Message Format

Follow the conventional commits format:

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Test additions or modifications
- `chore`: Maintenance tasks

### Examples

```
feat(tasks): add recurring task support

Implement daily, weekly, monthly, and yearly recurrence patterns.
Tasks automatically regenerate when completed.

Closes #42
```

```
fix(auth): validate email format on registration

Adds proper email validation to prevent invalid emails
from being registered.
```

## Pull Request Process

1. **Create a branch** from `main`:
   ```bash
   git checkout -b feature/my-feature
   ```

2. **Make your changes** following the coding standards

3. **Write tests** for new functionality

4. **Run the test suite** to ensure all tests pass:
   ```bash
   docker compose -f docker/docker-compose.yml exec php vendor/bin/phpunit
   ```

5. **Commit your changes** with descriptive messages

6. **Push your branch** and create a pull request

7. **Fill out the PR template** with:
   - Summary of changes
   - Test plan
   - Related issues

### PR Requirements

- All tests must pass
- Code must follow PSR-12 standards
- New features must include tests
- Documentation must be updated if needed

## Coding Standards

### PHP

- Follow PSR-12 coding standards
- Use strict types: `declare(strict_types=1);`
- Use type hints for all parameters and return types
- Use final classes by default
- Keep methods short and focused

### Architecture

- Controllers should be thin, delegating to services
- Business logic belongs in Service classes
- Use DTOs for request/response data
- Validate at system boundaries
- Trust internal code and framework guarantees

### Testing

- Write functional tests for API endpoints
- Write unit tests for services with complex logic
- Don't test framework functionality
- Use descriptive test method names

## Code Review

Pull requests require at least one approval before merging. Reviewers will check:

- Code quality and style
- Test coverage
- Security considerations
- Documentation updates
- Backwards compatibility

## Reporting Issues

When reporting bugs, please include:

1. **Description** of the problem
2. **Steps to reproduce**
3. **Expected behavior**
4. **Actual behavior**
5. **Environment details** (OS, browser, Docker version)
6. **Relevant logs** or error messages

## Feature Requests

For feature requests, please:

1. Check if the feature already exists
2. Search existing issues for duplicates
3. Describe the use case
4. Explain the expected behavior

## Questions

For questions about the codebase or development:

1. Check the documentation in `docs/`
2. Search existing issues
3. Open a new issue with the "question" label

## License

By contributing to todo-me, you agree that your contributions will be licensed under the MIT License.

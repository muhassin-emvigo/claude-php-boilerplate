# Contributing Guide

## Branch Naming
- `feat/<description>` — New features
- `fix/<description>` — Bug fixes
- `docs/<description>` — Documentation changes
- `refactor/<description>` — Code refactoring
- `test/<description>` — Adding or updating tests
- `chore/<description>` — Maintenance tasks

## Commit Messages

Use [Conventional Commits](https://www.conventionalcommits.org/) format:

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

### Types
- `feat` — New feature
- `fix` — Bug fix
- `docs` — Documentation only
- `style` — Formatting, no logic change
- `refactor` — Restructuring without behavior change
- `test` — Adding or updating tests
- `chore` — Build, tools, dependencies
- `perf` — Performance improvement

### Examples
```
feat(catalog): add product attribute import via CSV
fix(checkout): resolve null pointer in payment validation
test(model): add unit tests for OrderRepository
refactor(api): extract search criteria builder to trait
```

## Pull Request Checklist

- [ ] Code follows PSR-12 + Magento2 coding standard
- [ ] `declare(strict_types=1)` in all PHP files
- [ ] All public methods have type declarations
- [ ] Unit tests written for new code
- [ ] `make check` passes (lint + phpstan + phpmd + test)
- [ ] No ObjectManager direct usage
- [ ] ACL defined for admin routes
- [ ] Output properly escaped in templates
- [ ] CHANGELOG.md updated

## Development Workflow

1. Create a feature branch: `git checkout -b feat/my-feature`
2. Make changes and write tests
3. Run quality checks: `make check`
4. Commit with conventional message
5. Push and create PR
6. Address review feedback
7. Merge after approval

## Code Review Standards

Reviewers should check:
- Architecture follows Magento patterns (DI, service contracts)
- Security best practices (escaping, ACL, CSRF)
- Test coverage for business logic
- No anti-patterns (ObjectManager, class rewrites, raw SQL)
- Documentation for public APIs

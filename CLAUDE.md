# Magento 2 Module Boilerplate

## Project Overview
This is a Magento 2.4.x PHP module development workspace with Claude Code integration.
It provides a standardized skeleton, quality tooling, and AI-assisted workflows.

## AI Optimization (Required)

For **every `.md` file** (requirements, BRD, technical specs, progress, tasks, implementation plans, etc.), always enable the following before processing:

- Minimize token usage, avoid unnecessary explanations, and keep reasoning concise.
- Store and reuse important project context instead of repeating it across prompts.

## Tech Stack
- **PHP**: 8.2+ with strict_types
- **Framework**: Magento 2.4.x (Adobe Commerce / Open Source)
- **Database**: MySQL 8.0 / MariaDB 10.6+
- **Search**: OpenSearch 2.x
- **Cache**: Redis 7.x
- **Testing**: PHPUnit 10
- **Linting**: PHPCS (Magento2 standard), PHPStan (level 6), PHPMD, PHP-CS-Fixer
- **Git Hooks**: CaptainHook (PHP-native)

## Architecture Conventions
- **Dependency Injection**: Constructor injection ONLY — never use ObjectManager directly
- **Service Contracts**: Define interfaces in `Api/` before implementations in `Model/`
- **Plugins > Observers > Rewrites**: Prefer interceptors over event observers over class preferences
- **Declarative Schema**: Use `db_schema.xml` — never InstallSchema/UpgradeSchema
- **Data Patches**: Idempotent data migrations via `Setup/Patch/Data/`
- **ViewModels**: Use ViewModel classes instead of Block classes for template data

## Key Commands
```bash
make init            # First-time setup: rename vendor/CustomShipping
make install         # Install Composer dependencies
make test            # Run PHPUnit tests
make test-coverage   # Tests with HTML coverage report
make lint            # PHPCS (Magento2 coding standard)
make lint-fix        # Auto-fix with PHP-CS-Fixer
make phpstan         # PHPStan static analysis (level 6)
make phpmd           # PHPMD mess detection
make check           # Run ALL quality checks
make check-setup     # Verify environment
make clean           # Clean generated files
```

## Directory Structure
```
.claude/
├── agents/           # Specialized Claude agents (architect, reviewer, etc.)
├── commands/         # Custom slash commands (/test, /lint, /review)
├── hooks/            # Claude Code hooks (pre-commit-validate.sh)
├── rules/            # Project-specific AI rules
└── workflows/        # Step-by-step developer runbooks
app/code/{vendor}/{Module}/
├── Api/              # Service contract interfaces
│   └── Data/         # Data transfer object interfaces
├── Block/            # Block classes (prefer ViewModel)
├── Controller/       # Request handlers
├── Helper/           # Helper classes (use sparingly)
├── Model/            # Business logic, resource models, collections
├── Observer/         # Event observers
├── Plugin/           # Interceptors (before/after/around plugins)
├── Setup/Patch/      # Data and schema patches
├── ViewModel/        # Presentation logic for templates
├── etc/              # Configuration XML files
├── view/             # Templates, layouts, static assets
├── i18n/             # Translation CSV files
└── Test/             # PHPUnit tests (Unit/ and Integration/)
```

## Coding Standards
- `declare(strict_types=1)` in every PHP file
- Type declarations on ALL parameters and return types
- PSR-12 + Magento2 coding standard
- One class per file
- Test naming: `test<Method>_<Scenario>_<Expected>()`

## Security Requirements
- No raw SQL — use Resource Models / SearchCriteria
- Escape all output: `$escaper->escapeHtml()` in templates
- ACL for all admin controllers (`ADMIN_RESOURCE` constant)
- CSRF protection via form keys in POST forms
- Validate all user input

## Testing Strategy
1. **Unit Tests** (fast): Mock all dependencies, test business logic
2. **Integration Tests**: Use `@magentoDbIsolation`, test with real DI
3. **API Tests**: Test REST/SOAP endpoints

Target: 80%+ coverage on business logic

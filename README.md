# Magento 2 PHP Boilerplate for Claude Code

> Production-ready Magento 2 module skeleton with Claude Code integration — skills, rules, commands, hooks, and quality tooling out of the box.

## 🚀 Quick Start

```bash
# 1. Clone
git clone <repo-url> my-magento-module
cd my-magento-module

# 2. Initialize (renames Vendor/ModuleName to your names)
make init

# 3. Install dependencies
make install

# 4. Verify environment
make check-setup

# 5. Run the sample test
make test

# 6. Start building!
```

## ✨ What You Get

### Claude Code Integration
| Feature | Location | Purpose |
|---------|----------|---------|
| **Skills** | `.claude/skills/` | Module scaffolding, test writing, code review |
| **Rules** | `.claude/rules/` | PHP standards, Magento conventions, security, testing |
| **Commands** | `.claude/commands/` | `/start`, `/review`, `/test`, `/lint`, `/ship`, `/status` |
| **Agents** | `.claude/agents/` | Architect, reviewer, test-writer, security-auditor |
| **Hooks** | `.claude/settings.json` | Auto PHP syntax check on every file edit |

### Quality Tooling
| Tool | Command | Purpose |
|------|---------|----------|
| PHPUnit | `make test` | Unit & integration testing |
| PHPCS | `make lint` | Magento2 coding standard |
| PHPStan | `make phpstan` | Static analysis (level 6) |
| PHPMD | `make phpmd` | Mess detection |
| PHP-CS-Fixer | `make lint-fix` | Auto-fix code style |
| CaptainHook | auto on commit | Pre-commit & pre-push hooks |

### Module Template
A complete Magento 2.4.x module skeleton under `app/code/Vendor/ModuleName/` with:
- `registration.php`, `module.xml`, `di.xml`, `acl.xml`
- Frontend & admin route configuration
- All standard directories (Api, Model, Controller, Plugin, Observer, etc.)
- Sample PHPUnit test
- Translation file

## 📁 Directory Structure

```
├── .claude/                    # Claude Code configuration
│   ├── settings.json           # Permissions & hooks
│   ├── agents/                 # AI agent personas
│   ├── commands/               # Slash commands
│   ├── rules/                  # Coding rules (glob-scoped)
│   └── skills/                 # Modular skills
├── CLAUDE.md                   # Claude context document
├── app/code/Vendor/ModuleName/ # Module source template
├── composer.json               # Dependencies
├── phpunit.xml.dist            # PHPUnit config
├── phpcs.xml.dist              # PHPCS config
├── phpstan.neon.dist           # PHPStan config
├── phpmd.xml.dist              # PHPMD config
├── captainhook.json            # Git hooks
├── Makefile                    # Developer commands
├── scripts/                    # Setup & utility scripts
└── docker/                     # Optional Docker setup
```

## 🛠 Make Commands

```bash
make help           # Show all available commands
make init           # First-time setup (rename Vendor/ModuleName)
make install        # Install Composer dependencies
make test           # Run PHPUnit tests
make test-coverage  # Run tests with HTML coverage
make lint           # Run PHPCS
make lint-fix       # Auto-fix with PHP-CS-Fixer
make phpstan        # Run PHPStan
make phpmd          # Run PHPMD
make check          # Run ALL quality checks
make check-setup    # Verify environment
make clean          # Clean generated files
```

## 🤖 Claude Slash Commands

When working with Claude Code, use these commands:

| Command | Description |
|---------|-------------|
| `/start` | Initialize a dev session, check environment, show context |
| `/review` | Run PHPCS + PHPStan + PHPMD on changed files, provide review |
| `/test` | Run tests, report results and coverage |
| `/lint` | Run all linters, offer to auto-fix |
| `/ship` | Full pre-push pipeline (lint → test → security → changelog) |
| `/status` | Quick project health dashboard |

## 🐳 Docker (Optional)

A Docker Compose setup is included for local development:

```bash
cd docker
cp .env.docker.example .env
docker compose up -d
```

Services: PHP 8.2-FPM, Nginx, MySQL 8.0, OpenSearch 2.x, Redis 7, Mailhog.

## 📝 Customization

### Adding a New Module
1. Create `app/code/YourVendor/NewModule/`
2. Add `registration.php` and `etc/module.xml`
3. Update root `composer.json` autoload
4. Write tests in `Test/Unit/`

### Adding Claude Rules
Create a new `.md` file in `.claude/rules/` with glob frontmatter:
```yaml
---
globs:
  - "**/*.php"
---
# Your custom rules here
```

### Adding Claude Skills
Create a directory in `.claude/skills/<skill-name>/` with a `SKILL.md` file.

## 📋 Requirements

- PHP 8.2+
- Composer 2.x
- Git
- Claude CLI (optional, for AI features)

## 📄 License

MIT

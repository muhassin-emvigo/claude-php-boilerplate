# Magento 2 PHP Boilerplate for Claude Code

Production-ready Magento 2.4.x module skeleton with **integrated Claude Code integration** — AI-powered SDLC pipeline, quality tooling, skills, rules, and commands out of the box.

> **AI-Assisted Development:** Use `/pipeline` to take features or bugs from raw request → shipped PR through planning, review gauntlets, TDD, testing gates, security review, docs, and ship. Or use traditional make commands for manual control.

## 🚀 Quick Start

```bash
# 1. Clone
git clone <repo-url> my-magento-module
cd my-magento-module

# 2. Install Claude dependencies (required for AI features)
# See Prerequisites below

# 3. Initialize (renames Vendor/ModuleName to your names)
make init

# 4. Install dependencies
make install

# 5. Verify environment
make check-setup

# 6. Run sample test
make test

# 7. Start building!
```

## ✨ What's Inside

### AI-Powered SDLC Pipeline

**One command:** `/pipeline` orchestrates the entire workflow:

| Stage | Purpose |
|-------|---------|
| **Classify** | Size request (nano vs full) |
| **Plan** | Draft executable plan w/ brainstorming & spec |
| **Review** | Parallel eng/design/CEO reviews (hard gate until you approve) |
| **Execute** | Implementer builds with test-first if opted in |
| **Quality** | Unit tests, QA scenarios, code review, security review (OWASP + STRIDE) |
| **Ship** | Docs updated, PR merged |

See [Usage: `/pipeline` Examples](#usage-pipeline-examples) below.

### Claude Code Integration

| Feature | Location | Purpose |
|---------|----------|---------|
| **Skills** | `.claude/skills/` | Module scaffolding, test writing, code review |
| **Rules** | `.claude/rules/` | PHP standards, Magento conventions, security, testing |
| **Commands** | `.claude/commands/` | `/start`, `/review`, `/test`, `/lint`, `/ship`, `/status` |
| **Agents** | `.claude/agents/` | Specialist sub-agents (planner, reviewer, implementer, etc.) |
| **Hooks** | `.claude/settings.json` | Token tracking, context budgets, pre-commit validation |

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

Complete Magento 2.4.x module skeleton at `app/code/Vendor/ModuleName/`:
- `registration.php`, `module.xml`, `di.xml`, `acl.xml`
- Frontend & admin route configuration
- All standard directories (Api, Model, Controller, Plugin, Observer, ViewModel, etc.)
- Sample PHPUnit test
- Translation file
- Declarative schema (`db_schema.xml`)

## Prerequisites

### For Magento Development (always required)

- **PHP 8.2+**
- **Composer 2.x**
- **Git**
- MySQL 8.0 or MariaDB 10.6+
- Optional: OpenSearch 2.x, Redis 7.x, Docker

### For AI-Powered Pipeline (`/pipeline` command)

All three dependencies are **required** — `/pipeline` halts at preflight if any missing. Install **before** enabling the pipeline:

#### Python 3

Bundled hooks (`guard_context_budget.py`, `check_plan.py`) need **Python 3.8+** on your `PATH`:

```bash
# Verify
python3 --version   # should print Python 3.8 or later
```

Install if missing:
```bash
# macOS (Homebrew)
brew install python

# Ubuntu / Debian
sudo apt install python3

# Windows (winget)
winget install Python.Python.3
```

#### Claude Code + Plugins

1. **Claude Code CLI** — install from [claude.com/code](https://claude.com/code)
2. **superpowers** — provides brainstorming, writing-plans, executing-plans, test-driven-development
   ```
   /plugin marketplace add obra/superpowers-marketplace
   /plugin install superpowers@superpowers-marketplace
   ```
3. **gstack** — provides `/office-hours`, `/spec`, `/ship`
   ```
   git clone --single-branch --depth 1 https://github.com/garrytan/gstack.git ~/.claude/skills/gstack
   cd ~/.claude/skills/gstack && ./setup
   ```
4. **claude-mem** — persistent memory across sessions
   ```
   /plugin marketplace add thedotmack/claude-mem
   /plugin install claude-mem
   ```

> [!IMPORTANT]
> ⚠️ 5-minute prompt cache active. Set `ENABLE_PROMPT_CACHING_1H=1` and restart for ~25% lower cost.

## Installation

### 1. Project Setup

```bash
git clone <repo-url> my-magento-module
cd my-magento-module
make init           # Rename Vendor/ModuleName
make install        # Composer dependencies
make check-setup    # Verify environment
```

### 2. Claude Code Setup (optional, enables `/pipeline`)

#### Claude Code (CLI)

1. Install Python 3 and plugin dependencies (see [Prerequisites](#for-ai-powered-pipeline-pipeline-command) above)
2. Add marketplace:
   ```
   /plugin marketplace add nithinemvigo/sdlc-pipeline
   ```
3. Install plugin:
   ```
   /plugin install sdlc-pipeline@sdlc-pipeline-dev
   ```
4. Restart Claude or run `/reload-plugins`
5. Verify: type `/` and confirm `/pipeline` appears

#### Claude Cowork / Claude Desktop

1. Open the **Customize** menu in the left sidebar, then the **Plugins** tab
2. In **Personal plugins**, click **+** → **Add marketplace** → **Add from a repository**
3. Paste: `https://github.com/nithinemvigo/sdlc-pipeline`
4. Once marketplace syncs, click **Install** on **sdlc-pipeline**
5. Start a new conversation for plugin to take effect

Note: hooks and sub-agents run in Cowork and Claude Code; in plain chat only skills available.

#### Cursor

1. Open Cursor **Settings** → **Plugins** (or **Rules & Memories → Plugins**, depending on version)
2. Click **Add Plugin** → **From Git repository**
3. Paste: `https://github.com/nithinemvigo/sdlc-pipeline`
4. Cursor reads `.cursor-plugin/plugin.json` and installs plugin
5. Reload Cursor window (`Cmd/Ctrl+Shift+P` → "Reload Window")

Alternatively, clone repo and copy `agents/` and `commands/` into project's `.cursor/` directory if version doesn't support Git plugin installs.

#### Updating

Marketplace installs don't auto-pull commits. Get latest:

- **Claude Code:** `/plugin marketplace update sdlc-pipeline-dev`
- **Cowork/Desktop:** Customize → Plugins → find marketplace → ⋯ menu → **Update**, then start new session

### 3. Full Magento Setup (if building a full storefront, not just a module)

#### Docker (recommended)
```bash
cd docker
cp .env.docker.example .env
docker compose up -d
# Services: PHP 8.2-FPM, Nginx, MySQL, OpenSearch, Redis, Mailhog
```

#### Windows/XAMPP (native, no Docker)
```powershell
copy auth.json.example auth.json  # Add Magento Marketplace keys
make magento-install
make magento-run
```

See [docs/install-magento/](docs/install-magento/) for full step-by-step walkthrough.

## Usage

### Option A: AI-Assisted with `/pipeline`

#### Example 1 — Build a feature
```
/pipeline Add CSV export to the reports page
```

**What happens:**
1. Classify request (nano vs full)
2. Ask preferences: bug/feature, test-first, unit tests?
3. Plan with superpowers `brainstorming` + `/office-hours`
4. Parallel reviews: eng-reviewer, design-reviewer, ceo-reviewer
5. **You approve plan** (hard gate, no code until yes)
6. Implementer builds on dedicated branch
7. Quality gates: unit tests, QA scenarios, code review, security review
8. Docs & ship

#### Example 2 — Fix a bug
```
/pipeline Login button does nothing after session timeout
```

Skips planner; `investigator` does root-cause analysis → mini-plan in `docs/bugs/` → execution.

#### Example 3 — Tiny change (nano tier)
```
/pipeline Fix the typo in the welcome banner
```

Skips planning & reviews: `implementer` → `code-reviewer` → `ship-pr`. Fast path, still reviewed.

### Option B: Manual with Make Commands

```bash
make help           # Show all commands
make test           # Run PHPUnit
make test-coverage  # Tests + HTML coverage report
make lint           # Run PHPCS
make lint-fix       # Auto-fix with PHP-CS-Fixer
make phpstan        # Static analysis
make phpmd          # Mess detection
make check          # Run ALL quality checks
make usage          # Show token/cost usage
```

### Claude Slash Commands (all modes)

| Command | Description |
|---------|-------------|
| `/start` | Initialize session, check environment, show context |
| `/review` | Run PHPCS + PHPStan + PHPMD, provide review |
| `/test` | Run tests, report results & coverage |
| `/lint` | Run all linters, offer to auto-fix |
| `/ship` | Full pre-push pipeline (lint → test → security → changelog) |
| `/status` | Quick health dashboard |

## 📁 Directory Structure

```
├── .claude/                        # Claude Code configuration
│   ├── agents/                     # Specialist sub-agents
│   ├── commands/                   # Slash commands
│   ├── hooks/                      # Context budget, pre-commit validation
│   ├── rules/                      # Coding rules (glob-scoped, priority-ordered)
│   └── skills/                     # Modular development skills
├── CLAUDE.md                       # Claude context document
├── app/code/Vendor/ModuleName/     # Module source template
│   ├── Api/                        # Service contract interfaces
│   ├── Block/                      # Block classes
│   ├── Controller/                 # Request handlers
│   ├── Helper/                     # Helper classes
│   ├── Model/                      # Business logic
│   ├── Observer/                   # Event observers
│   ├── Plugin/                     # Interceptors
│   ├── Setup/Patch/                # Data patches
│   ├── ViewModel/                  # Presentation logic
│   ├── etc/                        # Configuration XML
│   ├── view/                       # Templates, layouts, assets
│   ├── i18n/                       # Translations
│   └── Test/                       # PHPUnit tests
├── composer.json                   # Dependencies
├── phpunit.xml.dist                # PHPUnit config
├── phpcs.xml.dist                  # PHPCS config
├── phpstan.neon.dist               # PHPStan config
├── phpmd.xml.dist                  # PHPMD config
├── captainhook.json                # Git hooks
├── Makefile                        # Developer commands
├── scripts/                        # Setup & utility scripts
└── docker/                         # Optional Docker setup
```

## 🛠 Make Commands (quick reference)

```bash
make init            # First-time: rename Vendor/ModuleName
make install         # Install Composer dependencies
make test            # Run PHPUnit tests
make test-coverage   # Tests with HTML coverage
make lint            # Run PHPCS (Magento2 standard)
make lint-fix        # Auto-fix with PHP-CS-Fixer
make phpstan         # PHPStan static analysis (level 6)
make phpmd           # PHPMD mess detection
make check           # Run ALL quality checks
make check-setup     # Verify environment
make magento-install # Install full Magento core (Docker or native)
make magento-run     # Start services & open storefront
make fix-windows     # Re-apply Windows vendor bugfixes
make clean           # Clean generated files
make usage           # Show Claude token usage
```

## 🐳 Docker (Optional)

Complete stack for local development:

```bash
cd docker
cp .env.docker.example .env
docker compose up -d
```

Services: PHP 8.2-FPM, Nginx, MySQL 8.0, OpenSearch 2.x, Redis 7, Mailhog.

## 🪟 Full Magento Install (Windows/XAMPP, no Docker)

```powershell
copy auth.json.example auth.json  # Add Magento Marketplace keys
make magento-install              # Install full Magento core
make magento-run                  # Start MySQL/OpenSearch/Apache
```

Script checks PHP/extensions, sets up Composer auth, creates DB, installs Magento core, applies Windows bugfixes (drive letters, backslashes, filename issues), and runs setup:install. Safe to re-run — upgrades if env.php exists.

Requires OpenSearch on localhost:9200 — `make magento-run` starts it automatically if installed.

### Magento Marketplace Keys

Needed to pull `magento/product-community-edition`:

1. Create account at [marketplace.magento.com](https://marketplace.magento.com/)
2. Go to **My Profile → Access Keys**, generate key pair
3. Add to **one** of:
   - `auth.json` (root, copy from `auth.json.example`) — used by native XAMPP path
   - `docker/.env` as `MAGENTO_PUBLIC_KEY` / `MAGENTO_PRIVATE_KEY` — used by Docker

Both are gitignored; only `.example` templates tracked.

## 📝 Customization

### Adding a New Module

```bash
mkdir -p app/code/YourVendor/NewModule/etc
# Add registration.php and etc/module.xml
# Update root composer.json autoload
# Write tests in Test/Unit/
```

### Adding Claude Rules

Create `.md` file in `.claude/rules/` with glob frontmatter:

```yaml
---
globs:
  - "**/*.php"
priority: 30
---
# Your custom rules here
```

See `.claude/rules/README.md` for priority guidance.

### Adding Claude Skills

Create directory `.claude/skills/<skill-name>/` with `SKILL.md`.

## 📋 Requirements

- **PHP 8.2+** (strict_types, readonly, constructor promotion, enums, match, union types)
- **Composer 2.x**
- **Git**
- **MySQL 8.0** or **MariaDB 10.6+**
- **Claude Code CLI** (optional, for AI features — not needed for manual development)

## 📄 License

MIT — free to use, modify, and distribute. See [`LICENSE`](LICENSE).

---

## Workflow Diagram (with `/pipeline`)

```
request
   │
   ▼
Stage 0    Classify (nano fast-path ─────────────────────┐)
Stage 0.5  Your preferences: bug/feature, TDD, unit tests │
   │                                                      │
   ├── FEATURE ──► Stage 1  Plan (planner)                │
   │               Stage 2  Plan reviews ∥ (eng/design/ceo)
   │               Stage 2.5  ★ YOU APPROVE THE PLAN ★    │
   │               Stage 2.7  ADR (if architecture changes)
   │                                                      │
   └── BUG ──────► Stage 1-B  Investigate (root cause)    │
   │                                                      │
   ▼                                                      │
Stage 3    Execute on dedicated branch (TDD if on) ◄──────┘
Stage 4    Unit-test gate (if opted in)
Stage 4.5  QA scenario gate (if user-facing)
Stage 5    Code review ∥ Security review (OWASP + STRIDE)
Stage 5.5  Performance gate (if perf-sensitive)
Stage 5.8  Documentation
Stage 6    Ship PR
```

`∥` = stages run in parallel. Token usage tracked per run.

## Hard Rules Enforced

- No production code before plan approval (Stage 2.5 is hard gate)
- Orchestrator never writes code — only specialist agents do
- Reviewers read-only, report-only; never "fix while reviewing"
- Implementers sequential (share working tree); reviewers parallel

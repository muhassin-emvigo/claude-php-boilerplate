# Install Planner — Magento Environment Setup

Agent-planning style runbook for installing PHP, Composer, a database, a search
engine, and Magento itself. Read this file first — it identifies the OS, then
routes to the matching step-by-step doc. Do not skip the "Detect" step: the
install steps differ per OS/stack and running the wrong one wastes time.

---

## Step 0 — Identify

Before doing anything, resolve and display these facts (this is the "planner
display" step — print them before starting installs, and again after, so
drift is visible):

```
OS            : <Windows | macOS | Linux distro+version>
Web stack     : <XAMPP | WAMP | native nginx/Apache | Docker>
PHP target    : <version — see versions.md>
Composer      : <version — see versions.md>
Magento       : <version — see versions.md, and check this repo's composer.json pin>
Database      : <MySQL | MariaDB, version>
Search engine : <OpenSearch | Elasticsearch, version>
```

Detection commands:

```bash
# OS
uname -s 2>/dev/null || echo Windows

# PHP (if already installed)
php -v

# Composer (if already installed)
composer --version

# Existing Magento pin in this repo
grep -A2 '"magento/product-community-edition"' composer.json 2>/dev/null
```

Then check [versions.md](versions.md) for the current "latest known" table and
reconcile against what this repo pins (`install-magento.sh` currently pins
`2.4.7-p8` — see that file's "Pinned vs latest" section for how to bump it).

---

## Step 1 — Route by OS

| Detected OS | Stack | Go to |
|---|---|---|
| Windows | XAMPP (this repo's default, `C:\xampppp`) | [windows-xampp.md](windows-xampp.md) |
| Windows | WAMP | [windows-wamp.md](windows-wamp.md) |
| macOS | Homebrew + native services | [macos.md](macos.md) |
| Linux | apt/dnf + native services | [linux.md](linux.md) |
| Any | Docker (OS-agnostic) | use `install-magento.sh` directly — see [../00-first-time-setup.md](../00-first-time-setup.md) is XAMPP-focused; Docker path needs no OS-specific doc, just Docker Desktop/Engine. |

Each OS doc follows the same four install stages in order:

1. **PHP** — install/verify version + required extensions
2. **Composer** — install/verify, wire Magento Marketplace auth (`auth.json`)
3. **Database** — install/verify MySQL or MariaDB, create the `magento` schema
4. **Magento** — install search engine, then run the Magento installer

---

## Step 2 — Common prerequisites (all OSes)

- **Magento Marketplace keys** (public + private) — required by Composer to
  pull `magento/product-community-edition`. Free account at the Adobe
  Commerce Marketplace. Stored in `auth.json` (local) or `docker/.env`
  (Docker path) — never commit real keys.
- **Required PHP extensions**: bcmath, curl, gd, intl, mbstring, pdo_mysql,
  soap, sockets, sodium, xsl, zip, openssl.
- **Ports**: 80/443 (web), 3306 (MySQL), 9200 (search), 6379 (Redis, if used).
  XAMPP on Windows commonly conflicts with Docker's nginx on port 80 — this
  repo's Docker path defaults `NGINX_PORT=8080` to avoid that.

---

## Step 3 — Run the install

Follow the OS-specific doc for stages 1–3, then finish with whichever
installer script matches your stack:

| Stack | Script |
|---|---|
| Windows + XAMPP | `.\scripts\install-magento-xampp.ps1` (or `make magento-install`) |
| Any + Docker | `bash install-magento.sh` |
| macOS / Linux native | manual `bin/magento setup:install` — see [macos.md](macos.md) / [linux.md](linux.md) |

---

## Step 4 — Start it

Windows + XAMPP: run `.\scripts\start-magento.ps1` (or `make magento-run`) instead of
starting services by hand — it starts Apache/MySQL/OpenSearch and opens
`http://localhost/rgd_dental/`. See
[../00-first-time-setup.md](../00-first-time-setup.md) Step 6.

Other stacks: start web server, DB, search engine per the OS doc's stage
commands (no wrapper script yet for WAMP/macOS/Linux native — see
[windows-wamp.md](windows-wamp.md), [macos.md](macos.md), [linux.md](linux.md)).

## Step 5 — Verify and display

After install completes, re-run the Step 0 detection block and confirm the
displayed versions match what you planned for:

```bash
php -v
composer --version
bin/magento --version
mysql --version
curl -s localhost:9200 | head -1   # search engine version
```

If any version differs from the plan, that's a signal something resolved
differently than expected (e.g. Composer picked an older Magento due to a
version constraint) — investigate before continuing, don't assume it's fine.

---

## Troubleshooting

Known Windows-specific Magento bugs and admin-login issues are already
documented and auto-fixed — see
[../00-first-time-setup.md](../00-first-time-setup.md) "If something goes
wrong" section and `scripts/fix-windows-vendor-bugs.php`.

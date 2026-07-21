# Installing Magento From Scratch (Any Operating System)

## 1. Purpose

Provide a single, OS-agnostic reference for installing a working Magento 2
environment — PHP, Composer, MySQL/MariaDB, a web server, and a search
engine — from a bare machine to a running storefront and admin panel. This
guide intentionally avoids assuming Windows/XAMPP, macOS/Homebrew, or Linux;
each step lists the equivalent command or action for all three, so it can be
followed on whichever machine a developer is setting up.

If you are specifically on Windows with XAMPP and want the fastest path
using this project's own automation scripts, use
[00-first-time-setup.md](00-first-time-setup.md) instead — that guide wraps
everything below into two scripts. Use this document when: setting up on
macOS or Linux, setting up without XAMPP, or when you need to understand
what those scripts are doing under the hood.

## 2. Scope

### In Scope

- Installing PHP with the correct version and extensions
- Installing and authenticating Composer
- Installing and configuring MySQL/MariaDB
- Installing and configuring a web server (Apache or Nginx)
- Installing a search engine (OpenSearch or Elasticsearch)
- Downloading Magento via Composer and running the installer
- Setting file permissions, cron, and deploy mode
- Verifying the install and troubleshooting the most common failures

### Out of Scope

- Production hardening, load balancing, CDN, or multi-server setups
- Docker/containerized installs (a valid alternative, not covered here)
- Upgrading an existing Magento install to a newer version

## 3. Prerequisites

**Current version matrix (verified against Adobe's official system
requirements docs — check
[experienceleague.adobe.com/.../system-requirements](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/system-requirements)
yourself before installing, since Adobe ships new minor versions and
patches regularly):**

| Magento Version | Status (as of mid-2026) | PHP | Composer | Database | Search Engine | Cache/Session | Message Queue |
|---|---|---|---|---|---|---|---|
| **2.4.9** | Latest stable (GA May 12, 2026; supported through May 12, 2029) | 8.5 | 2.9.3+ | MySQL 8.4, MariaDB 11.8/12.3 | OpenSearch 3 | Valkey 9, Varnish 8 | RabbitMQ 4.2 |
| **2.4.8-p5** | Previous stable line, still supported, broader ecosystem/extension compatibility | 8.3, 8.4 | 2.9.3+ | MySQL 8.4, MariaDB 11.4/11.8 | OpenSearch 3 (Elasticsearch dropped) | Valkey 8.1 (Redis unsupported) | RabbitMQ 4.2 |

If you're setting up a brand-new project with no third-party extension
constraints, install **2.4.9** with **PHP 8.5**. If any extension, payment
module, or hosting provider you depend on hasn't caught up to PHP 8.5 yet
(common in the months right after a major PHP bump), use **2.4.8-p5** with
**PHP 8.3 or 8.4** instead — it's still an actively supported release, just
one line behind latest. Either way, keep the PHP, database, search engine,
and cache versions matched to whichever Magento line you pick — mixing
versions across lines (e.g. Magento 2.4.8 with PHP 8.5, or Magento 2.4.9
with Elasticsearch) is unsupported and a common source of hard-to-diagnose
failures.

**Note on Redis:** Recent Magento versions have moved from Redis to
**Valkey** (a Redis-compatible open-source fork) for cache and session
storage. If you're following an older guide that says "install Redis,"
substitute Valkey — the config keys and CLI are drop-in compatible.

| Component | Notes |
|---|---|
| PHP | See version matrix above; extensions listed below |
| Composer | 2.9.3+ |
| MySQL or MariaDB | See version matrix above; one database instance, one schema |
| Web server | Apache 2.4+ or Nginx 1.18+ — either works; Nginx needs an explicit rewrite config |
| Search engine | OpenSearch 3 (required — Elasticsearch is no longer supported on 2.4.8+) |
| Cache/session store | Valkey (Redis-compatible) — optional for local dev, recommended for anything production-like |
| Node.js (optional) | 18.x+ — only needed if you'll customize frontend build tooling (Grunt/Webpack) |
| Magento Marketplace keys | Free account at the Adobe/Magento Marketplace; needed as Composer credentials |

Required PHP extensions: `bcmath`, `curl`, `gd` (or `imagick`), `intl`,
`mbstring`, `openssl`, `pdo_mysql`, `soap`, `sockets`, `sodium`, `xsl`, `zip`.
This list has stayed stable across recent Magento versions, but always cross-check
the extension list for your exact target version on Adobe's system
requirements page — new versions occasionally add one (e.g. `openssl` and
`sodium` were both later additions to the historical list).

## 4. Installing PHP

Pick the PHP version matching the Magento line you chose in section 3 —
**8.5** for Magento 2.4.9, or **8.3/8.4** for Magento 2.4.8-p5. XAMPP in
particular tends to lag a release or two behind the newest PHP, so check
what version your XAMPP bundle ships before assuming it has 8.5 — you may
need a standalone PHP install instead if it doesn't.

| OS | Commands |
|---|---|
| Windows (no XAMPP) | Download the matching PHP build from windows.php.net, extract, add to `PATH`. Enable extensions by uncommenting the matching `extension=` lines in `php.ini`. |
| Windows (XAMPP) | Check the bundled PHP version first (`php -v`); if it's older than your target, install a standalone PHP build instead of relying on XAMPP's. Edit `php.ini` inside the PHP folder to enable extensions, then restart Apache. |
| macOS (Homebrew) | `brew install php@8.4` (swap `8.4` for `8.5`/`8.3` to match your target) then `brew link php@8.4 --force` |
| Linux (Debian/Ubuntu) | `sudo apt update && sudo apt install php8.4 php8.4-{bcmath,curl,gd,intl,mbstring,soap,xsl,zip,mysql,sodium}` (swap `8.4` for your target version; you may need the `ondrej/php` PPA for the newest releases) |
| Linux (RHEL/Alma/Rocky) | `sudo dnf install php php-{bcmath,gd,intl,mbstring,soap,xml,mysqlnd}` (enable the Remi repo for newer PHP versions than the default OS repo ships) |

Verify with:

```bash
php -v
php -m
```

`php -m` should list every extension from the table in section 3. If one is
missing, the Magento installer will fail early with a clear "Required
extension X missing" message — install the extension and re-run rather than
troubleshooting further downstream.

## 5. Installing Composer

| OS | Commands |
|---|---|
| Windows | Download and run the Composer-Setup.exe installer from getcomposer.org |
| macOS | `brew install composer` |
| Linux | `curl -sS https://getcomposer.org/installer \| php` then `sudo mv composer.phar /usr/local/bin/composer` |

Verify with `composer --version` (expect `Composer version 2.x`).

### Composer authentication (Magento Marketplace keys)

Every OS uses the same mechanism — Composer reads Magento Marketplace
credentials from a global `auth.json`:

```bash
composer global config http-basic.repo.magento.com <public-key> <private-key>
```

Alternatively, per-project, copy `auth.json.example` to `auth.json` in the
project root and fill in the same public key (username) and private key
(password).

## 6. Installing MySQL or MariaDB

| OS | Commands |
|---|---|
| Windows (no XAMPP) | Install MySQL Community Server from dev.mysql.com, or MariaDB from mariadb.org |
| Windows (XAMPP) | Included; start it from the XAMPP Control Panel |
| macOS (Homebrew) | `brew install mysql && brew services start mysql` |
| Linux (Debian/Ubuntu) | `sudo apt install mysql-server && sudo systemctl enable --now mysql` |
| Linux (RHEL/Alma/Rocky) | `sudo dnf install mariadb-server && sudo systemctl enable --now mariadb` |

Create the database and a dedicated user (run via `mysql -u root -p`):

```sql
CREATE DATABASE magento CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'magento'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT ALL PRIVILEGES ON magento.* TO 'magento'@'localhost';
FLUSH PRIVILEGES;
```

Confirm MySQL is reachable before proceeding — a refused connection here
(`SQLSTATE[HY000] [2002]`) is one of the most common causes of "can't log
into admin" errors later, so it's worth testing directly:

```bash
mysql -u magento -p -h 127.0.0.1 magento -e "SELECT 1;"
```

## 7. Installing a search engine (OpenSearch)

Magento 2.4+ requires a search engine reachable on port 9200 *before* the
installer runs. As of Magento 2.4.8, **Elasticsearch is no longer
supported** — use **OpenSearch 3** for any current install.

| OS | Commands |
|---|---|
| Windows | Download OpenSearch 3.x (zip) for Windows, unzip, run `opensearch.bat` from the `bin` folder |
| macOS (Homebrew) | `brew install opensearch && brew services start opensearch` |
| Linux (Debian/Ubuntu) | Download the `.deb` from opensearch.org, `sudo dpkg -i opensearch-*.deb`, `sudo systemctl enable --now opensearch` |
| Docker (any OS) | `docker run -p 9200:9200 -e "discovery.type=single-node" -e "plugins.security.disabled=true" opensearchproject/opensearch:3` |

Always pull the exact current 3.x patch tag from
[opensearch.org's download page](https://opensearch.org/downloads.html)
rather than hardcoding a version here — OpenSearch ships its own frequent
patch releases independent of Magento's.

Verify with:

```bash
curl http://localhost:9200
```

You should get back a JSON response with a `"cluster_name"` field. If this
step is skipped, the Magento installer fails partway through with a
connection-refused error referencing Elasticsearch/OpenSearch — always
confirm this first, since it's cheaper to fix here than to debug later.

## 7a. Installing Valkey (cache/session store, optional for local dev)

Skip this for a minimal local dev setup — Magento falls back to filesystem
cache and database sessions without it. Install it if you want your local
environment to match a production-like setup, or if you're testing
cache/session behavior specifically.

| OS | Commands |
|---|---|
| Windows | Valkey doesn't ship official Windows binaries; use WSL2 or Docker instead |
| macOS (Homebrew) | `brew install valkey && brew services start valkey` |
| Linux (Debian/Ubuntu) | `sudo apt install valkey-server && sudo systemctl enable --now valkey-server` |
| Docker (any OS) | `docker run -p 6379:6379 valkey/valkey:8.1` |

## 8. Installing a web server

### Option A — Apache

| OS | Commands |
|---|---|
| Windows (XAMPP) | Included; ensure `mod_rewrite` is enabled in `httpd.conf` (it is, by default, in XAMPP) |
| macOS (Homebrew) | `brew install httpd` |
| Linux | `sudo apt install apache2` (Debian/Ubuntu) or `sudo dnf install httpd` (RHEL family); enable `mod_rewrite`: `sudo a2enmod rewrite` |

Magento ships its own `pub/.htaccess`, so no custom Apache rewrite rules are
required — just point your virtual host's document root at the project's
`pub/` folder (for a production-style deploy) or the project root (for
`bin/magento` to also serve correctly in developer mode without a
`pub`-rooted vhost, as this project's XAMPP setup does).

### Option B — Nginx

Nginx needs an explicit config, since it doesn't read `.htaccess` files.
Magento provides a generator:

```bash
php bin/magento setup:config:set # (after install, if switching web servers)
```

Or use the sample config template Magento ships at
`nginx.conf.sample` in the project root after Composer install, including it
from your site's `server {}` block.

## 9. Getting the Magento code

```bash
composer create-project --repository-url=https://repo.magento.com/ magento/project-community-edition <target-folder>
```

Or, if this repository already vendors a customized Magento setup (as this
project does), clone the repository instead and run `composer install` from
the project root — Composer will use the `auth.json` credentials from
section 5 to pull Magento's private packages.

## 10. Running the Magento installer

From the project root, with MySQL, the search engine, and the web server
all running:

```bash
php bin/magento setup:install \
  --base-url=http://localhost/<your-folder>/ \
  --db-host=127.0.0.1 \
  --db-name=magento \
  --db-user=magento \
  --db-password=a-strong-password \
  --admin-firstname=Admin \
  --admin-lastname=User \
  --admin-email=you@example.com \
  --admin-user=admin \
  --admin-password="A-Strong-Password1" \
  --language=en_US \
  --currency=USD \
  --timezone=America/Chicago \
  --use-rewrites=1 \
  --search-engine=opensearch \
  --opensearch-host=localhost \
  --opensearch-port=9200
```

This single command creates all database tables, writes `app/etc/env.php`
and `app/etc/config.php`, and creates the initial admin user. It takes
several minutes on first run.

## 11. File permissions (macOS/Linux only)

Windows doesn't need this step. On macOS/Linux, Magento needs write access
to several directories:

```bash
find var generated vendor pub/static pub/media app/etc -type d -exec chmod 770 {} \;
find var generated vendor pub/static pub/media app/etc -type f -exec chmod 660 {} \;
chown -R :<your-web-server-group> .
```

## 12. Deploy mode and static content

Local development should run in `developer` mode, which skips static
content deployment and gives full error output:

```bash
php bin/magento deploy:mode:set developer
```

For a production-style deploy, use `production` mode instead, and run:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
```

**Important cross-platform gotcha:** running `setup:di:compile` creates
`generated/metadata`. If that directory exists on disk, Magento silently
switches to "Compiled" mode *regardless* of what `deploy:mode:set` says —
and in Compiled mode, no `AbstractAction`-level plugins run, which breaks
admin login (the login form silently redisplays with no error message) and
produces "Broken reference" warnings in `var/log/debug.log` for `header`,
`footer`, and `messages` layout containers. If you're developing locally and
see this symptom, delete `generated/metadata` (keep `generated/code`) and
flush cache:

```bash
rm -rf generated/metadata   # Linux/macOS
rmdir /s /q generated\metadata   # Windows
php bin/magento cache:flush
```

## 13. Cron

Magento relies on cron for indexing, email queues, and scheduled tasks.

| OS | Setup |
|---|---|
| macOS/Linux | `php bin/magento cron:install` registers entries in the system crontab automatically |
| Windows | Use Windows Task Scheduler to run `php bin/magento cron:run` every minute, or use this project's `start-magento.ps1` which runs a background loop |

## 14. Verifying the install

```bash
php bin/magento setup:upgrade    # should complete with no errors
php bin/magento cache:flush
php bin/magento indexer:reindex
```

Then open `http://localhost/<your-folder>/` in a browser (storefront) and
`http://localhost/<your-folder>/admin` (admin — Magento generates a
random, longer secret path unless `--backend-frontname` was set explicitly
during install; check `app/etc/env.php`'s `backend.frontName` value if
unsure).

## 15. Troubleshooting

| Symptom | Likely Cause | Fix |
|---|---|---|
| `SQLSTATE[HY000] [2002]` connection refused | MySQL/MariaDB service isn't running | Start the DB service; confirm with `mysql -u ... -e "SELECT 1"` |
| Installer fails referencing Elasticsearch/OpenSearch | Search engine not running or wrong port | Confirm `curl http://localhost:9200` returns a JSON response before installing |
| Admin/storefront pages show a raw PHP fatal error | Missing PHP extension | Run `php -m` and compare against section 3's list |
| Admin login page keeps reappearing, no error shown, "Broken reference" warnings for header/footer in logs | `generated/metadata` present, forcing Compiled mode | See section 12 — delete `generated/metadata`, keep `generated/code`, flush cache |
| Admin login fails with no error, no obvious cache/compiled-mode issue | `Magento_ReCaptchaUser` enabled without reCAPTCHA keys configured | `php bin/magento module:disable Magento_ReCaptchaUser && php bin/magento setup:upgrade && php bin/magento cache:flush` |
| New/edited module doesn't appear anywhere in the site | `setup:upgrade` was never run since the module was added | Run `php bin/magento setup:upgrade`; confirm the module now appears in `app/etc/config.php`'s `modules` array |
| `bin/magento` command not found | Composer install didn't complete, or you're not in the project root | Re-run `composer install`; check for errors earlier in the output |

## 16. Assumptions

- You have administrative/sudo rights on the machine being set up.
- Network access is available to reach Composer's package repositories and
  the Magento Marketplace.
- You already have a Magento Marketplace account and Access Keys (free to
  create).

## 17. Dependencies

- PHP 8.2+ with required extensions
- Composer 2.x
- MySQL 8.0+ or MariaDB 10.6+
- A web server (Apache or Nginx)
- OpenSearch 2.x or Elasticsearch 7.x/8.x
- A Magento Marketplace account and Access Keys

## 18. Definition of Done

- PHP, Composer, MySQL/MariaDB, the web server, and the search engine are
  all installed and independently verified as running.
- `php bin/magento setup:install` completes without errors.
- The storefront home page loads without errors.
- The admin login page accepts valid credentials and reaches the dashboard.
- `php bin/magento cron:install` (or the OS equivalent) is configured.

# Windows + WAMP — Step by Step

This repo's scripts (`install-magento-xampp.ps1`, `start-magento.ps1`) are
written against XAMPP paths (`C:\xampppp\...`). WAMP works the same way
conceptually but different default paths — you'll need to either adjust those
scripts' path variables or follow the manual steps below.

Prereq: read [planner.md](planner.md) Step 0 first.

## Stage 1 — PHP

1. Install WAMP (WampServer) — pick a version bundling PHP 8.2+ (8.4 for
   latest Magento). WAMP defaults to `C:\wamp64\`.
2. Enable required extensions via the WampServer tray icon (PHP > PHP
   extensions) or by editing `C:\wamp64\bin\php\php<version>\php.ini`:
   bcmath, curl, gd, intl, mbstring, pdo_mysql, soap, sockets, sodium, xsl,
   zip, openssl.
3. Restart all WAMP services after changing extensions.
4. Verify: `php -v` (make sure `C:\wamp64\bin\php\php<version>` is on PATH,
   or use the WAMP-provided shell).

## Stage 2 — Composer

1. Install from [getcomposer.org/download](https://getcomposer.org/download/).
   Verify: `composer --version` — target 2.9+.
2. Copy `auth.json.example` to `auth.json` in the project root, fill in your
   Magento Marketplace public/private keys.

## Stage 3 — Database

1. Start MySQL/MariaDB via the WampServer tray icon.
2. Create the schema:
   ```sql
   CREATE DATABASE magento CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```
3. WAMP's default MySQL root user has no password unless you set one —
   confirm what credentials you're passing to `bin/magento setup:install`
   below.

## Stage 4 — Search engine

Install OpenSearch (2.11 to match this repo's tested baseline, or 3.x for
latest Magento) standalone — WAMP doesn't bundle it. Download the Windows
distribution, unzip anywhere, run `opensearch.bat`, confirm it answers on
`localhost:9200`.

## Stage 5 — Magento install

No WAMP-specific script exists in this repo yet. Either:

- **Adapt the XAMPP script**: copy `scripts/install-magento-xampp.ps1`, change
  `$XamppRoot = 'C:\xampppp'` to your WAMP root and the PHP/MySQL binary
  subpaths, then run it, or
- **Run manually** from the project root, once PHP/Composer/MySQL/OpenSearch
  above are confirmed working:
  ```powershell
  composer install
  php bin/magento setup:install `
    --base-url=http://localhost/rgd_dental/ `
    --db-host=127.0.0.1 --db-name=magento --db-user=root --db-password= `
    --admin-firstname=Admin --admin-lastname=User `
    --admin-email=admin@example.com --admin-user=admin --admin-password=Admin123! `
    --backend-frontname=admin --language=en_US --currency=USD `
    --timezone=America/Chicago --use-rewrites=1 `
    --search-engine=opensearch --opensearch-host=localhost --opensearch-port=9200 `
    --no-interaction
  php bin/magento setup:di:compile
  php bin/magento setup:static-content:deploy -f en_US
  php bin/magento deploy:mode:set developer
  ```

## Display versions after install

```powershell
php -v
composer --version
php bin\magento --version
mysql --version
```

# Windows + XAMPP — Step by Step

Prereq: read [planner.md](planner.md) Step 0 first and confirm you're targeting
this stack. Versions referenced below are pulled from [versions.md](versions.md)
— re-check that file if it's stale.

## Stage 1 — PHP

1. Install XAMPP with PHP 8.2+ bundled (this repo assumes XAMPP at `C:\xampppp`).
   For Magento 2.4.9, target PHP 8.4 instead if you're going latest — XAMPP's
   bundled PHP version depends on which XAMPP build you download, so verify
   with `php -v` after install rather than assuming.
2. Enable required extensions in `C:\xampppp\php\php.ini` (uncomment the
   `extension=` lines): bcmath, curl, gd, intl, mbstring, pdo_mysql, soap,
   sockets, sodium, xsl, zip, openssl.
3. Restart Apache from the XAMPP Control Panel after editing `php.ini`.
4. Verify: `php -v` and `php -m | findstr bcmath` (repeat per extension).

Known issue: `bcmath` DLL is sometimes missing from XAMPP's PHP build on
Windows. If `php -m` doesn't list it after enabling:
- Confirm `C:\xampppp\php\ext\php_bcmath.dll` exists; if not, copy it from a
  fresh XAMPP zip of the same PHP version.
- Install "Microsoft Visual C++ Redistributable 2015-2022 (x64)".
- Restart Apache, re-check.

## Stage 2 — Composer

1. Install from [getcomposer.org/download](https://getcomposer.org/download/)
   (Windows installer). Verify: `composer --version` — target 2.9+ for
   Magento 2.4.9 (2.10.2 is current latest).
2. Magento Marketplace auth — copy `auth.json.example` to `auth.json` in the
   project root and fill in your Marketplace public key (username) / private
   key (password). Never commit real keys.

## Stage 3 — Database

1. Start MySQL from the XAMPP Control Panel (bundled MySQL is typically 8.0.x
   on XAMPP; MariaDB 10.6+ also works if that's what your XAMPP build ships).
2. Create the database (the install script does this too, but to do it
   manually):
   ```sql
   CREATE DATABASE magento CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```

## Stage 4 — Search engine

Magento needs OpenSearch (or Elasticsearch, on older Magento versions)
reachable on `localhost:9200`. This repo's `start-magento.ps1` starts a local
OpenSearch automatically if installed at `C:\opensearch\opensearch-2.11.0`.
For latest Magento (2.4.9), OpenSearch 3 is the current target — download the
Windows distribution, unzip to that path (or update the path in
`start-magento.ps1`), and it will be started for you.

## Stage 5 — Magento install

Run the project's installer script rather than doing this by hand — it wraps
all of the above plus known Windows-specific fixes:

```powershell
.\scripts\install-magento-xampp.ps1
```

or `make magento-install`. See [../00-first-time-setup.md](../00-first-time-setup.md)
for what this script does step by step and its troubleshooting section.

## Display versions after install

```powershell
php -v
composer --version
.\vendor\bin\magento --version   # or: php bin\magento --version
mysql --version
```

Compare against the plan from [planner.md](planner.md) Step 0.

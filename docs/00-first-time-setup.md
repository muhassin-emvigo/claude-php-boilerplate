# First-Time Setup (New Developer / New Machine)

Follow this once, the very first time you get this project on a new computer (for example, after `git clone`). If the site is already installed and you just want to start it, see [01-getting-started.md](01-getting-started.md) instead.

## What you need before you start

- **XAMPP** installed, with Apache and MySQL available (this guide assumes XAMPP is at `C:\xampppp`)
- **PHP 8.2+** with these extensions enabled: bcmath, curl, gd, intl, mbstring, pdo_mysql, soap, sockets, sodium, xsl, zip, openssl
- **Composer 2.x** installed
- **A Magento Marketplace account** with an Access Key pair (public + private key) — free to create at the Adobe/Magento Marketplace site
- **OpenSearch or Elasticsearch** reachable on `localhost:9200` (this project was set up using a standalone OpenSearch 2.11 — see step 3 below)

## Step 1: Get the code

```bash
git clone <this-repo-url> rgd_dental
cd rgd_dental
```

Place it directly under your XAMPP `htdocs` folder, e.g. `C:\xampppp\htdocs\rgd_dental`.

## Step 2: Add your Magento Marketplace keys

Copy the example auth file and fill in your own keys:

```bash
cp auth.json.example auth.json
```

Edit `auth.json` and replace the placeholder username/password with your Magento Marketplace **public key** (as username) and **private key** (as password).

## Step 3: Make sure MySQL and a search engine are available

- Start MySQL (via the XAMPP Control Panel, or it will be started for you in step 5).
- Make sure something is listening on `localhost:9200` for search. If you don't already have OpenSearch installed, download OpenSearch 2.11 for Windows, unzip it to `C:\opensearch\opensearch-2.11.0`, and it will be started automatically by `start-magento.ps1` in later steps.

## Step 4: Rename the placeholder module (only needed once)

```bash
make init
```

This renames the example `vendor/CustomShipping` module to your own module name if you're starting a brand-new project from this boilerplate. If you're continuing work on an already-named module (like this one), you can skip this step.

## Step 5: Install everything

From PowerShell, in the project folder:

```powershell
.\install-magento-xampp.ps1
```

or equivalently:

```bash
make magento-install
```

This single script will:
1. Check your PHP version and extensions
2. Set up your Composer authentication
3. Create the `magento` database
4. Download and install the full Magento core (10–30+ minutes the first time)
5. Automatically fix a handful of known Magento-on-Windows bugs (see `scripts/fix-windows-vendor-bugs.php`) — you don't need to do anything for this, it happens on its own
6. Run the Magento installer, compile code, deploy static assets, and set up the database

**This step takes a while.** Grab a coffee. If it fails partway through, it's usually safe to just run it again — the script detects what's already done and picks up from there.

## Step 6: Start it and check it

```powershell
.\start-magento.ps1
```

or:

```bash
make magento-run
```

Your browser should open automatically to `http://localhost/rgd_dental/`. If it doesn't look right, work through [03-testing-the-site.md](03-testing-the-site.md).

## If something goes wrong

- **`bin/magento not found`** — the Composer install step failed. Scroll up in the terminal output for the actual error (often a missing PHP extension or a wrong Marketplace key).
- **A page shows a raw PHP error mentioning "Wrong file", "Invalid template file", or a 404 on every page** — this project includes automatic fixes for these (`scripts/fix-windows-vendor-bugs.php`, run automatically by `install-magento-xampp.ps1`). If you still see this, run:
  ```bash
  make fix-windows
  ```
  and then `make magento-run` again.
- **Search engine not reachable** — make sure OpenSearch is running and listening on port 9200 before running `install-magento-xampp.ps1`.
- **Admin login page keeps reappearing after you submit the correct username/password, with no error message at all** — this has two known independent causes on this project. `install-magento-xampp.ps1` now handles both automatically:
  1. `Magento_ReCaptchaUser` breaks admin login entirely when no Google reCAPTCHA API keys are configured (normal for local dev). Fix:
     ```bash
     php bin/magento module:disable Magento_ReCaptchaUser
     php bin/magento setup:upgrade
     php bin/magento cache:flush
     ```
  2. If `generated/metadata` exists on disk (left over from `setup:di:compile`), Magento silently switches to "Compiled" DI mode regardless of `deploy:mode:set developer` — and in this Magento version, Compiled mode never wraps admin controllers in their Interceptor classes, so **no `AbstractAction`-level plugins run at all**, including the plugin that processes the login form and the one that loads the admin theme. Symptoms: login form just redisplays with no error, and `var/log/debug.log` shows repeated "Broken reference" warnings for `header`/`footer`/`messages` layout containers. Fix:
     ```bash
     rm -rf generated/metadata
     php bin/magento cache:flush
     ```
     (Keep `generated/code` — PHPUnit and Factory/Proxy autoloading still need it. Only `generated/metadata` forces Compiled mode.)
- Still stuck? See the full checklist in [03-testing-the-site.md](03-testing-the-site.md).

Once installed, you won't need this guide again — just use [01-getting-started.md](01-getting-started.md) to start the site each time.

# XAMPP / Magento 2 install for http://localhost/rgd_dental/
#
# Run in PowerShell from project root:
#   .\install-magento-xampp.ps1
#
# Prerequisites:
#   - XAMPP: Apache + MySQL running
#   - PHP 8.2+ extensions enabled (see script output)
#   - Composer installed
#   - Magento Marketplace keys in docker/.env (or auth.json)
#   - OpenSearch/Elasticsearch on localhost:9200 (see notes below)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

function Say($msg) { Write-Host "`n=== $msg ===`n" -ForegroundColor Cyan }

function Write-MagentoHtaccess {
    @'
<IfModule mod_rewrite.c>
    RewriteEngine on
    RewriteBase /rgd_dental/
    RewriteRule ^$ pub/ [L]
    RewriteCond %{REQUEST_URI} !^/rgd_dental/pub/
    RewriteRule ^(.*)$ pub/$1 [L]
</IfModule>
DirectoryIndex index.php
'@ | Set-Content (Join-Path $PSScriptRoot '.htaccess') -Encoding ASCII
}

# --- XAMPP paths (adjust if your XAMPP folder differs) ---
$XamppRoot = 'C:\xampppp'
$PhpIni = Join-Path $XamppRoot 'php\php.ini'
$PhpExe = Join-Path $XamppRoot 'php\php.exe'
$MysqlExe = Join-Path $XamppRoot 'mysql\bin\mysql.exe'

if (Test-Path $PhpExe) {
    $env:PATH = "$(Split-Path $PhpExe);$env:PATH"
}

Say 'Checking PHP'
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "PHP not found. Add $($XamppRoot)\php to PATH or install XAMPP." -ForegroundColor Red
    exit 1
}

$phpVersion = php -r "echo PHP_VERSION;"
Write-Host "PHP $phpVersion"

$requiredExts = @('bcmath', 'curl', 'gd', 'intl', 'mbstring', 'pdo_mysql', 'soap', 'sockets', 'sodium', 'xsl', 'zip', 'openssl')
$missing = @()
foreach ($ext in $requiredExts) {
    $ok = php -m | Select-String -Pattern "^$ext$" -Quiet
    if (-not $ok) { $missing += $ext }
}

if ($missing.Count -gt 0) {
    Write-Host 'Missing PHP extensions:' -ForegroundColor Red
    $missing | ForEach-Object { Write-Host "  - $_" }
    Write-Host "`nEnable them in $PhpIni (remove ; from extension= lines), then restart Apache." -ForegroundColor Yellow
    if ($missing -contains 'bcmath') {
        Write-Host @'

bcmath fix on XAMPP (Windows):
  1. Check this file exists: C:\xampppp\php\ext\php_bcmath.dll
  2. If missing, copy php_bcmath.dll from a fresh XAMPP 8.2 PHP zip into that ext\ folder.
  3. Install "Microsoft Visual C++ Redistributable 2015-2022 (x64)" if the DLL exists but still fails.
  4. In php.ini use: extension=php_bcmath.dll
  5. Restart Apache and run: php -m | findstr bcmath

'@ -ForegroundColor Yellow
    }
    exit 1
}
Write-Host 'PHP extensions OK' -ForegroundColor Green

Say 'Checking Composer'
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    Write-Host 'Composer not found. Install from https://getcomposer.org/download/' -ForegroundColor Red
    exit 1
}

Say 'Magento Composer auth'
if (-not (Test-Path 'auth.json') -and (Test-Path 'docker/.env')) {
    $envContent = Get-Content 'docker/.env' -Raw
    if ($envContent -match 'MAGENTO_PUBLIC_KEY=(.+)') { $pub = $Matches[1].Trim() }
    if ($envContent -match 'MAGENTO_PRIVATE_KEY=(.+)') { $priv = $Matches[1].Trim() }
    if ($pub -and $priv) {
        @"
{
    "http-basic": {
        "repo.magento.com": {
            "username": "$pub",
            "password": "$priv"
        }
    }
}
"@ | Set-Content 'auth.json' -Encoding UTF8
        Write-Host 'Created auth.json from docker/.env' -ForegroundColor Green
    }
}

if (-not (Test-Path 'auth.json')) {
    Write-Host 'Create auth.json with your Magento Marketplace keys, or add keys to docker/.env' -ForegroundColor Red
    exit 1
}

Say 'Creating MySQL database (magento)'
$dbHost = '127.0.0.1'
$dbName = 'magento'
$dbUser = 'root'
$dbPass = ''

if (Test-Path $MysqlExe) {
    & $MysqlExe -h $dbHost -u $dbUser -e "CREATE DATABASE IF NOT EXISTS ``$dbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>$null
    Write-Host "Database '$dbName' ready" -ForegroundColor Green
} else {
    Write-Host "Create database '$dbName' manually in phpMyAdmin (http://localhost/phpmyadmin)" -ForegroundColor Yellow
}

Say 'Installing Magento core via Composer (10-30+ minutes)'
$env:COMPOSER_MEMORY_LIMIT = '-1'

# Magento's own file-mapping deploy (bin/, pub/, var/, generated/, etc.) silently
# aborts on its very first conflict, and this project ships a few files
# (.editorconfig, .htaccess, .php-cs-fixer.dist.php, CHANGELOG.md) that collide
# with files Magento's magento2-base package also wants to place at the project
# root. Move them out of the way for the deploy, then restore our originals.
$conflictFiles = @('.editorconfig', '.htaccess', '.php-cs-fixer.dist.php', 'CHANGELOG.md')
$backupDir = Join-Path $PSScriptRoot '_install_conflict_backup'
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
foreach ($f in $conflictFiles) {
    if (Test-Path $f) {
        Copy-Item $f (Join-Path $backupDir $f) -Force
        Remove-Item $f -Force
    }
}

composer update --no-interaction
$composerExit = $LASTEXITCODE

foreach ($f in $conflictFiles) {
    $backupPath = Join-Path $backupDir $f
    if (Test-Path $backupPath) {
        Copy-Item $backupPath $f -Force
    }
}
Remove-Item $backupDir -Recurse -Force -ErrorAction SilentlyContinue

if ($composerExit -ne 0) { exit $composerExit }

if (-not (Test-Path 'bin/magento')) {
    Write-Host 'bin/magento not found after composer update. Check composer errors above.' -ForegroundColor Red
    exit 1
}

Say 'Applying Windows compatibility fixes to Magento core'
php scripts/fix-windows-vendor-bugs.php
if ($LASTEXITCODE -ne 0) {
    Write-Host 'Some Windows compatibility fixes could not be applied automatically - see above.' -ForegroundColor Yellow
    Write-Host 'The install can continue, but you may hit the issues described in scripts/fix-windows-vendor-bugs.php.' -ForegroundColor Yellow
}

Say 'Checking OpenSearch / Elasticsearch (port 9200)'
$searchOk = $false
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:9200' -TimeoutSec 3 -UseBasicParsing
    $searchOk = ($r.StatusCode -eq 200)
} catch { }

if (-not $searchOk) {
    Write-Host @'

OpenSearch/Elasticsearch is NOT running on localhost:9200.
Magento 2.4 requires a search engine. Pick ONE option:

  A) Minimal Docker (only search, not full stack):
     docker run -d --name magento-search -p 9200:9200 ^
       -e discovery.type=single-node -e plugins.security.disabled=true ^
       opensearchproject/opensearch:2.11.0

  B) Install Elasticsearch 7/8 for Windows separately.

Then re-run: .\install-magento-xampp.ps1

'@ -ForegroundColor Yellow
    exit 1
}
Write-Host 'Search engine reachable on :9200' -ForegroundColor Green

$baseUrl = 'http://localhost/rgd_dental/'
$moduleId = 'vendor_CustomShipping'

if (-not (Test-Path 'app/etc/env.php')) {
    Say 'Running Magento setup:install'
    php bin/magento setup:install `
        --base-url="$baseUrl" `
        --db-host=$dbHost `
        --db-name=$dbName `
        --db-user=$dbUser `
        --db-password=$dbPass `
        --admin-firstname=Admin `
        --admin-lastname=User `
        --admin-email=admin@example.com `
        --admin-user=admin `
        --admin-password='Admin123!' `
        --backend-frontname=admin `
        --language=en_US `
        --currency=USD `
        --timezone=America/Chicago `
        --use-rewrites=1 `
        --search-engine=opensearch `
        --opensearch-host=127.0.0.1 `
        --opensearch-port=9200 `
        --no-interaction

    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
} else {
    Say 'Magento already installed — running setup:upgrade'
    php bin/magento setup:upgrade --no-interaction
}

Say 'Disabling Magento_ReCaptchaUser (no reCAPTCHA API keys configured for local dev)'
# Without Google reCAPTCHA keys, this module's admin-login observer throws
# "Cannot instantiate interface Magento\ReCaptchaUi\Model\ErrorMessageConfigInterface"
# on every login attempt, silently blocking ALL admin access (no error shown to
# the user, no failure recorded in admin_user - the login form just redisplays
# itself). This is unrelated to any custom module; it's a gap in this Magento
# version's ReCaptcha DI wiring when the feature isn't configured.
php bin/magento module:disable Magento_ReCaptchaUser 2>$null

Say 'Enabling module and finishing setup'
php bin/magento module:enable $moduleId 2>$null
php bin/magento setup:upgrade --no-interaction
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f en_US
php bin/magento deploy:mode:set developer
php bin/magento cache:flush
php bin/magento indexer:reindex

Say 'Done'
Write-MagentoHtaccess
Write-Host "Storefront : $baseUrl"
Write-Host "Admin      : ${baseUrl}admin   (user: admin / pass: Admin123!)"
Write-Host ''
Write-Host 'Apache: ensure mod_rewrite is enabled and AllowOverride All for htdocs.' -ForegroundColor Yellow

# Run Composer update with Apache stopped (avoids vendor/ file locks).
# Usage: .\scripts\composer-update.ps1

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path $PSScriptRoot -Parent
Set-Location $ProjectRoot

Get-Process -Name httpd -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue

$env:COMPOSER_MEMORY_LIMIT = '-1'
$composerHome = if ($env:COMPOSER_HOME) { $env:COMPOSER_HOME } else { Join-Path $env:LOCALAPPDATA 'Composer' }
$composerTmp = Join-Path $env:LOCALAPPDATA 'Temp\composer'
$composerCache = Join-Path $composerHome 'cache'

foreach ($dir in @($composerHome, $composerTmp, $composerCache, (Join-Path $composerCache 'vcs'), (Join-Path $composerCache 'repo'), (Join-Path $composerCache 'files'))) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
}

$env:COMPOSER_HOME = $composerHome
$env:COMPOSER_TEMP_DIR = $composerTmp
$env:COMPOSER_CACHE_DIR = $composerCache

if (-not (Test-Path 'vendor')) {
    Write-Host 'vendor/ missing — run .\scripts\fix-vendor-permissions.ps1 first (as Admin).' -ForegroundColor Red
    exit 1
}

Write-Host 'Running composer update (this can take 10-30+ minutes)...' -ForegroundColor Cyan
& composer update --no-interaction
exit $LASTEXITCODE

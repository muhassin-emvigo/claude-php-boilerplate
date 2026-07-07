# Fix Composer "Permission denied" on vendor/ (Windows + XAMPP).
#
# Uses a directory junction: project vendor/ -> writable folder in %LOCALAPPDATA%
#
# Run PowerShell AS ADMINISTRATOR:
#   .\scripts\fix-vendor-permissions.ps1
#
# Then (normal PowerShell, Apache STOPPED in XAMPP):
#   composer update --no-interaction

$ProjectRoot = Split-Path $PSScriptRoot -Parent
$VendorLink = Join-Path $ProjectRoot 'vendor'
$VendorTarget = Join-Path $env:LOCALAPPDATA 'rgd_dental-vendor'

Write-Host "Project:      $ProjectRoot" -ForegroundColor Cyan
Write-Host "Vendor link:  $VendorLink" -ForegroundColor Cyan
Write-Host "Vendor data:  $VendorTarget" -ForegroundColor Cyan

foreach ($proc in @('httpd', 'php')) {
    Get-Process -Name $proc -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
}

# Composer cache (fixes missing cache-vcs-dir)
$composerHome = if ($env:COMPOSER_HOME) { $env:COMPOSER_HOME } else { Join-Path $env:LOCALAPPDATA 'Composer' }
foreach ($dir in @(
        $composerHome,
        (Join-Path $composerHome 'cache'),
        (Join-Path $composerHome 'cache\vcs'),
        (Join-Path $composerHome 'cache\repo'),
        (Join-Path $composerHome 'cache\files')
    )) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
}

# Writable vendor target
if (Test-Path $VendorTarget) {
    Write-Host 'Cleaning old vendor target temp files...' -ForegroundColor Yellow
    $composerTmp = Join-Path $VendorTarget 'composer'
    if (Test-Path $composerTmp) {
        Get-ChildItem $composerTmp -Filter 'tmp-*' -ErrorAction SilentlyContinue |
            Remove-Item -Force -Recurse -ErrorAction SilentlyContinue
    }
} else {
    New-Item -ItemType Directory -Path $VendorTarget -Force | Out-Null
}

# Replace project vendor/ with junction to writable path
if (Test-Path $VendorLink) {
    $item = Get-Item $VendorLink -Force
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) {
        Write-Host 'Removing existing vendor junction...' -ForegroundColor Yellow
        cmd /c "rmdir `"$VendorLink`"" 2>$null | Out-Null
    } else {
        Write-Host 'Removing existing vendor folder (moving aside)...' -ForegroundColor Yellow
        $backup = "$VendorLink.bak.$(Get-Date -Format 'yyyyMMddHHmmss')"
        Rename-Item -Path $VendorLink -NewName (Split-Path $backup -Leaf) -ErrorAction SilentlyContinue
        if (Test-Path $VendorLink) {
            Remove-Item -Path $VendorLink -Recurse -Force -ErrorAction SilentlyContinue
        }
    }
}

if (-not (Test-Path $VendorLink)) {
    Write-Host 'Creating vendor junction...' -ForegroundColor Yellow
    cmd /c "mklink /J `"$VendorLink`" `"$VendorTarget`""
    if (-not (Test-Path $VendorLink)) {
        Write-Host 'Failed to create junction. Run this script as Administrator.' -ForegroundColor Red
        exit 1
    }
}

$user = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
cmd /c "icacls `"$VendorTarget`" /grant `"${user}:(OI)(CI)F`" /T" | Out-Null
cmd /c "icacls `"$ProjectRoot`" /grant `"${user}:(OI)(CI)F`" /T" | Out-Null

if (Get-Command composer -ErrorAction SilentlyContinue) {
    Write-Host 'Clearing Composer cache...' -ForegroundColor Yellow
    $prev = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    & composer clear-cache 2>&1 | Out-Null
    $ErrorActionPreference = $prev
}

Write-Host ''
Write-Host 'Setup complete.' -ForegroundColor Green
Write-Host ''
Write-Host 'Next steps:' -ForegroundColor Green
Write-Host '  1. Keep Apache STOPPED in XAMPP while Composer runs'
Write-Host "  2. cd $ProjectRoot"
Write-Host '  3. composer update --no-interaction'
Write-Host ''
Write-Host 'If Windows Defender still blocks downloads, add exclusions for:' -ForegroundColor Yellow
Write-Host "  $ProjectRoot"
Write-Host "  $VendorTarget"
Write-Host "  $composerHome"

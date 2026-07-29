# start-magento.ps1
#
# Starts everything the Magento website needs to run:
#   - the database (MySQL)
#   - the search engine (OpenSearch)
#   - the web server (Apache)
# then opens the website in your browser.
#
# Run this every time you restart your computer and want to use the site again.
#
# How to run it:
#   1. Open PowerShell in the project root.
#   2. Type: .\scripts\start-magento.ps1
#      (or: make magento-run)

$ErrorActionPreference = 'Stop'

# This script lives in <project root>/scripts/ — resolve and cd to the
# project root so it works regardless of where it was invoked from.
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

function Say($msg) { Write-Host "`n== $msg ==" -ForegroundColor Cyan }

# Project folder name doubles as the Apache alias / base-URL path segment —
# see scripts/install-magento-xampp.ps1 for the matching assumption.
$ProjectName = Split-Path -Leaf $ProjectRoot
$siteUrl = "http://localhost/$ProjectName/"

# --- 1. Start MySQL (the database) ---
Say 'Starting the database (MySQL)'
$mysqlRunning = Get-Process mysqld -ErrorAction SilentlyContinue
if ($mysqlRunning) {
    Write-Host 'Already running.' -ForegroundColor Green
} else {
    Start-Process -FilePath 'C:\xampppp\mysql\bin\mysqld.exe' `
        -ArgumentList '--standalone', '--console' `
        -WorkingDirectory 'C:\xampppp\mysql\bin' `
        -WindowStyle Hidden
    Write-Host 'Waiting for it to be ready...'
    $ready = $false
    for ($i = 0; $i -lt 30; $i++) {
        Start-Sleep -Seconds 1
        $test = Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue
        if ($test.TcpTestSucceeded) { $ready = $true; break }
    }
    if ($ready) { Write-Host 'Database is ready.' -ForegroundColor Green }
    else { Write-Host 'Database did not start in time - check for error messages above.' -ForegroundColor Red }
}

# --- 2. Start OpenSearch (the search engine) ---
Say 'Starting the search engine (OpenSearch)'
$osRunning = Test-NetConnection -ComputerName 127.0.0.1 -Port 9200 -WarningAction SilentlyContinue
if ($osRunning.TcpTestSucceeded) {
    Write-Host 'Already running.' -ForegroundColor Green
} else {
    Start-Process -FilePath 'C:\opensearch\opensearch-2.11.0\bin\opensearch.bat' `
        -WorkingDirectory 'C:\opensearch\opensearch-2.11.0\bin' `
        -WindowStyle Hidden
    Write-Host 'Waiting for it to be ready (this can take up to a minute)...'
    $ready = $false
    for ($i = 0; $i -lt 60; $i++) {
        Start-Sleep -Seconds 1
        $test = Test-NetConnection -ComputerName 127.0.0.1 -Port 9200 -WarningAction SilentlyContinue
        if ($test.TcpTestSucceeded) { $ready = $true; break }
    }
    if ($ready) { Write-Host 'Search engine is ready.' -ForegroundColor Green }
    else { Write-Host 'Search engine did not start in time - check for error messages above.' -ForegroundColor Red }
}

# --- 3. Start Apache (the web server) ---
Say 'Starting the web server (Apache)'
$apacheRunning = Get-Process httpd -ErrorAction SilentlyContinue
if ($apacheRunning) {
    Write-Host 'Already running.' -ForegroundColor Green
} else {
    Start-Process -FilePath 'C:\xampppp\apache\bin\httpd.exe' `
        -WorkingDirectory 'C:\xampppp\apache\bin' `
        -WindowStyle Hidden
    Start-Sleep -Seconds 2
    if (Get-Process httpd -ErrorAction SilentlyContinue) {
        Write-Host 'Web server is ready.' -ForegroundColor Green
    } else {
        Write-Host 'Web server did not start - check for error messages above.' -ForegroundColor Red
    }
}

# --- 4. Open the website ---
Say 'Opening the website'
Start-Process $siteUrl
Write-Host "`nDone! The website should now open in your browser." -ForegroundColor Green
Write-Host "Website:      $siteUrl"
Write-Host "Admin panel:  ${siteUrl}admin  (user: admin / password: Admin123!)"

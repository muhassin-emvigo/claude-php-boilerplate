# PowerShell wrapper for Makefile targets (Windows-friendly alternative to `make`).
# Usage: .\make.ps1 [target]
# Example: .\make.ps1 test

param(
    [Parameter(Position = 0)]
    [ValidateSet('help', 'init', 'install', 'test', 'test-coverage', 'lint', 'lint-fix', 'phpstan', 'phpmd', 'check', 'check-setup', 'clean')]
    [string]$Target = 'help'
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

function Invoke-BashScript {
    param([string]$Script)
    $bash = Get-Command bash -ErrorAction SilentlyContinue
    if (-not $bash) {
        Write-Error "bash not found. Install Git for Windows: https://git-scm.com/download/win"
    }
    & bash $Script
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

function Invoke-VendorBin {
    param(
        [string]$Binary,
        [Parameter(ValueFromRemainingArguments = $true)][string[]]$Args
    )

    if (-not (Test-Path "vendor/bin/$Binary") -and -not (Test-Path "vendor/bin/$Binary.bat")) {
        Write-Host "vendor/bin/$Binary not found. Run: .\make.ps1 install" -ForegroundColor Red
        exit 1
    }

    $binaryPath = "vendor/bin/$Binary"
    & php $binaryPath @Args
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

function Show-Help {
    @(
        'Magento 2 Module Boilerplate — PowerShell commands',
        '',
        '  .\make.ps1 init           First-time setup (rename Vendor/ModuleName)',
        '  .\make.ps1 install        Install Composer dependencies',
        '  .\make.ps1 test           Run PHPUnit tests',
        '  .\make.ps1 test-coverage  Run tests with HTML coverage',
        '  .\make.ps1 lint           Run PHPCS',
        '  .\make.ps1 lint-fix       Auto-fix code style',
        '  .\make.ps1 phpstan        Run PHPStan',
        '  .\make.ps1 phpmd          Run PHPMD',
        '  .\make.ps1 check          Run all quality checks',
        '  .\make.ps1 check-setup    Verify development environment',
        '  .\make.ps1 clean          Remove generated cache files',
        '',
        'Windows note: if `make test` fails, use `.\make.ps1 test` instead.'
    ) | ForEach-Object { Write-Host $_ }
}

switch ($Target) {
    'help' { Show-Help }
    'init' { Invoke-BashScript 'scripts/init.sh' }
    'check-setup' { Invoke-BashScript 'scripts/check-setup.sh' }
    'install' {
        Write-Host 'Installing dependencies...' -ForegroundColor Green
        & composer install
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
        Write-Host 'Dependencies installed.' -ForegroundColor Green
    }
    'test' {
        Write-Host 'Running tests...' -ForegroundColor Green
        Invoke-VendorBin 'phpunit' '-c', 'phpunit.xml.dist', '--colors=always'
    }
    'test-coverage' {
        Write-Host 'Running tests with coverage...' -ForegroundColor Green
        Invoke-VendorBin 'phpunit' '-c', 'phpunit.xml.dist', '--coverage-html', 'coverage/', '--colors=always'
        Write-Host 'Coverage report: coverage/index.html' -ForegroundColor Green
    }
    'lint' {
        Write-Host 'Running PHPCS...' -ForegroundColor Green
        Invoke-VendorBin 'phpcs' '--standard=phpcs.xml.dist'
    }
    'lint-fix' {
        Write-Host 'Fixing code style...' -ForegroundColor Yellow
        Invoke-VendorBin 'php-cs-fixer' 'fix', '--config=.php-cs-fixer.dist.php'
        Write-Host 'Code style fixed.' -ForegroundColor Green
    }
    'phpstan' {
        Write-Host 'Running PHPStan...' -ForegroundColor Green
        Invoke-VendorBin 'phpstan' 'analyse', '-c', 'phpstan.neon.dist', '--no-progress'
    }
    'phpmd' {
        Write-Host 'Running PHPMD...' -ForegroundColor Green
        Invoke-VendorBin 'phpmd' 'app/code/', 'text', 'phpmd.xml.dist'
    }
    'check' {
        & $PSCommandPath lint
        & $PSCommandPath phpstan
        & $PSCommandPath phpmd
        & $PSCommandPath test
        Write-Host 'All checks passed.' -ForegroundColor Green
    }
    'clean' {
        Write-Host 'Cleaning...' -ForegroundColor Yellow
        Remove-Item -Recurse -Force coverage, .phpcs-cache, .php-cs-fixer.cache -ErrorAction SilentlyContinue
        Write-Host 'Cleaned.' -ForegroundColor Green
    }
}

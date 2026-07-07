#!/usr/bin/env bash
set -euo pipefail

# ============================================================
# Environment Verification Script
# Checks that all required tools are installed and configured
# ============================================================

GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m'

PASSED=0
FAILED=0
WARNED=0

check_pass() {
    echo -e "  ${GREEN}✅${NC} $1"
    ((PASSED++))
}

check_fail() {
    echo -e "  ${RED}❌${NC} $1"
    ((FAILED++))
}

check_warn() {
    echo -e "  ${YELLOW}⚠️${NC}  $1"
    ((WARNED++))
}

echo -e "${BOLD}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║  Environment Check                           ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════╝${NC}"
echo

# PHP
echo -e "${BOLD}PHP:${NC}"
if command -v php &>/dev/null; then
    PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
    PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
    PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')
    if (( PHP_MAJOR >= 8 && PHP_MINOR >= 2 )); then
        check_pass "PHP $PHP_VERSION installed"
    else
        check_fail "PHP 8.2+ required (found $PHP_VERSION)"
    fi
else
    check_fail "PHP not found"
fi

# PHP Extensions
echo -e "\n${BOLD}PHP Extensions:${NC}"
for ext in json xml mbstring intl curl gd soap zip; do
    if php -m 2>/dev/null | grep -qi "^$ext$"; then
        check_pass "ext-$ext"
    else
        check_fail "ext-$ext (missing)"
    fi
done

# Composer
echo -e "\n${BOLD}Composer:${NC}"
if command -v composer &>/dev/null; then
    COMPOSER_VERSION=$(composer --version 2>/dev/null | grep -oP '\d+\.\d+\.\d+' | head -1)
    check_pass "Composer $COMPOSER_VERSION installed"
else
    check_fail "Composer not found — install from https://getcomposer.org"
fi

# Git
echo -e "\n${BOLD}Git:${NC}"
if command -v git &>/dev/null; then
    GIT_VERSION=$(git --version | grep -oP '\d+\.\d+\.\d+')
    check_pass "Git $GIT_VERSION installed"
else
    check_fail "Git not found"
fi

# Claude CLI (optional)
echo -e "\n${BOLD}Claude CLI (optional):${NC}"
if command -v claude &>/dev/null; then
    check_pass "Claude CLI installed"
else
    check_warn "Claude CLI not found (optional — install for AI-assisted development)"
fi

# vendor dependencies
echo -e "\n${BOLD}Dependencies:${NC}"
if [ -d "vendor" ]; then
    check_pass "vendor/ directory exists"

    # Check key tools
    for tool in phpunit phpcs phpstan phpmd php-cs-fixer; do
        if [ -f "vendor/bin/$tool" ]; then
            check_pass "$tool available"
        else
            check_fail "$tool not found in vendor/bin/ — run 'composer install'"
        fi
    done
else
    check_fail "vendor/ not found — run 'composer install' first"
fi

# Summary
echo
echo -e "${BOLD}══════════════════════════════════════════════${NC}"
echo -e "  ${GREEN}Passed: $PASSED${NC}  ${RED}Failed: $FAILED${NC}  ${YELLOW}Warnings: $WARNED${NC}"
echo -e "${BOLD}══════════════════════════════════════════════${NC}"

if [ $FAILED -gt 0 ]; then
    echo -e "\n${RED}Some checks failed. Please fix the issues above before proceeding.${NC}"
    exit 1
else
    echo -e "\n${GREEN}Environment is ready! 🚀${NC}"
    exit 0
fi

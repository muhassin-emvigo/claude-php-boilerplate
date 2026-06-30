#!/usr/bin/env bash
# ============================================================
# Claude Code Statusline Command
# Shows project metrics in the Claude Code terminal footer
# Reads JSON from stdin (session state) and outputs formatted text
# ============================================================

set -euo pipefail

# Get project root
PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$PROJECT_ROOT"

# Git info
BRANCH=$(git branch --show-current 2>/dev/null || echo "no-git")
DIRTY=$(git status --porcelain 2>/dev/null | wc -l | tr -d ' ')

# Module count
MODULES=$(find app/code -name 'module.xml' 2>/dev/null | wc -l | tr -d ' ')

# PHP version
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null || echo "?")

# Last test result (cached)
TEST_CACHE="$PROJECT_ROOT/.phpunit.result.cache"
if [ -f "$TEST_CACHE" ]; then
    TEST_STATUS="✅"
else
    TEST_STATUS="?"
fi

# Lint status (check if cache exists and is recent)
LINT_CACHE="$PROJECT_ROOT/.phpcs-cache"
if [ -f "$LINT_CACHE" ]; then
    LINT_STATUS="✅"
else
    LINT_STATUS="?"
fi

# Build the statusline
echo "🐘 PHP $PHP_VER | 🔀 $BRANCH${DIRTY:+ (+$DIRTY)} | 📦 ${MODULES} module(s) | 🧪 $TEST_STATUS | 📏 $LINT_STATUS"

#!/usr/bin/env bash
set -euo pipefail

# ============================================================
# Magento 2 Module Boilerplate — First-Time Initialization
# Replaces Vendor/ModuleName placeholders with your actual names
# ============================================================

GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m'

echo -e "${BOLD}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║  Magento 2 Module Boilerplate — Setup        ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════╝${NC}"
echo

# Get project root (parent of scripts/)
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

# Check if already initialized
if [ ! -d "app/code/Vendor/ModuleName" ]; then
    echo -e "${YELLOW}⚠ Module directory app/code/Vendor/ModuleName not found.${NC}"
    echo -e "${YELLOW}  It looks like init has already been run, or the template is missing.${NC}"
    exit 1
fi

# Prompt for vendor name
read -rp "Enter your Vendor name (e.g., Acme, Emvigo): " VENDOR_NAME
if [[ -z "$VENDOR_NAME" ]]; then
    echo -e "${RED}❌ Vendor name cannot be empty.${NC}"
    exit 1
fi

# Prompt for module name
read -rp "Enter your Module name (e.g., CustomShipping, PaymentGateway): " MODULE_NAME
if [[ -z "$MODULE_NAME" ]]; then
    echo -e "${RED}❌ Module name cannot be empty.${NC}"
    exit 1
fi

# Derived values
VENDOR_LOWER=$(echo "$VENDOR_NAME" | tr '[:upper:]' '[:lower:]')
MODULE_LOWER=$(echo "$MODULE_NAME" | tr '[:upper:]' '[:lower:]')
MODULE_HYPHEN=$(echo "$MODULE_NAME" | sed 's/\([A-Z]\)/-\L\1/g' | sed 's/^-//')

echo
echo -e "${BOLD}Summary:${NC}"
echo -e "  Vendor:         ${GREEN}$VENDOR_NAME${NC}"
echo -e "  Module:         ${GREEN}$MODULE_NAME${NC}"
echo -e "  Full name:      ${GREEN}${VENDOR_NAME}_${MODULE_NAME}${NC}"
echo -e "  Composer name:  ${GREEN}${VENDOR_LOWER}/module-${MODULE_HYPHEN}${NC}"
echo -e "  Namespace:      ${GREEN}${VENDOR_NAME}\\${MODULE_NAME}${NC}"
echo
read -rp "Proceed? (y/N): " CONFIRM
if [[ ! "$CONFIRM" =~ ^[yY]$ ]]; then
    echo -e "${YELLOW}Aborted.${NC}"
    exit 0
fi

echo
echo -e "${GREEN}Renaming files and directories...${NC}"

# 1. Replace in file contents (excluding .git, vendor, node_modules)
find . -type f \
    -not -path './.git/*' \
    -not -path './vendor/*' \
    -not -path './node_modules/*' \
    -not -path './scripts/init.sh' \
    -not -name '*.png' -not -name '*.jpg' -not -name '*.gif' \
    -print0 | while IFS= read -r -d '' file; do
    if file "$file" | grep -q 'text'; then
        sed -i \
            -e "s/Vendor_ModuleName/${VENDOR_NAME}_${MODULE_NAME}/g" \
            -e "s/Vendor\\\\ModuleName/${VENDOR_NAME}\\\\${MODULE_NAME}/g" \
            -e "s/Vendor\\\\\\\\ModuleName/${VENDOR_NAME}\\\\\\\\${MODULE_NAME}/g" \
            -e "s|Vendor/ModuleName|${VENDOR_NAME}/${MODULE_NAME}|g" \
            -e "s/vendor\/module-name/${VENDOR_LOWER}\/module-${MODULE_HYPHEN}/g" \
            -e "s/ModuleName/${MODULE_NAME}/g" \
            -e "s/modulename/${MODULE_LOWER}/g" \
            -e "s/Vendor/${VENDOR_NAME}/g" \
            -e "s/vendor/${VENDOR_LOWER}/g" \
            "$file" 2>/dev/null || true
    fi
done

# 2. Rename directories (deepest first to avoid path issues)
mkdir -p "app/code/${VENDOR_NAME}/${MODULE_NAME}"
if [ -d "app/code/Vendor/ModuleName" ]; then
    cp -r app/code/Vendor/ModuleName/* "app/code/${VENDOR_NAME}/${MODULE_NAME}/" 2>/dev/null || true
    cp -r app/code/Vendor/ModuleName/.* "app/code/${VENDOR_NAME}/${MODULE_NAME}/" 2>/dev/null || true
    rm -rf app/code/Vendor
fi

echo -e "${GREEN}✅ Files and directories renamed.${NC}"

# 3. Install dependencies
echo
echo -e "${GREEN}Installing Composer dependencies...${NC}"
composer install --no-interaction 2>&1 || {
    echo -e "${YELLOW}⚠ Composer install had issues. You may need to run it manually.${NC}"
}

# 4. Initialize git if needed
if [ ! -d ".git" ]; then
    echo
    echo -e "${GREEN}Initializing git repository...${NC}"
    git init
    git add -A
    git commit -m "feat: initial module scaffold for ${VENDOR_NAME}_${MODULE_NAME}"
fi

echo
echo -e "${BOLD}${GREEN}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║  ✅ Setup complete!                          ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════════╝${NC}"
echo
echo -e "Next steps:"
echo -e "  ${BOLD}make check-setup${NC}  — Verify your environment"
echo -e "  ${BOLD}make test${NC}         — Run the sample test"
echo -e "  ${BOLD}make check${NC}        — Run all quality checks"
echo -e "  Start building your module in ${GREEN}app/code/${VENDOR_NAME}/${MODULE_NAME}/${NC}"

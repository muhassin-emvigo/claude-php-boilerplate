.PHONY: help init install magento-install magento-run fix-windows test test-coverage lint lint-fix phpstan phpmd check check-setup clean

# GNU Make on Windows defaults to cmd.exe, which cannot run vendor/bin/* directly.
# Use Git Bash when available (install Git for Windows: https://git-scm.com/download/win).
ifeq ($(OS),Windows_NT)
    ifndef GIT_BASH
        GIT_BASH := C:/Program Files/Git/bin/bash.exe
    endif
    ifneq ("$(wildcard $(GIT_BASH))","")
        SHELL := "$(GIT_BASH)"
        .SHELLFLAGS := -ec
    endif
endif

# Default target
.DEFAULT_GOAL := help

# Colors
GREEN  := \033[0;32m
YELLOW := \033[0;33m
RED    := \033[0;31m
NC     := \033[0m # No Color

PHP := php
VENDOR_BIN := $(PHP) vendor/bin

## —— Magento 2 Module Boilerplate ——————————————————————————————
help: ## Show this help message
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-20s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Setup ————————————————————————————————————————————————————
init: ## First-time setup — rename Vendor/ModuleName to your names
	@bash scripts/init.sh

install: ## Install Composer dependencies
	@echo "$(GREEN)Installing dependencies...$(NC)"
	composer install
	@echo "$(GREEN)✅ Dependencies installed$(NC)"

## —— Magento ——————————————————————————————————————————————————
magento-install: ## Install/upgrade full Magento core via XAMPP (runs install-magento-xampp.ps1)
	@powershell -NoProfile -ExecutionPolicy Bypass -File install-magento-xampp.ps1

magento-run: ## Start MySQL/OpenSearch/Apache and open the storefront (runs start-magento.ps1)
	@powershell -NoProfile -ExecutionPolicy Bypass -File start-magento.ps1

fix-windows: ## Re-apply Windows compatibility fixes to vendor/ (safe to re-run any time)
	@php scripts/fix-windows-vendor-bugs.php

## —— Quality ——————————————————————————————————————————————————
test: ## Run PHPUnit tests
	@echo "$(GREEN)Running tests...$(NC)"
	$(VENDOR_BIN)/phpunit -c phpunit.xml.dist --colors=always

test-coverage: ## Run tests with HTML coverage report
	@echo "$(GREEN)Running tests with coverage...$(NC)"
	$(VENDOR_BIN)/phpunit -c phpunit.xml.dist --coverage-html coverage/ --colors=always
	@echo "$(GREEN)✅ Coverage report: coverage/index.html$(NC)"

lint: ## Run PHPCS (Magento2 coding standard)
	@echo "$(GREEN)Running PHPCS...$(NC)"
	$(VENDOR_BIN)/phpcs --standard=phpcs.xml.dist

lint-fix: ## Auto-fix code style with PHP-CS-Fixer
	@echo "$(YELLOW)Fixing code style...$(NC)"
	$(VENDOR_BIN)/php-cs-fixer fix --config=.php-cs-fixer.dist.php
	@echo "$(GREEN)✅ Code style fixed$(NC)"

phpstan: ## Run PHPStan static analysis
	@echo "$(GREEN)Running PHPStan...$(NC)"
	$(VENDOR_BIN)/phpstan analyse -c phpstan.neon.dist --no-progress

phpmd: ## Run PHPMD mess detection
	@echo "$(GREEN)Running PHPMD...$(NC)"
	$(VENDOR_BIN)/phpmd app/code/ text phpmd.xml.dist

check: lint phpstan phpmd test ## Run ALL quality checks (lint + phpstan + phpmd + test)
	@echo "$(GREEN)✅ All checks passed!$(NC)"

## —— Utilities ————————————————————————————————————————————————
check-setup: ## Verify development environment
	@bash scripts/check-setup.sh

clean: ## Clean generated files and caches
	@echo "$(YELLOW)Cleaning...$(NC)"
	rm -rf coverage/ .phpcs-cache .php-cs-fixer.cache
	@echo "$(GREEN)✅ Cleaned$(NC)"

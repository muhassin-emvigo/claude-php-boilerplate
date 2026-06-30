.PHONY: help init install test test-coverage lint lint-fix phpstan phpmd check check-setup clean

# Default target
.DEFAULT_GOAL := help

# Colors
GREEN  := \033[0;32m
YELLOW := \033[0;33m
RED    := \033[0;31m
NC     := \033[0m # No Color

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

## —— Quality ——————————————————————————————————————————————————
test: ## Run PHPUnit tests
	@echo "$(GREEN)Running tests...$(NC)"
	vendor/bin/phpunit -c phpunit.xml.dist --colors=always

test-coverage: ## Run tests with HTML coverage report
	@echo "$(GREEN)Running tests with coverage...$(NC)"
	vendor/bin/phpunit -c phpunit.xml.dist --coverage-html coverage/ --colors=always
	@echo "$(GREEN)✅ Coverage report: coverage/index.html$(NC)"

lint: ## Run PHPCS (Magento2 coding standard)
	@echo "$(GREEN)Running PHPCS...$(NC)"
	vendor/bin/phpcs --standard=phpcs.xml.dist

lint-fix: ## Auto-fix code style with PHP-CS-Fixer
	@echo "$(YELLOW)Fixing code style...$(NC)"
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php
	@echo "$(GREEN)✅ Code style fixed$(NC)"

phpstan: ## Run PHPStan static analysis
	@echo "$(GREEN)Running PHPStan...$(NC)"
	vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress

phpmd: ## Run PHPMD mess detection
	@echo "$(GREEN)Running PHPMD...$(NC)"
	vendor/bin/phpmd app/code/ text phpmd.xml.dist

check: lint phpstan phpmd test ## Run ALL quality checks (lint + phpstan + phpmd + test)
	@echo "$(GREEN)✅ All checks passed!$(NC)"

## —— Utilities ————————————————————————————————————————————————
check-setup: ## Verify development environment
	@bash scripts/check-setup.sh

clean: ## Clean generated files and caches
	@echo "$(YELLOW)Cleaning...$(NC)"
	rm -rf coverage/ .phpcs-cache .php-cs-fixer.cache
	@echo "$(GREEN)✅ Cleaned$(NC)"

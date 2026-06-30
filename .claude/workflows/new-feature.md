---
name: new-feature
description: >
  TDD-based feature development workflow for Magento 2 modules.
  Follows: Spec → Design → Tests First → Implement → Review → Ship.
---

# New Feature Workflow

## Step 1: Specification
1. Define requirements and acceptance criteria
2. Identify affected areas: frontend, admin, API, database, cron
3. List module dependencies (for `module.xml` sequence)
4. Determine if new database tables are needed (`db_schema.xml`)

## Step 2: Architecture Design
1. Design service contracts (Api interfaces):
   - `Api/Data/EntityInterface.php` — data model interface
   - `Api/EntityRepositoryInterface.php` — repository interface
2. Plan XML configurations:
   - `etc/di.xml` — preferences, plugins, virtual types
   - `etc/webapi.xml` — REST endpoints (if API)
   - `etc/events.xml` — observer registrations (if events)
   - `etc/db_schema.xml` — table definitions (if DB)
3. Decide: Plugin vs Observer vs Controller for the feature
4. Plan admin UI if needed: `ui_component/`, `menu.xml`, `system.xml`

## Step 3: Scaffold Files
1. Create the file structure based on the design:
   ```bash
   # Use the magento-module skill or create manually
   mkdir -p app/code/Vendor/Module/{Api/Data,Model/ResourceModel,Controller,etc}
   ```
2. Create `registration.php` and `etc/module.xml` if new module
3. Create empty interface and class files with proper namespaces

## Step 4: Write Tests First (TDD)
1. Create test files mirroring the module structure:
   ```
   Test/Unit/Model/EntityTest.php
   Test/Unit/Model/EntityRepositoryTest.php
   ```
2. Write tests for every public method:
   - Happy path
   - Edge cases
   - Error scenarios
3. Run tests — they should all **fail** at this point:
   ```bash
   vendor/bin/phpunit --filter=EntityTest
   ```

## Step 5: Implement
1. Implement interfaces and classes to make tests pass
2. Follow Magento conventions:
   - Constructor DI only
   - `declare(strict_types=1)` everywhere
   - Full type declarations
   - Proper Magento exception classes
3. Create XML configurations (`di.xml`, `db_schema.xml`, etc.)
4. Run tests incrementally as you implement:
   ```bash
   vendor/bin/phpunit --filter=EntityTest
   ```

## Step 6: Quality Check
1. Run all quality tools:
   ```bash
   make check
   ```
2. Fix any PHPCS, PHPStan, or PHPMD violations
3. Verify test coverage:
   ```bash
   make test-coverage
   ```

## Step 7: Document & Ship
1. Update module documentation (README, API docs)
2. Update CHANGELOG.md under `### Added`
3. Generate `db_schema_whitelist.json` if schema changed:
   ```bash
   bin/magento setup:db-declaration:generate-whitelist --module-name=Vendor_Module
   ```
4. Commit with conventional format:
   ```
   feat(module): add [feature description]

   - [List key changes]
   - [Note any new dependencies]
   ```

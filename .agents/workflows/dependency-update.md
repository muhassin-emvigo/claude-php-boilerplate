---
name: dependency-update
description: >
  Safe workflow for upgrading Composer dependencies in Magento 2 modules.
  Follows: Audit → Update → Verify → Commit.
---

# Dependency Update Workflow

## Step 1: Audit
1. Check current dependency versions:
   ```bash
   composer show --direct
   ```
2. Check for outdated packages:
   ```bash
   composer outdated --direct
   ```
3. Read release notes / changelogs for breaking changes
4. Check Magento version compatibility if updating framework packages
5. Review `composer.json` constraints (^ vs ~ vs exact)

## Step 2: Update
1. Update the target package:
   ```bash
   # Single package
   composer update vendor/package --with-dependencies

   # All dev dependencies
   composer update --dev
   ```
2. If updating Magento framework packages, also run:
   ```bash
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   ```
3. Review `composer.lock` changes:
   ```bash
   git diff composer.lock | head -100
   ```

## Step 3: Verify
1. Run PHP syntax check:
   ```bash
   find app/code -name '*.php' -exec php -l {} \; 2>&1 | grep -i error
   ```
2. Run static analysis (catches type incompatibilities):
   ```bash
   make phpstan
   ```
3. Run the full test suite:
   ```bash
   make test
   ```
4. Run coding standards (new versions may add rules):
   ```bash
   make lint
   ```

## Step 4: Commit
1. Commit with conventional format:
   ```
   chore(deps): update [package] from [old] to [new]

   - [Summary of notable changes]
   - No breaking changes / Breaking: [describe]
   ```
2. Keep `composer.json` and `composer.lock` in the same commit
3. If multiple packages updated, group related ones in a single commit

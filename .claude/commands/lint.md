---
description: Run all code quality tools (PHPCS, PHPStan, PHPMD, PHP-CS-Fixer)
allowed-tools:
  - Bash
  - Read
---

# Lint All Code

Run all quality tools and aggregate results:

1. **PHPCS** (Magento2 coding standard):
   ```bash
   vendor/bin/phpcs --standard=Magento2 app/code/ 2>&1
   ```

2. **PHPStan** (static analysis):
   ```bash
   vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress 2>&1
   ```

3. **PHPMD** (mess detection):
   ```bash
   vendor/bin/phpmd app/code/ text phpmd.xml.dist 2>&1
   ```

4. **PHP-CS-Fixer** (dry run — show what would be fixed):
   ```bash
   vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php 2>&1
   ```

5. Present aggregated results:
   - PHPCS: X violations (Y fixable)
   - PHPStan: X errors
   - PHPMD: X warnings
   - PHP-CS-Fixer: X files would change

6. Ask: "Would you like me to auto-fix the fixable issues?"
   - If yes: run `vendor/bin/php-cs-fixer fix` and `vendor/bin/phpcbf`

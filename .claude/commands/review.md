---
description: Run comprehensive code review on changed files with all quality tools
allowed-tools:
  - Read
  - Bash
  - Grep
---

# Code Review

1. Get changed files:
   ```bash
   git diff --name-only --diff-filter=d HEAD 2>/dev/null || find app/code -name '*.php' -newer composer.json
   ```

2. Run quality tools on changed PHP files:
   ```bash
   # PHPCS - Coding standards
   vendor/bin/phpcs --standard=Magento2 --report=full [files]

   # PHPStan - Static analysis
   vendor/bin/phpstan analyse --no-progress [files]

   # PHPMD - Mess detection
   vendor/bin/phpmd [files] text phpmd.xml.dist
   ```

3. For each file, also manually review:
   - declare(strict_types=1) present
   - No ObjectManager direct usage
   - Proper type hints on all methods
   - Security: escaping in templates, ACL in controllers
   - Tests exist for new/modified public methods

4. Output a structured review report:
   - 🔴 Critical issues (must fix)
   - 🟡 Warnings (should fix)
   - 🔵 Suggestions (nice to have)
   - ✅ What looks good
   - 📊 Summary stats (files reviewed, issues by severity)

---
description: Quick project health dashboard — branch, tests, lint, modules
allowed-tools:
  - Bash
  - Read
  - Grep
---

# Project Status

Gather and display project health:

1. **Git Info**:
   ```bash
   echo "Branch: $(git branch --show-current 2>/dev/null || echo 'not a git repo')"
   echo "Last commits:"
   git log --oneline -5 2>/dev/null || echo 'No git history'
   echo "Uncommitted changes:"
   git status --short 2>/dev/null || echo 'N/A'
   ```

2. **Modules**:
   ```bash
   find app/code -name 'module.xml' -exec grep -l 'module name' {} \; 2>/dev/null
   ```

3. **Quick Test Check**:
   ```bash
   vendor/bin/phpunit -c phpunit.xml.dist --no-coverage 2>&1 | tail -3
   ```

4. **Lint Summary**:
   ```bash
   vendor/bin/phpcs --standard=Magento2 --report=summary app/code/ 2>&1 | tail -5
   ```

5. Present as a dashboard:
   ```
   ╔══════════════════════════════════════╗
   ║     Magento 2 Module Dashboard      ║
   ╠══════════════════════════════════════╣
   ║ Branch:    feature/my-feature       ║
   ║ Modules:   2 registered             ║
   ║ Tests:     ✅ 15/15 passing         ║
   ║ Lint:      ⚠️  3 warnings           ║
   ║ Coverage:  82%                      ║
   ╚══════════════════════════════════════╝
   ```

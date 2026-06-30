---
description: Pre-push validation pipeline — lint, test, security check, and prepare for shipping
allowed-tools:
  - Bash
  - Read
  - Grep
  - Edit
---

# Ship Pipeline

Full pre-push quality gate:

1. **Lint Check**:
   ```bash
   make lint && make phpstan && make phpmd
   ```
   ❌ If any check fails, stop and report issues.

2. **Test Suite**:
   ```bash
   make test
   ```
   ❌ If tests fail, stop and report failures.

3. **Security Scan**:
   ```bash
   # Check for common vulnerabilities
   grep -rn 'ObjectManager::getInstance' app/code/ --include='*.php' | grep -v Test
   grep -rn 'echo \$' app/code/ --include='*.phtml'
   grep -rn 'unserialize(' app/code/ --include='*.php'
   ```
   ⚠️ Flag any findings.

4. **Changelog Check**:
   - Read CHANGELOG.md
   - Check if there are unreleased changes documented
   - If not, ask user to describe changes and update CHANGELOG.md

5. **Summary**:
   - ✅ All checks passed / ❌ Issues found
   - Files changed: `git diff --stat`
   - Ready to push: Yes/No

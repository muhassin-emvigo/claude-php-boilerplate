---
name: bug-fix
description: >
  Structured workflow for fixing bugs in Magento 2 modules.
  Follows: Reproduce → Failing Test → Fix → Verify → Document.
---

# Bug Fix Workflow

## Step 1: Investigate
1. Read the bug report / issue description carefully
2. Identify the affected module and files:
   ```bash
   grep -rn "relevant_keyword" app/code/ --include='*.php'
   ```
3. Check recent changes that might have introduced the bug:
   ```bash
   git log --oneline -20 -- app/code/
   ```
4. Reproduce the bug locally and document exact reproduction steps

## Step 2: Write Failing Test
1. Create a unit test that exposes the bug:
   ```
   app/code/Vendor/Module/Test/Unit/Model/AffectedClassTest.php
   ```
2. Follow naming: `testMethodName_BugScenario_ExpectedBehavior()`
3. Run the test to confirm it **fails**:
   ```bash
   vendor/bin/phpunit --filter=testMethodName
   ```

## Step 3: Apply Fix
1. Make the minimal change needed to fix the bug
2. Follow Magento conventions:
   - Use DI, no ObjectManager
   - Proper type declarations
   - Escape output in templates
3. Check for side effects in related code

## Step 4: Verify
1. Run the previously failing test — it should now **pass**:
   ```bash
   vendor/bin/phpunit --filter=testMethodName
   ```
2. Run the full test suite to check for regressions:
   ```bash
   make test
   ```
3. Run all quality checks:
   ```bash
   make check
   ```

## Step 5: Document
1. Update CHANGELOG.md under `### Fixed`
2. Commit with conventional format:
   ```
   fix(module): brief description of what was fixed

   Closes #ISSUE_NUMBER
   ```
3. If the bug was caused by a pattern, consider adding a Claude rule to prevent recurrence

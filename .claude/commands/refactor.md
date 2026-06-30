---
description: Systematic code refactoring with safety checks — tests before, refactor, tests after
allowed-tools:
  - Read
  - Edit
  - Grep
  - Bash
---

# Refactor Code

1. **Snapshot** — Run tests to establish baseline:
   ```bash
   make test
   ```
   Record: X tests, Y assertions, all passing.

2. **Analyze** — Read the target code and identify:
   - What pattern to apply (extract class, extract method, replace with ViewModel, etc.)
   - Magento-specific refactoring opportunities:
     - Replace `ObjectManager` with constructor DI
     - Replace Block with ViewModel
     - Replace `InstallSchema` with declarative schema
     - Extract logic from Controller to Service/Model
     - Replace class rewrite with Plugin
     - Replace direct collection usage with Repository + SearchCriteria

3. **Refactor** — Make changes following Magento conventions:
   - Preserve all public API contracts (no breaking changes)
   - Update `di.xml` if dependencies change
   - Maintain backward compatibility

4. **Verify** — Run tests again:
   ```bash
   make test
   ```
   Confirm: same X tests, Y assertions, all still passing.

5. **Quality Check**:
   ```bash
   make check
   ```

6. **Commit** with conventional format:
   ```
   refactor(module): [description of refactoring]

   - No functional changes
   - [Describe structural improvements]
   ```

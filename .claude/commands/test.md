---
description: Run PHPUnit test suite and report results with coverage
allowed-tools:
  - Bash
  - Read
---

# Run Tests

1. Run the full test suite:
   ```bash
   vendor/bin/phpunit -c phpunit.xml.dist --colors=always 2>&1
   ```

2. If tests fail, for each failure:
   - Show the test name and assertion that failed
   - Show the relevant source code
   - Suggest a fix

3. Run coverage check:
   ```bash
   vendor/bin/phpunit -c phpunit.xml.dist --coverage-text 2>&1 | grep -A 5 'Code Coverage'
   ```

4. Present results:
   - ✅ Tests passed: X/Y
   - ❌ Tests failed: [list with details]
   - 📊 Code coverage: X%
   - 💡 Suggestions for uncovered code

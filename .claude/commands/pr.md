---
description: Generate a pull request description with diff summary, test results, and Magento-specific details
allowed-tools:
  - Read
  - Grep
  - Bash
---

# Generate PR Description

1. Determine the base branch:
   ```bash
   BASE=$(git rev-parse --abbrev-ref HEAD@{upstream} 2>/dev/null | sed 's|origin/||' || echo "main")
   echo "Comparing against: $BASE"
   ```

2. Gather change information:
   ```bash
   # File stats
   git diff $BASE --stat

   # Changed files list
   git diff $BASE --name-only

   # Commit messages
   git log $BASE..HEAD --oneline --no-merges
   ```

3. Identify Magento-specific changes:
   ```bash
   # XML config changes
   git diff $BASE --name-only | grep '\.xml$'

   # Database schema changes
   git diff $BASE --name-only | grep -E 'db_schema|Setup/Patch'

   # API changes
   git diff $BASE --name-only | grep -E 'webapi\.xml|Api/'

   # ACL changes
   git diff $BASE --name-only | grep 'acl\.xml'
   ```

4. Run quality checks and capture results:
   ```bash
   make check 2>&1 | tail -20
   ```

5. Generate the PR description following the template in the `pr-description` agent

6. Present the description:
   - Show the formatted markdown
   - Ask if it should be copied to clipboard or saved to a file
   - Note any concerns (breaking changes, missing tests, schema migrations)

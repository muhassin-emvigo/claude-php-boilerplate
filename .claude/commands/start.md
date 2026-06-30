---
description: Initialize a new development session — checks environment and presents project context
allowed-tools:
  - Read
  - Bash
  - Grep
---

# Start Development Session

1. Read and internalize CLAUDE.md from the project root
2. Run `bash scripts/check-setup.sh` to verify the environment
3. List all modules in app/code/ with `find app/code -name module.xml -exec dirname {} \;`
4. Show recent git activity with `git log --oneline -10 2>/dev/null || echo 'No git history yet'`
5. Check for any failing tests with `make test 2>&1 | tail -5`
6. Present a summary:
   - Project: Magento 2 Module Development
   - Modules found: [list]
   - Environment: [status]
   - Last activity: [git log]
   - Test status: [pass/fail]
7. Ask: "What would you like to work on?"

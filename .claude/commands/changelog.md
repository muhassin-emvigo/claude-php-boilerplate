---
description: Auto-generate or update CHANGELOG.md from recent git commits
allowed-tools:
  - Bash
  - Read
  - Edit
---

# Generate Changelog

1. Get the latest tag:
   ```bash
   git describe --tags --abbrev=0 2>/dev/null || echo "v0.0.0"
   ```

2. Get commits since last tag, grouped by conventional commit type:
   ```bash
   git log $(git describe --tags --abbrev=0 2>/dev/null || echo "")..HEAD --oneline --no-merges 2>/dev/null || git log --oneline --no-merges
   ```

3. Categorize commits:
   - `feat:` → **Added**
   - `fix:` → **Fixed**
   - `refactor:` → **Changed**
   - `perf:` → **Performance**
   - `docs:` → **Documentation**
   - `chore:`, `ci:`, `build:` → **Maintenance**
   - `BREAKING CHANGE` or `!:` → **Breaking Changes** ⚠️

4. Format as [Keep a Changelog](https://keepachangelog.com/) entry with today's date

5. Present the generated changelog entry and ask:
   - "Should I prepend this to CHANGELOG.md?"
   - "What version number? (current: [last tag])"

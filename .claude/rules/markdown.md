---
globs:
  - "**/*.md"
priority: 40
---

# Markdown File Rules

- Grep/Glob first, Read only matched region — never load whole file for search/lookup
- Narrow query: exact pattern + glob filter, not broad regex over full repo
- Reuse context already in conversation/memory instead of re-reading unchanged file

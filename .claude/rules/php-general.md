---
globs:
  - "**/*.php"
  - "**/*.phtml"
---

# PHP General Rules

- Always add `declare(strict_types=1);` as the first statement after `<?php`
- Use PHP 8.2+ features: readonly properties, enums, named arguments, union/intersection types, constructor property promotion
- Follow PSR-12 coding standard strictly
- Add type declarations on ALL method parameters and return types — no untyped parameters
- Use `readonly` on properties that should not change after construction
- Prefer constructor property promotion to reduce boilerplate
- Never suppress errors with `@` operator
- Use `match` expressions instead of `switch` where appropriate
- Always use strict comparison (`===`, `!==`) instead of loose comparison
- Import classes with `use` statements — never use fully qualified class names inline
- One class per file, filename must match class name
- Use early returns to reduce nesting
- No magic numbers — use named constants

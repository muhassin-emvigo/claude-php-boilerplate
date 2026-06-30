---
name: code-review
description: >
  Perform comprehensive code review of Magento 2 PHP code. Checks coding
  standards, architecture, security, performance, and test coverage.
  Use when asked to review code or check quality.
---

# Magento 2 Code Review

## Review Process

### Step 1: Identify Changed Files
```bash
git diff --name-only HEAD~1 2>/dev/null || find app/code -name '*.php' -newer composer.json
```

### Step 2: Automated Checks
Run these tools and collect output:
```bash
# Coding standards
vendor/bin/phpcs --standard=Magento2 --report=json [files]

# Static analysis
vendor/bin/phpstan analyse --error-format=json [files]

# Mess detection
vendor/bin/phpmd [files] json phpmd.xml.dist

# Auto-fixable issues
vendor/bin/php-cs-fixer fix --dry-run --diff [files]
```

### Step 3: Manual Review Checklist

#### Architecture
- [ ] DI used correctly (constructor injection, no ObjectManager)
- [ ] Service contracts defined for public APIs
- [ ] Proper separation of concerns
- [ ] No class rewrites (preferences) where plugins suffice

#### Code Quality
- [ ] strict_types declared
- [ ] Full type coverage (params + returns)
- [ ] No dead code or unused imports
- [ ] Error handling with proper exception types
- [ ] Logging at appropriate levels

#### Security
- [ ] No SQL injection vectors
- [ ] Output escaping in templates
- [ ] ACL for admin actions
- [ ] CSRF protection
- [ ] Input validation

#### Performance
- [ ] No N+1 query patterns
- [ ] Collections use proper filters (not load-all-then-filter)
- [ ] Heavy operations not in event observers
- [ ] Cache usage where appropriate

#### Tests
- [ ] Tests exist for new/modified code
- [ ] Tests are meaningful (not just checking true === true)
- [ ] Edge cases covered

### Step 4: Output Format

```
## Code Review Summary

**Files Reviewed**: 5
**Issues Found**: 3 critical, 2 warnings, 4 suggestions

### 🔴 Critical
1. `Model/Payment.php:45` — Direct ObjectManager usage
   **Fix**: Inject dependency via constructor

### 🟡 Warnings
1. `Block/Product.php:23` — Missing return type declaration
   **Fix**: Add `: string` return type

### 🔵 Suggestions
1. `Helper/Data.php` — Consider using ViewModel instead

### ✅ What Looks Good
- Clean service contract implementation
- Proper use of data patches
```

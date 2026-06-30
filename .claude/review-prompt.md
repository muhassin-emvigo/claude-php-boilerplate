# Code Review Prompt

You are reviewing a Magento 2 module change. Follow this structured review process.

## Priority Order
1. **🔴 Security** — SQL injection, XSS, CSRF, ACL, credentials exposure
2. **🟠 Correctness** — Logic errors, edge cases, exception handling, data integrity
3. **🟡 Performance** — N+1 queries, unbounded collections, missing indexes, heavy observers
4. **🔵 Architecture** — DI patterns, service contracts, plugin vs observer, separation of concerns
5. **🟢 Standards** — Coding standard, naming conventions, type declarations, documentation

## Review Checklist

### Security (non-negotiable)
- [ ] No raw SQL or string concatenation in queries
- [ ] Output properly escaped in templates (`$escaper->escapeHtml()`)
- [ ] ACL defined for admin controllers and API endpoints
- [ ] Form key validation for POST requests
- [ ] No `unserialize()` on user input
- [ ] Sensitive data not logged

### Magento Patterns
- [ ] Constructor DI only — no ObjectManager
- [ ] Service contracts for public APIs
- [ ] Plugins over class rewrites
- [ ] ViewModels over Block classes
- [ ] Declarative schema (no InstallSchema/UpgradeSchema)
- [ ] Idempotent data patches

### Code Quality
- [ ] `declare(strict_types=1)` in every PHP file
- [ ] Type declarations on all parameters and return types
- [ ] Meaningful variable and method names
- [ ] No dead code or unused imports
- [ ] Proper Magento exception classes (not generic \Exception)

### Tests
- [ ] Tests exist for new/modified public methods
- [ ] Tests follow AAA pattern and naming convention
- [ ] Edge cases and error scenarios covered
- [ ] All dependencies mocked in unit tests

## Output Format
For each finding, provide:
- **Severity**: 🔴 Critical | 🟠 High | 🟡 Medium | 🔵 Low | 🟢 Suggestion
- **File:Line**: exact location
- **Issue**: what's wrong
- **Fix**: suggested fix with code snippet

Always cite evidence — quote the specific code that triggers the finding.

---
globs:
  - "**/*.php"
  - "**/*.phtml"
priority: 100
---

# Security Rules

## SQL Injection Prevention
- NEVER use raw SQL queries or string concatenation in queries
- Use Magento's Resource Model, Collection, or SearchCriteria for data access
- If raw queries are unavoidable, use bound parameters with `$connection->quoteInto()` or prepared statements

## XSS Prevention
- In `.phtml` templates, always escape output:
  - `$escaper->escapeHtml($value)` for HTML content
  - `$escaper->escapeUrl($url)` for URLs
  - `$escaper->escapeJs($string)` for JavaScript strings
  - `$escaper->escapeHtmlAttr($attr)` for HTML attributes
- Never use `echo $variable` without escaping in templates
- Use `$block->getBlockHtml('formkey')` to include form keys

## CSRF Protection
- Validate form key in all POST controller actions: `$this->formKeyValidator->validate($request)`
- Include form key in all HTML forms
- Admin controllers must extend `\Magento\Backend\App\Action`

## Access Control
- Define ACL resources in `etc/acl.xml` for every admin action
- Admin controllers must declare `const ADMIN_RESOURCE = 'vendor_Module::resource'`
- Web API endpoints must specify ACL resource in `webapi.xml`
- Never expose admin functionality without proper ACL checks

## General
- Never use `unserialize()` on user input — use `json_decode()` or Magento's serializer
- Never log sensitive data (passwords, tokens, credit card numbers, PII)
- Validate and sanitize all user input before processing
- Use Magento's built-in CSRF token mechanism for AJAX requests

---
globs:
  - "**/*.php"
  - "**/env.php"
  - "**/.env*"
---

# Environment & Configuration Rules

## Environment Variables
- NEVER hardcode credentials, API keys, or secrets in source code
- Use Magento's `env.php` for environment-specific configuration
- Use `app/etc/config.php` for module enable/disable state (committed to git)
- Use `app/etc/env.php` for sensitive data (NEVER committed to git)
- Document all required environment variables in `.env.example`

## Configuration Management
- Use `system.xml` for admin-configurable values (Stores > Configuration)
- Access config values via `ScopeConfigInterface::getValue()`, never direct DB queries
- Use config path convention: `vendor_module/group/field`
- Set default values in `etc/config.xml`
- Use `@magentoConfigFixture` in integration tests to set config values

## Deploy Modes
- **Developer mode**: Full error reporting, no caching, static files on-the-fly
- **Production mode**: Full caching, static content pre-deployed, no auto-generation
- **Default mode**: Not recommended — hybrid behavior
- Code must work correctly in ALL modes — never rely on developer mode behavior

## Sensitive Data
- Store secrets in `env.php` or environment variables
- Use `Magento\Config\Model\Config\Backend\Encrypted` for encrypted config fields
- Never log passwords, tokens, API keys, or PII
- Mark sensitive config fields with `<sensitive>1</sensitive>` in `system.xml`
- Use `bin/magento config:sensitive:set` for production deployments

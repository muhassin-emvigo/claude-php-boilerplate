---
globs:
  - "app/code/**/*.php"
  - "app/code/**/*.xml"
---

# Magento Module Rules

## Dependency Injection
- Use constructor injection exclusively — NEVER use `ObjectManager::getInstance()` directly
- The only acceptable ObjectManager usage is in `registration.php`, factories, and CLI commands' entry points
- Define preferences and plugins in `etc/di.xml`
- Use virtual types to avoid unnecessary class inheritance

## Service Contracts
- Define API interfaces in `Api/` directory for all public-facing module APIs
- Data interfaces go in `Api/Data/` — use getters/setters with `@api` annotation
- Repository interfaces extend nothing — define `save()`, `getById()`, `delete()`, `getList()`
- Return types must use interfaces, not concrete classes

## Plugins vs Observers
- Use **Plugins** (interceptors) to modify behavior of existing methods (before/after/around)
- Use **Observers** for event-driven side effects that don't modify the return value
- NEVER use class preferences (rewrites) unless absolutely no alternative exists
- Around plugins must call `$proceed()` and return its result

## Patterns
- ViewModels over Block classes for providing data to templates
- Data Patches (`Setup/Patch/Data/`) for data migrations — must be idempotent
- Use `Magento\Framework\Api\SearchCriteriaBuilder` for filtering collections via repositories
- Always register modules via `ComponentRegistrar::register()` in `registration.php`

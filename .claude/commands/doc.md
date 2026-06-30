---
description: Generate or update documentation for a Magento 2 module or specific files
allowed-tools:
  - Read
  - Edit
  - Grep
  - Bash
---

# Generate Documentation

1. Identify the target:
   - If a file path is provided, document that specific file
   - If a module name is provided, document the entire module
   - If no argument, scan `app/code/` for modules and ask which to document

2. For a **module**, generate:
   - Module README with purpose, installation, configuration
   - List all XML configurations with descriptions
   - API endpoint documentation (from `webapi.xml`)
   - Admin configuration guide (from `system.xml`)
   - List all observers, plugins, and cron jobs

3. For a **PHP file**, generate/update:
   - Class-level PHPDoc with `@api`, `@since` tags
   - Method-level PHPDoc with `@param`, `@return`, `@throws`
   - Inline comments for complex logic

4. For **XML files**, document:
   - Purpose of the configuration
   - Available nodes and attributes
   - Usage examples

5. Output the documentation and ask:
   - "Should I create/update the README.md in the module directory?"
   - "Should I add PHPDoc blocks to the source files?"

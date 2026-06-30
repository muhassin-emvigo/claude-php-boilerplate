---
globs:
  - "**/*.xml"
  - "**/*.xsd"
---

# Magento XML Configuration Rules

- Always include the XSD schema reference in root XML elements: `xsi:noNamespaceSchemaLocation="urn:magento:..."` 
- Use proper indentation (4 spaces) in all XML files
- `di.xml`: Group type declarations logically — plugins first, then preferences, then virtual types
- `db_schema.xml`: Always generate `db_schema_whitelist.json` after schema changes via `bin/magento setup:db-declaration:generate-whitelist`
- `system.xml`: Use proper section > group > field hierarchy with `translate="label"` on all labels
- `acl.xml`: Define granular permissions — one resource per admin action
- `events.xml`: Event observer names must be unique and descriptive: `vendor_module_observer_name`
- `webapi.xml`: Define routes with proper HTTP methods and ACL resources
- Route IDs must be unique: use `vendor_module` prefix in `routes.xml`
- Menu items must reference ACL resources in `menu.xml`

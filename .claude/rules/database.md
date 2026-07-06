---
globs:
  - "**/db_schema*"
  - "**/Setup/**"
  - "**/ResourceModel/**"
---

# Database Rules

## Declarative Schema
- Use `db_schema.xml` for ALL schema definitions — never use `InstallSchema` or `UpgradeSchema`
- Always generate whitelist after schema changes: `bin/magento setup:db-declaration:generate-whitelist --module-name=Vendor_ModuleName`
- Include `db_schema_whitelist.json` in version control

## Naming Conventions
- Table names: `vendor_module_entity` (lowercase, underscored)
- Primary key: `entity_id` (auto-increment integer or identity column)
- Foreign keys: `FK_VENDOR_MODULE_ENTITY_FIELD__REF_TABLE_REF_FIELD`
- Indexes: `IDX_VENDOR_MODULE_ENTITY_FIELD`
- Unique constraints: `UNQ_VENDOR_MODULE_ENTITY_FIELD`

## Data Patches
- Implement `\Magento\Framework\Setup\Patch\DataPatchInterface`
- Must be idempotent — safe to run multiple times
- Use `PatchVersionInterface` for version ordering if needed
- Keep patches focused — one logical change per patch
- Never delete data in patches without explicit confirmation mechanism

## Resource Models & Collections
- Resource models extend `\Magento\Framework\Model\ResourceModel\Db\AbstractDb`
- Collections extend `\Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection`
- Use `SearchCriteriaBuilder` in repositories, not direct collection manipulation
- Implement proper `_construct()` method calling `_init()`

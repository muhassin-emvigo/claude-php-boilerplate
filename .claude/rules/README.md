# Rule precedence

Every file in this directory declares `globs:` in its frontmatter, and several
overlap on the same path — for example, a single file at
`app/code/Vendor/Module/Model/Entity.php` matches `magento-module.md`,
`environment.md`, `error-handling.md`, `php-general.md`, and `security.md`
simultaneously. Previously nothing declared which rule wins if two files ever
gave conflicting guidance for the same path.

Each rule file now carries a `priority:` integer in its frontmatter. **Higher
number wins** when rules conflict for the same file. This mirrors the
priority/precedence structure ported from em-claude-skills
(`fullstack-dev-skills`), adapted from its skill `depends_on` / `strength`
concept to a flat numeric scale suited to glob-scoped rules.

| Priority | Rule file | Scope | Why this tier |
|---|---|---|---|
| 100 | `security.md` | `**/*.php`, `**/*.phtml` | Non-negotiable. Security guidance always overrides style/convention preferences — never downgrade this to resolve a conflict. |
| 60 | `testing.md` | `**/*Test.php`, `**/Test/**/*.php` | Narrowest path scope of any rule file; test-specific conventions (mocking, fixtures) take precedence over general PHP rules inside `Test/`. |
| 50 | `api.md` | `**/Api/**`, `**/webapi.xml`, `**/Controller/**` | Domain-specific, narrow path scope. |
| 50 | `database.md` | `**/db_schema*`, `**/Setup/**`, `**/ResourceModel/**` | Domain-specific, narrow path scope. Tied with `api.md` — the two globs don't overlap in practice, so no conflict arises between them. |
| 40 | `magento-xml.md` | `**/*.xml`, `**/*.xsd` | Format-specific, applies repo-wide; ranks above the module-wide baseline because XML structural conventions (schema refs, ACL hierarchy) are more specific than generic module conventions. |
| 30 | `magento-module.md` | `app/code/**/*.php`, `app/code/**/*.xml` | Module-wide Magento baseline (DI, plugins vs. observers, service contracts). |
| 20 | `error-handling.md` | `**/*.php` | Topic-specific baseline. |
| 20 | `environment.md` | `**/*.php`, `**/env.php`, `**/.env*` | Topic-specific baseline. Tied with `error-handling.md` — distinct topics, no real overlap. |
| 10 | `php-general.md` | `**/*.php`, `**/*.phtml` | Broadest, most generic style baseline (PSR-12, strict types). Everything else overrides it. |

## How to use this when rules seem to conflict

1. Identify every rule file whose `globs:` match the file you're editing.
2. If their guidance agrees, follow all of them.
3. If it conflicts, follow the rule with the higher `priority:` value.
4. `security.md` at priority 100 is a hard floor — never let a lower-priority
   rule (including project-specific style preferences) override a security
   requirement.
5. If two rule files with the *same* priority genuinely conflict on the same
   path (shouldn't happen with the table above, but if a new rule file is
   added), treat that as a bug in the rule set and resolve it by adjusting
   priorities — don't guess at runtime.

## Adding a new rule file

Give it a `priority:` in the same range as similarly-scoped existing rules
(see table above), not an arbitrary number. Update this table when you do.

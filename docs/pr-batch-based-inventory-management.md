# PR: Batch-Based Inventory Management (FEFO) - Rgd_Inventory Module

**Branch:** `feature/fefo-batch-inventory` -> `rgd_dental_dev`
**Commit:** `c50dfb0` (1 commit, 54 files, +5718/-26)
**Status:** Approved for Phase 1 shipping (see Approver sign-off below) - not yet pushed / PR not yet opened on GitHub.

## Summary

Adds `Rgd_Inventory`, a new Magento 2 module that tracks pharmacy stock by SKU + batch number, each with its own expiry date and quantity, and automatically dispenses using First Expiry, First Out (FEFO) ordering. It is not a bolt-on report: it hooks into Magento's real MSI (Multi-Source Inventory) deduction path via a plugin on `SourceDeductionServiceInterface::execute()`, so every shipment- and creditmemo-driven deduction actually draws from the correct batch, switches batches automatically when one is exhausted, and writes an append-only audit ledger of exactly which batch every unit came from - a hard compliance requirement for a pharmacy.

## Type of Change
- [ ] Bug fix (non-breaking change fixing an issue)
- [x] New feature (non-breaking change adding functionality)
- [ ] Breaking change (fix or feature causing existing functionality to break)
- [ ] Documentation update
- [ ] Refactor (no functional changes)
- [ ] Test update

New module addition: `app/code/vendor/Rgd/Inventory`. No existing module's code was modified - integration with MSI is entirely via an additive plugin, not an edit to core or to `CustomShipping`.

## Changes Made

### Module Changes - `app/code/vendor/Rgd/Inventory` (new module, 40 files)

- **API contracts (8 files, `Api/`)** - `BatchRepositoryInterface`, `FefoBatchSelectorInterface` (read-only selection), `BatchDeductionServiceInterface` (atomic locking deduction), `BatchReturnServiceInterface` (Phase 2 contract, stubbed), and their `Data/` DTOs (`BatchInterface`, `BatchTransactionInterface`, `BatchAllocationInterface`, `BatchSearchResultsInterface`).
- **Models (10 files, `Model/`)** - `BatchRepository`, `FefoBatchSelector`, `BatchDeductionService` (`SELECT ... FOR UPDATE` locking + allocation), `BatchReturnService` (Phase 2 no-op stub), `SourceDeductionCoordinator` (owns the shared transaction - see ADR Decision 3), `Data/Batch`, `Data/BatchAllocation`, `Data/BatchTransaction`, plus `ResourceModel/Batch`, `ResourceModel/Batch/Collection`, `ResourceModel/BatchTransaction`.
- **Plugin (1 file)** - `Plugin/InventorySourceDeduction/SourceDeductionServicePlugin.php`, an around-plugin on `Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface::execute()`. This is the single integration point with core Magento - additive only, no core files touched.
- **Admin UI (Controllers/UI, 8 files)** - `Controller/Adminhtml/Batch/{Index,Edit,Save,Delete}`, `Ui/Component/Listing/Column/Actions`, `Ui/DataProvider/BatchDataProvider`, grid/form `ui_component` XML and `adminhtml` layout XML for batch CRUD.
- **Tests (4 files, 37 tests total)** - `Test/Unit/Model/{BatchDeductionServiceTest,BatchRepositoryTest,FefoBatchSelectorTest,SourceDeductionCoordinatorTest}`.
- **Docs** - module `README.md` (usage, admin path, Known Limitations), plus repo-level `docs/spec-`, `docs/build-summary-`, `docs/qa-scenario-verification-`, `docs/progress-`, and `docs/adr/1-fefo-batch-inventory-architecture.md`.

### Configuration Changes

- **db_schema.xml**: 2 new tables - `rgd_inventory_batch` (batch_id, sku, batch_number, expiry_date, received_qty, remaining_qty, source_code, is_active, timestamps; unique constraint on `sku+batch_number+source_code`; 3 indexes covering the FEFO hot path) and `rgd_inventory_batch_transaction` (append-only audit ledger; FK to batch with app-layer delete guard; 3 indexes for audit-trail queries including `order_item_id`). **Requires `bin/magento setup:upgrade`.**
- **di.xml**: 5 interface-to-implementation preferences plus the `SourceDeductionServiceInterface` plugin registration.
- **acl.xml**: new `Rgd_Inventory::batch` admin resource.
- **adminhtml/menu.xml + routes.xml**: new admin grid under the Inventory area.
- **webapi.xml**: none - Phase 1 exposes admin-grid CRUD and the internal service contracts only; no new REST/GraphQL surface.
- **composer.json / phpstan.neon.dist / phpunit.xml.dist**: module test suite wired in; a pre-existing `exclude-from-classmap` bug in `composer.json` that broke phpunit entirely was also fixed as part of this work (see Bug Fixing history below).

### Files Changed (by category)

| Category | Count | Notes |
|---|---:|---|
| API contracts + Data DTOs | 8 | `Api/`, `Api/Data/` |
| Model implementations | 10 | `Model/`, `Model/ResourceModel/` |
| Plugin | 1 | Sole MSI integration point |
| Admin controllers / UI | 8 | Controllers, UI DataProvider, listing column, ui_component + layout XML |
| Schema / DI / ACL / routing config | 7 | `db_schema.xml`, `db_schema_whitelist.json`, `di.xml`, `acl.xml`, `module.xml`, `menu.xml`, `routes.xml` |
| i18n | 1 | `en_US.csv` |
| Unit tests | 4 | 37 tests / 112 assertions |
| Module + repo docs | 6 | README, spec, build-summary, QA verification, progress log, ADR |
| Build tooling | 3 | `composer.json`, `phpstan.neon.dist`, `phpunit.xml.dist` |

Full file-level diff: `git diff rgd_dental_dev...feature/fefo-batch-inventory --stat` (54 files, +5718/-26).

## How to Test

1. `bin/magento setup:upgrade` (creates `rgd_inventory_batch` and `rgd_inventory_batch_transaction`), then `bin/magento setup:di:compile`.
2. In admin, go to the new Rgd Inventory Batches grid (see module README for exact path) and create two batches for the same SKU with different expiry dates and quantities.
3. Place an order (or run a shipment) for that SKU for a quantity less than the earliest-expiry batch's stock - confirm via the batch grid or the `rgd_inventory_batch_transaction` table that the deduction came from the earlier-expiry batch first.
4. Exhaust the first batch across multiple orders - confirm the system automatically switches to the next batch with no caller-side batch selection, and that the product stays salable.
5. Exhaust all batches for the SKU - confirm the product goes Out of Stock and that both `FefoBatchSelectorInterface::selectForDeduction()` and `BatchDeductionServiceInterface::deduct()` reject further requests with a `LocalizedException` rather than under-fulfilling.
6. Query `rgd_inventory_batch_transaction` for the SKU and confirm every unit deducted is attributed to the correct batch (sum of ledger rows equals total deducted).
7. Full scripted version of the above (real MySQL, not mocks) is captured in `docs/qa-scenario-verification-batch-based-inventory-management.md`.

## Test Results

- Unit tests: 37/37 passing (112 assertions) - `php vendor/bin/phpunit -c phpunit.xml.dist`
- PHPCS: No violations - `php vendor/bin/phpcs --standard=phpcs.xml.dist app/code/vendor/Rgd/Inventory`
- PHPStan: No errors - `php vendor/bin/phpstan analyse -c phpstan.neon.dist app/code/vendor/Rgd/Inventory`
- PHPMD: Clean (0 findings) - `php vendor/bin/phpmd app/code/vendor/Rgd/Inventory text phpmd.xml.dist`
- Real-database scenario verification: 11/11 checks passed against a live MySQL database (not SQLite/mocked) - FEFO ordering, auto-switch on batch exhaustion, out-of-stock rejection on both the read-only selector and the locking deduction path, full audit-ledger attribution, and the expiry-cutoff boundary (`expiry_date > CURDATE()`, a batch expiring today is treated as already expired). See `docs/qa-scenario-verification-batch-based-inventory-management.md` for the full transcript.

## How This Was Verified

This shipped through a full 16-stage pipeline (Classifier, CEO, Architecture, Eng spec, Build, Unit Testing, QA/Scenario Testing, Security Testing, Performance Testing, six rounds of Bug Fixing, Code Review, two Approver passes, ADR, Documentation; see `docs/progress-batch-based-inventory-management.md` for the stage-by-stage log). That process is why this is trustworthy, not a formality - it found and fixed real, material bugs that a lighter review would have shipped:

- **A non-functional core algorithm.** QA/Scenario Testing found the FEFO `ORDER BY` clause had broken SQL syntax - the feature's central sorting logic (earliest-expiry-first) did not work at all. Caught before it ever reached a real database.
- **A transaction-atomicity integrity gap (HIGH, Security Testing).** The original design ran each item's batch deduction in its own transaction, independent of MSI's own `proceed()` decrement. If `proceed()` or a later item then failed, the batch ledger could commit while MSI's aggregate did not - two stores permanently disagreeing with no rollback. Fixed by introducing `SourceDeductionCoordinator`, which owns one shared transaction spanning every item's batch deduction plus MSI's `proceed()` - see ADR Decision 3 for the full writeup, including why this was judged unacceptable specifically in a pharmacy context.
- **Redundant hot-path queries (CRITICAL, Performance Testing).** 3-4 redundant queries per item on the checkout deduction path, plus a missing index causing filesorts and an N+1 order-item lookup. Fixed: queries deduplicated, index restructured to cover `source_code`, N+1 batched into a single query, lock-acquisition order documented.
- **A production-only duplicate-detection bug (CRITICAL, Code Review).** Duplicate-key detection was implemented by checking SQLite error text, which does not exist on production MySQL - the guard would have silently failed to catch duplicate batch numbers in production despite passing in the (SQLite-backed) test suite.
- **A missing admin UI (CRITICAL, Code Review).** The entire admin CRUD grid - spec'd as part of Phase 1 - was absent from the first build pass. Added in a follow-up build round and re-reviewed.
- **Also caught along the way:** a call to a nonexistent method (`$item->getQtyToDeduct()`) that would have fatal-errored on every real checkout in production (found during the Security-triggered bug-fix pass); a fatally miscounted admin-facing diagnostic message (`fetchOne()` used where `COUNT(*)` was needed, producing values like "1227 batch(es)" instead of "1 batch(es)" - found only during real-database verification, invisible to mocked unit tests, and fixed on the spot); and a pre-existing `composer.json` bug that broke `phpunit` entirely, unrelated to this feature but blocking its own test run.

All findings above were independently re-verified closed by a second Approver pass before this branch was committed. No unresolved BLOCKER/CRITICAL/HIGH issue remains anywhere in the review history.

## Backward Compatibility

- [x] This change is backward compatible
- [ ] Breaking changes documented above
- [x] Data migration patch included (if DB changes) - handled via standard declarative schema (`db_schema.xml`), not a data patch; see Migration Notes below.

This is a new module with no changes to `CustomShipping` or to any existing Magento core behavior. The only integration point is an additive around-plugin on `Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface::execute()`, which wraps the existing MSI deduction flow rather than replacing it - `proceed()` (MSI's native `source_item` decrement) still runs, now inside the module's shared transaction, so MSI's salable-quantity, reservation, and shipping behavior is unchanged for any SKU that doesn't have batches configured.

## Scope Note - Phase 1 Only

This ships Phase 1 as scoped and approved:
- Done: FEFO batch selection, locked transactional deduction, audit ledger, admin CRUD grid.
- Deferred to Phase 2: `BatchReturnServiceInterface::restore()` is a documented no-op stub - returns/creditmemos are not yet restored to their original batch (the contract signature is locked now so Phase 2 is a drop-in; see ADR Decision 4).
- Deferred to Phase 2: CSV batch import. `expiry_date` is intentionally nullable at the DB layer (required only at the admin-form layer) to leave room for import - do not tighten it to `NOT NULL` without accounting for this.

Full rationale for these and all other architectural decisions (MSI integration approach, locking strategy, transaction boundary, returns strategy, ledger authority) is in `docs/adr/1-fefo-batch-inventory-architecture.md`. Full functional spec is in `docs/spec-batch-based-inventory-management.md`.

## Migration / Deployment Notes

- `bin/magento setup:upgrade` is required - this PR adds 2 new tables (`rgd_inventory_batch`, `rgd_inventory_batch_transaction`) via declarative schema.
- `bin/magento setup:di:compile` is required - new DI preferences and plugin registration.
- No existing tables are altered; no data patch is needed for existing installs (the new tables start empty and are additive).

## Related Issues

- Source requirement: `docs/requirements/2026-07-08-batch-based-inventory-management.md`

## Evidence

- Real-database (live MySQL, not mocked) scenario verification transcript: `docs/qa-scenario-verification-batch-based-inventory-management.md` (11/11 checks, includes actual SQL and query output).
- Full stage-by-stage pipeline log: `docs/progress-batch-based-inventory-management.md`.
- Architecture decisions and trade-offs: `docs/adr/1-fefo-batch-inventory-architecture.md`.

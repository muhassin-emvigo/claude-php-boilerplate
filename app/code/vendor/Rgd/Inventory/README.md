# Rgd_Inventory Module — FEFO Batch Inventory Management

## Overview

The `Rgd_Inventory` module implements batch-based inventory management with First Expiry, First Out (FEFO) ordering for the RGD Dental clinic. It allows tracking of multiple batches per SKU with individual expiry dates and quantities, automatically selecting the batch with the earliest expiry date when dispensing stock.

**Status:** Phase 1 shipped (commit `c50dfb0`, branch `feature/fefo-batch-inventory`) — FEFO deduction,
audit ledger, and admin CRUD UI are live. Phase 2 (returns/restoration, CSV import) is deferred; see
[Known limitations (Phase 1)](#known-limitations-phase-1) below and
[docs/adr/1-fefo-batch-inventory-architecture.md](../../../../../docs/adr/1-fefo-batch-inventory-architecture.md) for the architectural
decisions behind the split.

---

## Module Structure

```
Rgd/Inventory/
├── Api/                              # Public service contracts
│   ├── BatchRepositoryInterface.php   # Batch CRUD
│   ├── FefoBatchSelectorInterface.php # Read-only batch selection
│   ├── BatchDeductionServiceInterface.php # Transactional deduction
│   ├── BatchReturnServiceInterface.php # Phase 2 restore logic (stub)
│   └── Data/                          # Data transfer objects
│       ├── BatchInterface.php         # Batch entity
│       ├── BatchTransactionInterface.php # Audit log entry
│       ├── BatchAllocationInterface.php # FEFO allocation result (immutable)
│       └── BatchSearchResultsInterface.php # SearchCriteria result wrapper
├── Model/
│   ├── Data/                          # Magento model classes
│   │   ├── Batch.php                  # Batch entity model
│   │   ├── BatchTransaction.php       # Transaction entity model
│   │   └── BatchAllocation.php        # Immutable allocation DTO
│   ├── ResourceModel/
│   │   ├── Batch.php                  # Batch database layer
│   │   ├── BatchTransaction.php       # Transaction database layer
│   │   └── Batch/Collection.php       # Batch collection
│   ├── BatchRepository.php            # Batch repository implementation
│   ├── FefoBatchSelector.php          # FEFO selection logic (read-only)
│   ├── BatchDeductionService.php      # Transactional deduction with locking
│   ├── BatchReturnService.php         # Phase 2 stub
│   └── SourceDeductionCoordinator.php # Plugin coordinator
├── Plugin/
│   └── InventorySourceDeduction/
│       └── SourceDeductionServicePlugin.php # Around plugin on MSI deduction
├── etc/
│   ├── module.xml                     # Module declaration + dependencies
│   ├── di.xml                         # Dependency injection configuration
│   ├── acl.xml                        # Admin ACL resources
│   ├── db_schema.xml                  # Database schema (declarative)
│   └── db_schema_whitelist.json       # Schema whitelist
├── i18n/
│   └── en_US.csv                      # English translations
└── registration.php                   # Module registration
```

---

## Database Schema

### `rgd_inventory_batch`

Tracks batch inventory with FEFO metadata.

| Column | Type | Notes |
|--------|------|-------|
| `batch_id` | int unsigned | PK, auto-increment |
| `sku` | varchar(64) | Product SKU (matches catalog_product_entity.sku) |
| `batch_number` | varchar(64) | Batch identifier |
| `expiry_date` | date nullable | ISO date (Y-m-d); NULL = no expiry tracked |
| `received_qty` | decimal(12,4) | Intake amount (immutable, audit reference) |
| `remaining_qty` | decimal(12,4) unsigned | Current available quantity (ledger value) |
| `source_code` | varchar(255) | MSI source code (default = 'default') |
| `is_active` | smallint | 1=active, 0=inactive (soft-delete) |
| `created_at` | timestamp | Row creation time |
| `updated_at` | timestamp | Last update time |

**Unique Key:** (sku, batch_number, source_code) — prevents duplicate batch entries

**Indexes:**
- `RGD_INVENTORY_BATCH_SKU_SOURCE_CODE_EXPIRY_DATE` — covers FEFO selector's hot query (sku + source_code equality predicates, then expiry_date for the FEFO ordering/range); `remaining_qty` intentionally excluded from this index — as a trailing column after an inequality/OR-NULL predicate it isn't seekable there
- `RGD_INVENTORY_BATCH_EXPIRY_DATE` — supports expiry-range filtering
- `RGD_INVENTORY_BATCH_BATCH_NUMBER` — supports batch number filtering

### `rgd_inventory_batch_transaction`

Audit log of all batch movements (intake, deduction, adjustment, return).

| Column | Type | Notes |
|--------|------|-------|
| `transaction_id` | int unsigned | PK, auto-increment |
| `batch_id` | int unsigned | FK to rgd_inventory_batch (CASCADE on delete) |
| `sku` | varchar(64) | Denormalized snapshot (audit trail completeness) |
| `batch_number` | varchar(64) | Denormalized snapshot |
| `expiry_date` | date nullable | Denormalized snapshot at time of movement |
| `movement_type` | varchar(32) | deduction \| return \| adjustment \| intake |
| `qty` | decimal(12,4) signed | Signed quantity (negative = deduction/out, positive = intake/return) |
| `sales_event_type` | varchar(32) nullable | Raw SalesEventInterface::getType() value; null for manual adjustments |
| `order_id` | int unsigned nullable | Associated order (if order-bound) |
| `order_item_id` | int unsigned nullable | Associated order item (enables split-batch traceability) |
| `reference` | varchar(64) nullable | Free-text: creditmemo id, admin note, etc. |
| `created_at` | timestamp | Row creation time (append-only) |

**Indexes:**
- `RGD_INV_BATCH_TXN_SKU_CREATED_AT` — traceability queries by SKU + time
- `RGD_INV_BATCH_TXN_ORDER_ITEM_ID` — split-batch lookup per order line
- `RGD_INV_BATCH_TXN_BATCH_ID` — covers FK, batch drill-down
- `RGD_INV_BATCH_TXN_BATCH_NUMBER` — supports admin report filter by batch number
- `RGD_INV_BATCH_TXN_EXPIRY_DATE` — supports admin report filter by expiry date range

---

## Service Contracts & APIs

### `BatchRepositoryInterface`

CRUD interface for batches.

```php
interface BatchRepositoryInterface
{
    public function save(BatchInterface $batch): BatchInterface;
    public function getById(int $batchId): BatchInterface;
    public function getBySkuAndBatchNumber(string $sku, string $batchNumber, string $sourceCode = 'default'): BatchInterface;
    public function getList(SearchCriteriaInterface $searchCriteria): BatchSearchResultsInterface;
    public function delete(BatchInterface $batch): bool;
    public function deleteById(int $batchId): bool;
}
```

**Error Handling:**
- `save()`: Throws `CouldNotSaveException` if remaining_qty > received_qty or unique key violation
- `getById()` / `getBySkuAndBatchNumber()`: Throws `NoSuchEntityException` if not found
- `delete()` / `deleteById()`: Throws `CouldNotDeleteException` if batch has transaction history (audit-trail guard)

### `FefoBatchSelectorInterface`

Read-only batch selection (no locking or mutation).

```php
interface FefoBatchSelectorInterface
{
    public function selectForDeduction(string $sku, float $requestedQty, string $sourceCode = 'default'): array;
    public function getAvailableBatches(string $sku, string $sourceCode = 'default'): array;
}
```

`selectForDeduction()` returns an array of `BatchAllocationInterface` objects (immutable DTOs) ordered by earliest expiry first.

`getAvailableBatches()` returns an array of `BatchInterface` objects for all active, non-expired, in-stock batches in the same FEFO order — but, unlike `selectForDeduction()`, it never throws and doesn't allocate against a target quantity. It's the read-only stock/expiry check used by the GraphQL resolver below (and is the method to reach for anywhere else that just needs "what's currently sellable for this SKU" without needing to consume it).

**Error Handling (`selectForDeduction` only):**
- Throws `LocalizedException` with distinct messages for:
  - `RGD_INV_INSUFFICIENT_STOCK` — Not enough stock across all batches
  - No batches configured for SKU
  - All batches expired

### `BatchDeductionServiceInterface`

Owns the lock/transaction lifecycle for atomic deduction.

```php
interface BatchDeductionServiceInterface
{
    public function deduct(
        string $sku,
        float $qty,
        SalesEventInterface $salesEvent,
        ?int $orderItemId,
        string $sourceCode = 'default'
    ): array;
}
```

**Behavior (standalone call via the public `deduct()` method):**
1. Acquires `SELECT ... FOR UPDATE` lock on candidate batches
2. Computes FEFO allocation against locked rows
3. Updates `remaining_qty` for each allocated batch
4. Writes `movement_type=deduction` audit rows
5. Commits (or rolls back on error)

`deduct()` is a fully self-contained, single-item, single-transaction operation — the right entry point
for any future caller outside the checkout plugin (e.g. manual admin adjustment tooling).

The concrete `Rgd\Inventory\Model\BatchDeductionService` class additionally exposes a
`deductWithinTransaction(..., AdapterInterface $connection, ?int $orderId)` method that performs the
same allocate/update/audit-write work but takes an **externally supplied, already-open** connection and
does not begin/commit/roll back anything itself. This method is intentionally **not** part of the
`@api` interface — it is transaction plumbing consumed only by `SourceDeductionCoordinator` (see Plugin
Integration below), which needs to keep one transaction open across several items *and* MSI's own
`proceed()` call. `deduct()` itself calls `deductWithinTransaction()` internally, wrapped in its own
transaction, so the two entry points share one allocation implementation.

**Error Handling:**
- `LocalizedException RGD_INV_INSUFFICIENT_STOCK` — Insufficient or all-expired stock
- `CouldNotSaveException` — Ledger write failure (transaction already rolled back)

### `BatchReturnServiceInterface`

Phase 2 stub (not implemented in phase 1).

```php
interface BatchReturnServiceInterface
{
    public function restore(
        string $sku,
        float $qty,
        SalesEventInterface $salesEvent,
        ?int $orderItemId,
        string $sourceCode = 'default'
    ): array;
}
```

Currently returns `[]` (no-op). Phase 2 will reverse audited deductions using transaction history.

---

## Plugin Integration

### `SourceDeductionServicePlugin` + `SourceDeductionCoordinator`

The plugin (`Rgd\Inventory\Plugin\InventorySourceDeduction\SourceDeductionServicePlugin`) intercepts
`Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface::execute()` as an **around**
plugin, but it does no work itself — it immediately delegates to
`Rgd\Inventory\Model\SourceDeductionCoordinator::executeWithBatchTracking()`, which owns the actual
transaction lifecycle.

> **Note:** `SourceDeductionCoordinator` is a package-private orchestration class, deliberately **not**
> part of the `Api/` contracts (see ADR-1, Decision 3). It exists specifically to hold the transaction
> open across multiple items *and* MSI's own `proceed()` call — something `BatchDeductionServiceInterface::deduct()`
> cannot do on its own, since `deduct()`'s public contract is a self-contained, already-committed
> single-item operation for standalone callers.

**Flow (one shared transaction for the whole request):**
1. Plugin receives MSI's `SourceDeductionRequest` (items, sales event, source) and hands it straight to the coordinator.
2. Coordinator resolves `order_item_id` for **all** items in a single batched `OrderItemRepositoryInterface::getList()` call (not one query per item — see Performance history below), keyed by order id + SKU list.
3. Coordinator opens **one** database transaction (`beginTransaction()`).
4. For each item, the coordinator calls `BatchDeductionService::deductWithinTransaction()` — the non-transactional allocation/lock/ledger-write logic — reusing the same connection/transaction for every item.
5. Once all items have been deducted, the coordinator invokes the original `$proceed($sourceDeductionRequest)` **inside the still-open transaction**, letting MSI run its native `source_item` decrement and event dispatch.
6. Coordinator commits. Any exception at any step (order-item resolution, any item's allocation, or `proceed()` itself) rolls back the entire transaction — our batch ledger and MSI's `source_item` aggregate always commit or roll back together.

> **Important — corrected design, do not regress.** An earlier build had each item's batch deduction
> commit independently before `proceed()` ran. Security Testing flagged this HIGH: if `proceed()` (or a
> later item) then failed, the batch ledger was already committed while MSI's aggregate was not, leaving
> the two stores permanently out of sync with no rollback — unacceptable in a pharmacy context. This was
> fixed by introducing the coordinator and the single shared transaction described above. See
> [docs/adr/1-fefo-batch-inventory-architecture.md](../../../../../docs/adr/1-fefo-batch-inventory-architecture.md), Decision 3, for the full rationale. Anyone refactoring this plugin/coordinator
> must preserve the "`proceed()` runs inside our transaction" invariant.

**Concurrency:**
- Lock is `SELECT ... FOR UPDATE` with deterministic `ORDER BY` per SKU to prevent deadlock between two transactions deducting the *same* SKU.
- Stable lock acquisition order: `ORDER BY expiry_date IS NULL, expiry_date ASC, batch_id ASC` (the `batch_id ASC` tie-breaker is load-bearing, not cosmetic — remove it and concurrent checkouts can lock the same rows in different orders and deadlock instead of one blocking on the other).
- Residual risk (documented, accepted, not fixed): items *within* a single multi-item cart are locked in the order MSI supplied them, which is not guaranteed to be the same order across two different requests sharing overlapping SKUs (e.g. order A = [SKU-1, SKU-2], order B = [SKU-2, SKU-1]). InnoDB's deadlock detector resolves this by aborting one transaction, which then retries via Magento's normal checkout retry path. A single globally-ordered lock query across all items would close this gap but was judged a larger redesign than warranted for phase 1.

---

## Error Handling Strategy

All exceptions thrown are `LocalizedException` (or subclasses) with specific, actionable messages:

| Condition | Exception | Message |
|-----------|-----------|---------|
| No batches configured for SKU | `LocalizedException` | `No batch inventory configured for SKU "%1".` |
| All batches expired | `LocalizedException` | `No usable batch stock for SKU "%1": %2 unit(s) exist across %3 batch(es) but all are expired.` |
| Insufficient available stock | `LocalizedException` | `Insufficient batch stock for SKU "%1": requested %2, available %3.` |
| Concurrent exhaustion | `LocalizedException` | (Same as insufficient stock — caller can't distinguish, treated identically) |
| Batch has transaction history | `CouldNotDeleteException` | `Cannot delete batch "%1": it has recorded inventory transactions. Deactivate it instead.` |
| remaining_qty > received_qty on save | `CouldNotSaveException` | `Cannot save batch: remaining quantity cannot exceed received quantity.` |
| Unique key violation on save | `CouldNotSaveException` | `A batch with number "%1" already exists for SKU "%2" and source "%3".` |

---

## Concurrency & Locking

The FEFO selector uses database-level locking to prevent race conditions:

```sql
SELECT batch_id, batch_number, expiry_date, remaining_qty
FROM rgd_inventory_batch
WHERE sku = :sku 
  AND source_code = :sourceCode 
  AND is_active = 1
  AND remaining_qty > 0
  AND (expiry_date IS NULL OR expiry_date > CURDATE())
ORDER BY expiry_date IS NULL, expiry_date ASC, batch_id ASC
FOR UPDATE
```

**Deterministic ordering** (`batch_id ASC` as tie-breaker) ensures stable lock acquisition across concurrent transactions, preventing deadlock.

**Expiry cutoff:** A batch expiring today (`expiry_date = CURDATE()`) is treated as already expired and excluded from selection. Only strictly future expiry dates (or no expiry at all, treated as "expires last") are usable.

---

## Admin UI

Phase 1 ships a standard Magento UI Components grid + form (no CSV import, no custom grid JS) for
clinic staff to manage batch intake and corrections directly.

### Finding the Batch grid

**Menu path:** **Catalog > Inventory > Batch Inventory**
(the menu item is registered under `Magento_Catalog::catalog_inventory`, i.e. it sits alongside the
core "Products" and "Categories" items in the Catalog menu, not as a new top-level group).

Direct admin URL: `<admin>/rgd_inventory/batch/index`

**Who can see it:** access is controlled by two ACL resources, nested under **Catalog > Batch Inventory**
in the admin **System > Permissions > User Roles** resource tree (a separate place from the menu path
above — this is where an admin grants/revokes access per role):
- `Rgd_Inventory::batch` — required to open the grid at all (`Index` controller's `ADMIN_RESOURCE`)
- `Rgd_Inventory::batch_manage` — required to open the create/edit form or use Delete (`Edit`, `Save`,
  `Delete` controllers)

  > **Note:** `etc/acl.xml` also declares a third resource, `Rgd_Inventory::batch_view`, intended for a
  > future read-only staff role. No controller currently checks it — it is reserved/unused in phase 1,
  > not a bug, but don't expect assigning only `batch_view` to grant grid access today; use `batch` for that.

### Batch listing grid

Columns: ID, SKU, Batch Number, Expiry Date, Received Qty, Remaining Qty, Source, Status, Created At — all
sortable. Filters: SKU (text), Batch Number (text), Expiry Date (date range), Received Qty (numeric
range), Remaining Qty (numeric range), Source (select), Status (Active/Inactive).

Row actions: **Edit** and **Delete** (see delete-guard behavior below). There is no bulk/mass-action
delete in phase 1 — batches are deleted one at a time deliberately, so the delete-guard's per-row error
message is always seen rather than silently skipped in a bulk operation.

### Creating a new batch (intake)

1. From the grid, click **Add New Batch**.
2. Fill in SKU, Batch Number, Source (defaults to `default`), Expiry Date, and Received Qty.
3. Click **Save Batch**.

On create, the controller (`Rgd\Inventory\Controller\Adminhtml\Batch\Save`) sets `remaining_qty` equal
to the entered `received_qty` and writes a `rgd_inventory_batch_transaction` row with
`movement_type=intake`, `qty=+received_qty`, `reference="Manual intake"`. This means every batch's stock
— even stock that was never touched by a sale — has a traceable origin row in the ledger from the moment
it's created; there is no bare `INSERT` into the batch table that bypasses the audit trail.

> **Validation note:** the SKU field is a plain text input in phase 1 (no product autocomplete), but the
> repository's `save()` does **not** currently verify the SKU exists in the catalog before persisting the
> batch — a typo'd SKU will silently create a batch the FEFO selector will never match against any real
> order (since order items carry the real SKU). Double-check the SKU against the product grid before
> saving; this is a known gap, not by-design behavior worth relying on.

### Editing a batch, and how remaining-quantity edits become an audit adjustment

SKU, Batch Number, and Source are treated as the batch's identity (matching the unique key
`sku` + `batch_number` + `source_code`). The edit form currently renders these as regular (not
visually disabled) inputs, but **the Save controller silently discards any change to them on edit** —
`Save::prepareExistingBatch()` unsets `sku`, `batch_number`, and `source_code` from the posted data
before applying it, so editing those fields in the browser has no effect once you hit Save. To change a
batch's identity, delete it (if it has no transaction history) and recreate it, or deactivate it and
create a new batch under the corrected identity.

**Received Qty** is also effectively immutable on edit for the same reason — it's part of the posted
data, but nothing in the edit flow is expected to change it meaningfully after intake; treat it as a
historical record of what was received.

**Remaining Qty**, however, is genuinely editable on the edit form — this is the mechanism for manual
stock corrections (stock takes, damage write-offs, found stock). When you save an edit where
`remaining_qty` differs from its previous value, the Save controller computes the signed delta
(`new − old`) and writes a second `rgd_inventory_batch_transaction` row with `movement_type=adjustment`,
`qty=<signed delta>`, `reference="Manual adjustment"` — in addition to updating the batch row itself.
This is what keeps manual admin corrections traceable in the same ledger the FEFO deduction and intake
flows write to, rather than being an invisible `UPDATE` with no history. If `remaining_qty` is unchanged
on save, no adjustment row is written.

Server-side validation (in `BatchRepository::save()`) still enforces `0 <= remaining_qty <= received_qty`
and rejects a negative `received_qty`, regardless of what the client-side form validation already checks.

### Delete-guard behavior — why some Delete actions are refused

Clicking **Delete** on a grid row or the edit form's Delete button asks for confirmation, then posts to
`rgd_inventory/batch/delete`. The repository's `deleteById()` first checks whether any
`rgd_inventory_batch_transaction` rows reference that `batch_id`:

- **No transaction history** (e.g. a batch created by mistake and never used) — the batch row is deleted
  outright and a success message is shown.
- **Any transaction history exists** (intake, deduction, adjustment, or return rows) — the delete is
  **refused**. The controller catches `CouldNotDeleteException` and surfaces the message
  *"Cannot delete batch "%1": it has recorded inventory transactions. Deactivate it instead."* via the
  standard admin error-message banner. The batch row is left untouched.

This is a deliberate application-layer guard, not a database limitation — the underlying foreign key
(`rgd_inventory_batch_transaction.batch_id → rgd_inventory_batch.batch_id`) is actually configured
`ON DELETE CASCADE` as a defense-in-depth safety net against orphaned rows, but the repository is
expected to never let that cascade fire in normal operation, because doing so would silently destroy
audit history that exists specifically for pharmacy compliance/traceability. **The correct way to retire
a batch that has history is to deactivate it**, not delete it:

1. Open the batch's Edit form (or use the grid's Activate/Deactivate mass action).
2. Uncheck **Active** (`is_active`) and save — no adjustment row is written for this, since it doesn't
   change `remaining_qty`, only visibility to the FEFO selector.
3. A deactivated batch (`is_active = 0`) is excluded from `FefoBatchSelectorInterface::selectForDeduction()`
   and `BatchDeductionService`'s locked candidate query, so it will never be drawn from again, while its
   full transaction history remains intact and queryable.

## GraphQL API

A single read-only query, `rgdInventoryStock`, exposes FEFO batch stock for a SKU to headless/mobile
frontends — the same "what's currently sellable" data as `FefoBatchSelectorInterface::getAvailableBatches()`,
over GraphQL instead of REST. Unauthenticated, like standard product stock queries.

```graphql
{
  rgdInventoryStock(sku: "SKU-123") {
    sku
    available_qty
    batches {
      batch_number
      expiry_date
      available_qty
      received_at
    }
  }
}
```

- `available_qty` is the sum of `remaining_qty` across active, non-expired batches — a batch expiring
  today is already treated as expired, matching the deduction path's rule.
- `batches` is ordered earliest-expiry-first (FEFO order), with NULL-expiry batches last.
- An unknown or out-of-stock SKU returns `available_qty: 0, batches: []` rather than an error.
- A missing/empty `sku` argument throws a `graphql-input` category error.
- Optional `sourceCode` argument (defaults to `"default"`) scopes the query to an MSI source.

Schema: `etc/schema.graphqls`. Resolver: `Model/Resolver/InventoryStock.php`.

## Known limitations (Phase 1)

These are deliberate, documented scope cuts from the approved spec/ADR — not oversights — but anyone
building on top of this module should know about them up front:

- **Returns/restore is a stubbed no-op.** `BatchReturnServiceInterface::restore()` is implemented by
  `Rgd\Inventory\Model\BatchReturnService` and simply returns `[]` without touching the database. The
  contract (signature, docblock) is locked and designed so a phase 2 implementation is a drop-in — it's
  meant to reconstruct which batch(es) a quantity was originally deducted from via the
  `rgd_inventory_batch_transaction` ledger (matched by `order_item_id`, `movement_type=deduction`,
  walked oldest-first up to the returned qty) — but **no such logic exists yet**. Practically: creditmemos
  and order cancellations do **not** restore quantity to `rgd_inventory_batch.remaining_qty` or write a
  `return` ledger row in phase 1. MSI's own `source_item` quantity may still be restored natively by MSI
  itself outside this module's control — this module's batch-level ledger simply doesn't participate in
  that restoration yet. See ADR-1, Decision 4.
- **No CSV import for batch intake.** All batch creation goes through the admin form, one batch at a
  time. This is also *why* `expiry_date` is nullable at the database level even though the admin form
  requires it — the schema was deliberately left import-friendly so a future CSV importer (which might
  encounter rows with unknown expiry dates) doesn't require a schema migration. Do not tighten
  `expiry_date` to `NOT NULL` at the DB layer without accounting for this.
- **Single-source design (`source_code = 'default'`).** The schema and FEFO selector are fully
  source-code-aware (it's part of the unique key and every query), so multi-source deployments are not
  architecturally blocked — but phase 1 was built and tested against a single-source pharmacy deployment.
  The admin form's Source dropdown currently only offers `default`. Multi-source behavior (e.g. FEFO
  allocation per-source vs. across sources) has not been scenario-tested.
- **No SKU-exists validation on batch save.** See the intake note above — a mistyped SKU will silently
  create an orphaned batch.
- **No product-name column / no CSV export / no dedicated "expiring soon" dashboard.** The grid's Expiry
  Date range filter covers the spec's reporting requirement; anything beyond that (e.g. a widget) was
  explicitly out of scope for phase 1.

### Phase 2 (deferred, contracts already locked so this is additive work)

- Implement `BatchReturnServiceInterface::restore()` against the audit trail; wire it into the
  creditmemo/cancel flow.
- CSV batch import/export.
- Batch-level reporting/analytics dashboard.
- Revisit whether `expiry_date` should be hard-required at the DB layer once CSV import's behavior for
  unknown-expiry rows is decided.

---

## Configuration

### Module Dependencies (di.xml)

- `Magento_InventorySourceDeductionApi` — Core MSI interface
- `Magento_InventorySalesApi` — Sales event types
- `Magento_InventorySales` — Shipment/creditmemo factory
- `Magento_InventoryShipping` — Shipping deduction coordinator
- `Magento_Sales` — Order/order-item repository
- `Magento_InventoryCatalogApi` — Product references

### Module Load Order (module.xml)

Rgd_Inventory loads **after** all MSI modules to ensure availability of interfaces and factories during DI compilation.

### Plugin Registration (di.xml)

```xml
<type name="Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface">
    <plugin name="rgd_inventory_source_deduction" type="Rgd\Inventory\Plugin\InventorySourceDeduction\SourceDeductionServicePlugin" sortOrder="10"/>
</type>
```

### Admin Routes & Menu (etc/adminhtml/routes.xml, etc/adminhtml/menu.xml)

Routes:
- Admin URL route ID: `rgd_inventory`
- Grid: `/admin/rgd_inventory/batch/index`
- Create/Edit form: `/admin/rgd_inventory/batch/edit` (batch_id parameter optional)
- Save endpoint: `/admin/rgd_inventory/batch/save` (POST)
- Delete endpoint: `/admin/rgd_inventory/batch/delete` (POST)

Menu:
- Location: **Catalog > Inventory > Batch Inventory** (registered under `Magento_Catalog::catalog_inventory`)
- ACL: `Rgd_Inventory::batch` (grid view), `Rgd_Inventory::batch_manage` (create/edit/delete). A third
  resource, `Rgd_Inventory::batch_view`, is declared in `etc/acl.xml` for a future read-only role but is
  not currently checked by any controller.

---

## Installation & Verification

1. **Install module schema:**
   ```bash
   php bin/magento setup:upgrade
   ```

2. **Compile DI configuration:**
   ```bash
   php bin/magento setup:di:compile
   ```

3. **Verify plugin is loaded:**
   - Check generated interceptor files (if debugging)
   - Monitor logs during order placement/shipment

4. **Test batch deduction:**
   - Create a batch via `BatchRepositoryInterface::save()`
   - Place an order with matching SKU
   - Create shipment and verify:
     - Batch `remaining_qty` decremented
     - `rgd_inventory_batch_transaction` row written with `movement_type=deduction`

---

## Coding Standards

- `declare(strict_types=1)` on all PHP files
- Constructor DI only (no ObjectManager)
- Service contracts first (Api/ before Model/)
- Declarative schema (db_schema.xml, no InstallSchema)
- PSR-12 + Magento coding standard

---

## Support & Troubleshooting

### Schema not created
- Run `php bin/magento setup:upgrade` again
- Check `var/log/system.log` for database errors

### Plugin not intercepting
- Verify `di.xml` plugin type/sortOrder
- Run `php bin/magento setup:di:compile`
- Clear generated/ directory if persistent issues

### Insufficient stock errors
- Check batch `remaining_qty > 0` and `is_active = 1`
- Verify expiry_date is NULL or future-dated (not expired)
- Review transaction log for recent deductions

---

## Testing

**Shipped test coverage:** `Test/Unit/Model/` contains 40 passing unit tests across
`BatchDeductionServiceTest`, `BatchRepositoryTest`, `FefoBatchSelectorTest`, and
`SourceDeductionCoordinatorTest` — covering FEFO allocation ordering, the three distinct
insufficient-stock/expired/unconfigured error paths, save() validation, the delete-guard, the
coordinator's shared-transaction/rollback behavior (mocked connection), and the read-only
`getAvailableBatches()` path used by the GraphQL resolver (FEFO order, empty-stock, never throws).

In addition to the unit suite, this module went through a real-MySQL scenario-verification pass
(11/11 checks; see [docs/qa-scenario-verification-batch-based-inventory-management.md](../../../../../docs/qa-scenario-verification-batch-based-inventory-management.md) in the
main project) covering end-to-end deduction across multiple batches, expiry exclusion, the
transaction-atomicity fix, and the delete-guard against a live database — not just mocks.

`Test/Integration/` currently only holds a `.gitkeep` placeholder — no Magento integration-test-framework
tests (`Magento\TestFramework\TestCase\...` against a bootstrapped Magento app) exist yet. The real-DB
verification above was done via a standalone scenario script, not the integration test framework;
adding proper integration tests here (in particular a true concurrency test with two overlapping
transactions) is recommended future work, not something already covered.

---

## Version History

| Version | Date | Notes |
|---------|------|-------|
| 1.0.0 | 2026-07-09 | Phase 1 shipped (commit `c50dfb0`, branch `feature/fefo-batch-inventory`): FEFO deduction with pessimistic locking, append-only audit ledger, admin CRUD (grid + form) with intake/adjustment ledger writes and delete-guard, `BatchReturnServiceInterface` defined as a locked-but-stubbed phase 2 contract. Preceded by 6 rounds of bug-fixing (see [docs/progress-batch-based-inventory-management.md](../../../../../docs/progress-batch-based-inventory-management.md) in the main project) that corrected, among other things, a HIGH-severity transaction-atomicity bug (each item originally committed its own batch deduction independently of MSI's `proceed()` — fixed via `SourceDeductionCoordinator`'s shared transaction), the expiry cutoff operator (`>` not `>=`, so a batch expiring today is excluded), and `order_id` not being persisted to the ledger. |

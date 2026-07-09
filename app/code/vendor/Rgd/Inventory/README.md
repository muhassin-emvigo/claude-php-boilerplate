# Rgd_Inventory Module — FEFO Batch Inventory Management

## Overview

The `Rgd_Inventory` module implements batch-based inventory management with First Expiry, First Out (FEFO) ordering for the RGD Dental clinic. It allows tracking of multiple batches per SKU with individual expiry dates and quantities, automatically selecting the batch with the earliest expiry date when dispensing stock.

**Status:** Phase 1 — Core deduction logic and audit trail implemented. Phase 2 (return/restoration logic) deferred.

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
}
```

Returns an array of `BatchAllocationInterface` objects (immutable DTOs) ordered by earliest expiry first.

**Error Handling:**
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

**Behavior:**
1. Acquires `SELECT ... FOR UPDATE` lock on candidate batches
2. Computes FEFO allocation against locked rows
3. Updates `remaining_qty` for each allocated batch
4. Writes `movement_type=deduction` audit rows
5. Commits (or rolls back on error)

The plugin (SourceDeductionCoordinator) manages a shared transaction that wraps both batch deduction and MSI's original `proceed()` call, ensuring atomicity between the batch ledger and MSI's source_item decrement.

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

### `SourceDeductionServicePlugin`

Intercepts `Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface::execute()` to inject batch-level deduction tracking.

**Flow:**
1. Plugin receives MSI's `SourceDeductionRequest` (items, sales event, source)
2. Resolves `order_item_id` for each item via `OrderItemRepositoryInterface`
3. Delegates to `BatchDeductionService::deduct()` for each item
4. Calls original `proceed()` to execute MSI's native deduction
5. Returns control to caller

**Concurrency:**
- Each batch deduction opens/closes its own transaction (phase 1 pragmatic limitation)
- Lock is `SELECT ... FOR UPDATE` with deterministic `ORDER BY` to prevent deadlock
- Stable lock acquisition order: `ORDER BY expiry_date IS NULL, expiry_date ASC, batch_id ASC`

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

### Batch Listing Grid (`Catalog > Batch Inventory`)

Displays all batches with:
- **Columns:** ID, SKU, Batch Number, Expiry Date, Received Qty, Remaining Qty, Source, Status, Created At
- **Filters:** SKU (text), Batch Number (text), Expiry Date (date range), Remaining Qty (numeric range), Status (active/inactive)
- **Sorting:** All columns sortable
- **Bulk Actions:** Activate, Deactivate (hard delete intentionally not offered as bulk action per spec)
- **Row Actions:** Edit, Delete (delete disabled/hidden if batch has transaction history)

### Batch Create/Edit Form

Create or edit batch inventory with:
- **Fields:**
  - SKU (required, read-only on edit — identity field)
  - Batch Number (required, read-only on edit — identity field)
  - Source Code (required, read-only on edit, default = 'default')
  - Expiry Date (required, date picker)
  - Received Qty (required, positive number — immutable on edit)
  - Remaining Qty (required on edit only; on create, auto-set to Received Qty)
  - Status (Active checkbox, default checked)
- **On Create:** Intake transaction row written with `movement_type=intake`, `qty=received_qty`
- **On Edit:** If remaining_qty changed, adjustment transaction row written with signed delta

## Limitations & Future Work

### Phase 1 (Current)

- Deduction with batch tracking and FEFO ordering
- Audit trail recording all movements
- Admin CRUD for batch intake with form-driven transaction recording
- Hard-delete guard: batches with transaction history cannot be deleted (soft-deactivation is the removal path)

### Phase 2 (Deferred)

- `BatchReturnServiceInterface` implementation (creditmemo-driven restoration)
- CSV import/export of batches
- Batch-level reporting/analytics dashboard
- Hard expiry_date requirement at DB layer (currently optional in schema)

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
- Location: **Catalog > Batch Inventory**
- ACL: `Rgd_Inventory::batch` (view), `Rgd_Inventory::batch_manage` (edit/delete)

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

## Testing Strategy (Phase Next)

- **Unit tests:** Mock dependencies, test FEFO logic, error conditions
- **Integration tests:** Full flow from order to shipment with real DB
- **Concurrency tests:** Multiple simultaneous orders against same batch
- **Edge cases:** Partial allocations, expired batches, zero-qty batches

---

## Version History

| Version | Date | Notes |
|---------|------|-------|
| 1.0.0 | 2026-07-08 | Phase 1 release: deduction with FEFO ordering, audit trail, hard-delete guard |

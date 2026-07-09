## Technical spec — batch-based-inventory-management

**Module:** `Rgd_Inventory` (vendor `Rgd`)
**Source requirement:** `docs/requirements/2026-07-08-batch-based-inventory-management.md`
**Architecture baseline:** plugin on `SourceDeductionServiceInterface::execute()`, `SELECT ... FOR UPDATE` locking, two new tables (see progress doc, step 3, approved). This spec does not revisit those decisions; it defines contracts, schema, and error handling against them.
**Magento version (confirmed from vendor/):** 2.4.7-p8. MSI modules are core-vendored; no separate `magento/inventory` composer package needed as a dependency, only `require` on the specific MSI api/module packages.

---

### API contracts

All service contracts live under `Rgd/Inventory/Api` and `Rgd/Inventory/Api/Data`, per Magento service-contract convention. All are marked `@api`.

#### Data\BatchInterface

```php
namespace Rgd\Inventory\Api\Data;

interface BatchInterface
{
    const BATCH_ID = 'batch_id';
    const SKU = 'sku';
    const BATCH_NUMBER = 'batch_number';
    const EXPIRY_DATE = 'expiry_date';
    const RECEIVED_QTY = 'received_qty';
    const REMAINING_QTY = 'remaining_qty';
    const SOURCE_CODE = 'source_code';
    const IS_ACTIVE = 'is_active';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    public function getBatchId(): ?int;
    public function setBatchId(?int $batchId): self;

    public function getSku(): string;
    public function setSku(string $sku): self;

    public function getBatchNumber(): string;
    public function setBatchNumber(string $batchNumber): self;

    /** @return string|null ISO date (Y-m-d). Null = no expiry tracked (see Data Model note). */
    public function getExpiryDate(): ?string;
    public function setExpiryDate(?string $expiryDate): self;

    public function getReceivedQty(): float;
    public function setReceivedQty(float $qty): self;

    public function getRemainingQty(): float;
    public function setRemainingQty(float $qty): self;

    public function getSourceCode(): string;
    public function setSourceCode(string $sourceCode): self;

    public function isActive(): bool;
    public function setIsActive(bool $isActive): self;

    public function getCreatedAt(): ?string;
    public function setCreatedAt(string $createdAt): self;

    public function getUpdatedAt(): ?string;
    public function setUpdatedAt(string $updatedAt): self;
}
```

#### Data\BatchTransactionInterface

```php
namespace Rgd\Inventory\Api\Data;

interface BatchTransactionInterface
{
    const TRANSACTION_ID = 'transaction_id';
    const BATCH_ID = 'batch_id';
    const SKU = 'sku';
    const BATCH_NUMBER = 'batch_number';
    const EXPIRY_DATE = 'expiry_date';
    const MOVEMENT_TYPE = 'movement_type';
    const QTY = 'qty';
    const SALES_EVENT_TYPE = 'sales_event_type';
    const ORDER_ID = 'order_id';
    const ORDER_ITEM_ID = 'order_item_id';
    const REFERENCE = 'reference';
    const CREATED_AT = 'created_at';

    const MOVEMENT_DEDUCTION = 'deduction';
    const MOVEMENT_RETURN = 'return';
    const MOVEMENT_ADJUSTMENT = 'adjustment';
    const MOVEMENT_INTAKE = 'intake';

    public function getTransactionId(): ?int;
    public function setTransactionId(?int $id): self;

    public function getBatchId(): int;
    public function setBatchId(int $batchId): self;

    public function getSku(): string;
    public function setSku(string $sku): self;

    public function getBatchNumber(): string;
    public function setBatchNumber(string $batchNumber): self;

    public function getExpiryDate(): ?string;
    public function setExpiryDate(?string $expiryDate): self;

    /** One of the MOVEMENT_* constants. */
    public function getMovementType(): string;
    public function setMovementType(string $movementType): self;

    /** Signed: negative for deduction/adjustment-down, positive for return/intake/adjustment-up. */
    public function getQty(): float;
    public function setQty(float $qty): self;

    /** Raw value of SalesEventInterface::getType(), null for non-sales movements (intake/manual adjustment). */
    public function getSalesEventType(): ?string;
    public function setSalesEventType(?string $salesEventType): self;

    public function getOrderId(): ?int;
    public function setOrderId(?int $orderId): self;

    public function getOrderItemId(): ?int;
    public function setOrderItemId(?int $orderItemId): self;

    /** Free-text reference: creditmemo increment id, admin adjustment note, CSV batch id (future), etc. */
    public function getReference(): ?string;
    public function setReference(?string $reference): self;

    public function getCreatedAt(): ?string;
    public function setCreatedAt(string $createdAt): self;
}
```

#### Data\BatchAllocationInterface

DTO returned by the FEFO selector and by the deduction service; represents "this many units came from this batch."

```php
namespace Rgd\Inventory\Api\Data;

interface BatchAllocationInterface
{
    public function getBatchId(): int;
    public function getBatchNumber(): string;
    public function getExpiryDate(): ?string;
    public function getQty(): float;
}
```

Plain immutable DTO (constructor-populated via factory `data` array), no persistence, no setters — it is a value object, not an entity. Rationale: allocation results are ephemeral (computed during a locked transaction and consumed immediately by the caller and the audit-row writer); giving it setters/persistence would blur it with `BatchTransactionInterface`, which is the actual persisted record.

#### Api\BatchRepositoryInterface

```php
namespace Rgd\Inventory\Api;

use Rgd\Inventory\Api\Data\BatchInterface;
use Rgd\Inventory\Api\Data\BatchSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;

interface BatchRepositoryInterface
{
    /**
     * @throws CouldNotSaveException on unique-key violation (sku+batch_number+source_code)
     *         or if remaining_qty > received_qty
     */
    public function save(BatchInterface $batch): BatchInterface;

    /** @throws NoSuchEntityException */
    public function getById(int $batchId): BatchInterface;

    /** @throws NoSuchEntityException */
    public function getBySkuAndBatchNumber(string $sku, string $batchNumber, string $sourceCode = 'default'): BatchInterface;

    public function getList(SearchCriteriaInterface $searchCriteria): BatchSearchResultsInterface;

    /** @throws CouldNotDeleteException if the batch has transaction history (see Error Handling) */
    public function delete(BatchInterface $batch): bool;

    /** @throws NoSuchEntityException|CouldNotDeleteException */
    public function deleteById(int $batchId): bool;
}
```

`BatchSearchResultsInterface extends \Magento\Framework\Api\SearchResults` with typed `getItems(): BatchInterface[]` / `setItems(array $items)` — standard Magento search-results boilerplate, not elaborated further here.

#### Api\FefoBatchSelectorInterface

Read-only planner. Does **not** lock rows or mutate state — see `BatchDeductionServiceInterface` for the transactional operation. Exposed as its own contract because the admin "preview allocation" UI (out of scope for phase 1 build, but worth keeping decoupled) and future reporting can call it without paying for a write transaction.

```php
namespace Rgd\Inventory\Api;

use Rgd\Inventory\Api\Data\BatchAllocationInterface;
use Magento\Framework\Exception\LocalizedException;

interface FefoBatchSelectorInterface
{
    /**
     * Select batches to cover $requestedQty for $sku under FEFO ordering
     * (earliest expiry_date first; NULL-expiry batches ordered last — see Data Model note),
     * restricted to active, non-expired, remaining_qty > 0 batches.
     *
     * Does not lock or mutate. Caller is responsible for locking if the result
     * will be used to authorize a physical deduction (see BatchDeductionServiceInterface).
     *
     * @return BatchAllocationInterface[] Ordered list; sum(qty) === $requestedQty on success.
     * @throws LocalizedException RGD_INV_INSUFFICIENT_STOCK if sum of available batches < $requestedQty
     *         (includes the case where all remaining batches are expired — see Error Handling).
     */
    public function selectForDeduction(string $sku, float $requestedQty, string $sourceCode = 'default'): array;
}
```

#### Api\BatchDeductionServiceInterface

Owns the lock/transaction lifecycle. This is the method the `SourceDeductionServiceInterface` plugin calls once per `ItemToDeductInterface` (per SKU) inside the intercepted request.

```php
namespace Rgd\Inventory\Api;

use Rgd\Inventory\Api\Data\BatchAllocationInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\Framework\Exception\LocalizedException;

interface BatchDeductionServiceInterface
{
    /**
     * Atomically select FEFO batches and deduct $qty for $sku, writing audit rows.
     *
     * Transaction boundary (owned by this method):
     *   begin -> SELECT ... FOR UPDATE candidate batch rows for (sku, sourceCode)
     *         -> compute FEFO allocation against locked rows
     *         -> UPDATE remaining_qty per allocated batch
     *         -> INSERT rgd_inventory_batch_transaction row(s), movement_type=deduction, qty negative
     *         -> commit
     *   Caller (the plugin) invokes MSI's original proceed() inside this same transaction
     *   scope (see Service Boundaries) so the MSI source_item aggregate and our ledger
     *   commit or roll back together.
     *
     * @param string $sku
     * @param float $qty Positive requested quantity.
     * @param SalesEventInterface $salesEvent Disambiguates deduction (shipment/invoice) vs
     *        refund-driven re-deduction; type is persisted verbatim to sales_event_type.
     * @param int|null $orderItemId Resolved by the caller (plugin) via order id + sku lookup
     *        (see Service Boundaries — Order Item Resolution). Null only for non-order-bound
     *        callers, none exist in phase 1; reserved for future manual/API deduction.
     * @param string $sourceCode
     * @return BatchAllocationInterface[] What was actually allocated, for logging/telemetry.
     * @throws LocalizedException RGD_INV_INSUFFICIENT_STOCK — see Error Handling.
     * @throws \Magento\Framework\Exception\CouldNotSaveException on ledger write failure
     *         (transaction is rolled back before this is thrown).
     */
    public function deduct(
        string $sku,
        float $qty,
        SalesEventInterface $salesEvent,
        ?int $orderItemId,
        string $sourceCode = 'default'
    ): array;
}
```

#### Api\BatchReturnServiceInterface (phase 2 contract — defined now, not implemented)

```php
namespace Rgd\Inventory\Api;

use Rgd\Inventory\Api\Data\BatchAllocationInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\Framework\Exception\LocalizedException;

interface BatchReturnServiceInterface
{
    /**
     * Restore $qty for $sku back to the batch(es) it was originally deducted from,
     * using rgd_inventory_batch_transaction as the source of truth (matched by
     * order_item_id, movement_type=deduction, walked oldest-first up to $qty).
     *
     * Phase 1: no concrete implementation is wired into the SourceDeductionService
     * plugin for EVENT_ORDER_CANCELED / return flows beyond what MSI already does
     * natively at the source_item level — our ledger is NOT restored automatically
     * in phase 1. Calling this in phase 1 either throws NotImplementedException or,
     * if time allows, performs a straightforward reverse-of-audit-trail restore
     * (see Open Questions). Either behavior is acceptable for phase 1 sign-off;
     * Build must pick one and document which, but the interface signature below
     * is fixed regardless so phase 2 is a drop-in.
     *
     * @param string $sku
     * @param float $qty
     * @param SalesEventInterface $salesEvent Expected type: EVENT_CREDITMEMO_CREATED for
     *        refund-driven restores.
     * @param int|null $orderItemId Required (non-null) for phase-2 traceable restore;
     *        phase 1 stub may accept null.
     * @param string $sourceCode
     * @return BatchAllocationInterface[] What was restored to which batch(es). Phase 1
     *         stub returns [].
     * @throws LocalizedException
     */
    public function restore(
        string $sku,
        float $qty,
        SalesEventInterface $salesEvent,
        ?int $orderItemId,
        string $sourceCode = 'default'
    ): array;
}
```

---

### Data model

Confirming the architecture doc's schema with the following refinements. Flagged deltas are called out explicitly; everything else matches as given.

#### `rgd_inventory_batch`

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `batch_id` | int unsigned | no | auto_increment | PK |
| `sku` | varchar(64) | no | — | matches `catalog_product_entity.sku` length |
| `batch_number` | varchar(64) | no | — | |
| `expiry_date` | date | **yes** | null | see delta 1 below |
| `received_qty` | decimal(12,4) | no | — | intake amount, immutable audit reference |
| `remaining_qty` | decimal(12,4) unsigned | no | — | authoritative ledger value |
| `source_code` | varchar(255) | no | `'default'` | matches MSI `source_code` column width |
| `is_active` | smallint | no | `1` | soft-disable without delete |
| `created_at` | timestamp | no | CURRENT_TIMESTAMP | |
| `updated_at` | timestamp | no | CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | |

Keys:
- PRIMARY (`batch_id`)
- UNIQUE `RGD_INVENTORY_BATCH_SKU_BATCH_NUMBER_SOURCE_CODE` (`sku`, `batch_number`, `source_code`)
- INDEX `RGD_INVENTORY_BATCH_SKU_EXPIRY_DATE_REMAINING_QTY` (`sku`, `expiry_date`, `remaining_qty`) — covers the FEFO selector's hot query
- INDEX `RGD_INVENTORY_BATCH_EXPIRY_DATE` (`expiry_date`) — supports expiry-range admin grid filter and future expiry reports
- INDEX `RGD_INVENTORY_BATCH_BATCH_NUMBER` (`batch_number`) — supports admin grid filter by batch number across SKUs

**Delta 1 — `expiry_date` must be nullable, with a documented behavioral consequence.** The requirement doc says "each batch has its own... Expiry Date" implying it's always present, and the admin form should indeed treat it as required for phase-1 CRUD intake (enforced at the form/validation layer, not the DB layer). The DB-level nullability is a deliberate looseness for two reasons: (a) declarative schema on a `date` column with `NOT NULL` and no default forces every legacy/backfilled row to have a real date, which is fine for admin-entered data but becomes a foot-gun if a future CSV import (phase 2, explicitly out of scope now but the schema should not have to change to support it) has rows with unknown expiry; (b) the FEFO selector needs defined behavior for "no expiry" rather than undefined behavior. **Resolved ordering:** NULL-expiry batches are treated as "expires last" (sort NULL after all dated batches, i.e. `ORDER BY expiry_date IS NULL, expiry_date ASC`), never excluded outright — an unknown expiry is not the same as "known expired." If Product/Design decide phase-1 admin intake should hard-require expiry_date at the DB layer too, that's a one-line form validation change, not a schema change — flagging for Design/Build rather than blocking this spec on it.

**Delta 2 — unique key naming.** Declarative schema requires explicit, deterministic constraint/index referenceIds (not auto-generated) so upgrades are stable across environments. Named all keys explicitly above; Build must use these exact referenceIds in `db_schema.xml` so `db_schema_whitelist.json` generation is reproducible.

No other deltas — column set, types, and the two-index-plus-unique design match the architecture doc.

#### `rgd_inventory_batch_transaction`

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `transaction_id` | int unsigned | no | auto_increment | PK |
| `batch_id` | int unsigned | no | — | FK to `rgd_inventory_batch.batch_id` |
| `sku` | varchar(64) | no | — | denormalized snapshot |
| `batch_number` | varchar(64) | no | — | denormalized snapshot |
| `expiry_date` | date | yes | null | denormalized snapshot at time of movement |
| `movement_type` | varchar(32) | no | — | enum-like: `deduction`\|`return`\|`adjustment`\|`intake` |
| `qty` | decimal(12,4) | no | — | **signed** (delta 3) |
| `sales_event_type` | varchar(32) | yes | null | raw `SalesEventInterface::getType()` value, null for `intake`/`adjustment` |
| `order_id` | int unsigned | yes | null | |
| `order_item_id` | int unsigned | yes | null | key for split-batch traceability |
| `reference` | varchar(64) | yes | null | |
| `created_at` | timestamp | no | CURRENT_TIMESTAMP | append-only, no `updated_at` |

Keys:
- PRIMARY (`transaction_id`)
- FOREIGN KEY `RGD_INV_BATCH_TXN_BATCH_ID_RGD_INV_BATCH_BATCH_ID` (`batch_id`) REFERENCES `rgd_inventory_batch` (`batch_id`) ON DELETE CASCADE
- INDEX `RGD_INV_BATCH_TXN_SKU_CREATED_AT` (`sku`, `created_at`) — traceability/reporting by SKU over time
- INDEX `RGD_INV_BATCH_TXN_ORDER_ITEM_ID` (`order_item_id`) — split-batch lookup per order line
- INDEX `RGD_INV_BATCH_TXN_BATCH_ID` (`batch_id`) — covers FK, also used for "transactions for this batch" admin drill-down

**Delta 3 — flagging, not changing: `qty` sign convention plus `ON DELETE CASCADE` is a real tension worth Product/Build awareness.** The architecture doc specifies `qty decimal(12,4) signed` and `FK ... ON DELETE CASCADE` in the same breath. Signed qty (negative for deduction) is correct and kept as-is — it lets a single `SUM(qty)` reconstruct net movement per batch without a `CASE` on `movement_type`, and is the more standard ledger pattern. But `ON DELETE CASCADE` on an **audit trail** table is a footgun: deleting a batch row (via `BatchRepositoryInterface::delete()`) would silently destroy the transaction history that exists specifically for traceability/compliance in a pharmacy context. Recommendation, reflected in Error Handling below: **application-layer guard, not a schema change.** Keep `ON DELETE CASCADE` in the schema (per architecture doc, and it's the right safety net against orphaned rows if a batch is ever deleted through some path that bypasses the repository), but `BatchRepositoryInterface::delete()`/`deleteById()` must refuse to delete a batch that has any transaction history, throwing `CouldNotDeleteException`, and the admin UI must offer "deactivate" (`is_active=0`) as the actual day-to-day removal action rather than hard delete. This is not a schema disagreement, just making sure Build doesn't wire the grid's delete button straight to `deleteById()` without the guard.

No other deltas from the architecture doc for this table.

#### Declarative schema notes for Build

- Both tables via `db_schema.xml`, `resource="default"`, InnoDB.
- `remaining_qty unsigned` at the DB level is a defense-in-depth constraint; the application-layer lock-and-decrement logic in `BatchDeductionServiceInterface::deduct()` must never even attempt to write a negative value (validated pre-update against the locked row), so the DB constraint should never actually fire in normal operation — treat a DB-level violation here as a bug, not an expected error path.
- `db_schema_whitelist.json` must be generated (`bin/magento setup:db-declaration:generate-whitelist`) as part of the Build stage before this ships; noting it here so it isn't missed.

---

### Service boundaries

```
Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface::execute()
        │
        ▼ (around plugin)
Rgd\Inventory\Plugin\Model\SourceDeductionServicePlugin::aroundExecute()
        │
        │  1. Read $sourceDeductionRequest->getItems() (ItemToDeductInterface[]: sku, qty only)
        │  2. Read $sourceDeductionRequest->getSalesEvent() → getType(), getObjectId() (= order entity id, string)
        │  3. Read $sourceDeductionRequest->getSourceCode()
        │  4. For each item: resolve order_item_id (see "Order Item Resolution" below)
        │  5. Delegate to Rgd\Inventory\Model\SourceDeductionCoordinator (see "Transaction Ownership"
        │     below), passing the resolved items + a closure over $proceed/$sourceDeductionRequest.
        │     The coordinator, NOT deduct() itself, owns begin/commit/rollback:
        │       a. begin transaction
        │       b. for each item: lock+allocate FEFO batches, update remaining_qty, insert ledger rows
        │          (this is the non-transactional allocation logic also reused by
        │          BatchDeductionServiceInterface::deduct() for non-plugin callers)
        │       c. invoke $proceed($sourceDeductionRequest) inside the still-open transaction
        │          — lets MSI's native SourceDeductionService run its SourceItem decrement + event dispatch
        │       d. commit
        │  6. Any exception at any step (a-d) → rollback, exception propagates (no swallowing).
        │
        ▼
Magento\InventorySourceDeductionApi\Model\SourceDeductionService::execute() [original]
        → decrements inventory_source_item, dispatches inventory reservation/place events as normal
```

**Transaction ownership.** `BatchDeductionServiceInterface::deduct()` owns the transaction lifecycle per the architecture doc ("owns the lock/transaction lifecycle"). Because the plugin must call `$proceed()` *inside* that same transaction (so our ledger write and MSI's `source_item` decrement commit atomically), the practical shape is: the plugin begins by calling into a coordinator that (a) starts the transaction, (b) does the lock+allocate+ledger-write, (c) calls `$proceed()` passing control back to MSI within the open transaction, (d) commits. This means `deduct()` cannot be a simple "call it, get a result, then separately call proceed()" — the plugin needs a way to pass `$proceed` (or a callable wrapping it) through to the point where the transaction is still open. Two implementation shapes satisfy the contract as specified without changing the public interface:
  - (a) `deduct()` internally uses `ResourceConnection::getConnection()->beginTransaction()/commit()/rollBack()` and the plugin calls `deduct()` first (closing its transaction), then calls `$proceed()` outside it, accepting a small window where our ledger is committed but MSI's decrement hasn't happened yet; **or**
  - (b) the plugin wraps `$proceed` in a closure and the transaction is opened one level up (in the plugin or a thin coordinator class the plugin delegates to), with `deduct()`'s allocation logic exposed as a non-transactional inner method plus a transactional outer method used only by the plugin's coordinator.

This spec requires shape (b) — true atomicity between our ledger and MSI's decrement is a hard requirement per the architecture doc's transaction description ("commit ... rollback on any failure" wrapping both), not just our own write. Build should introduce a package-private (not part of the `Api/` contracts) coordinator, e.g. `Rgd\Inventory\Model\SourceDeductionCoordinator`, that the plugin calls, which is the thing that actually opens the transaction, calls the non-transactional allocation logic, calls `$proceed()`, and commits/rolls back. `BatchDeductionServiceInterface::deduct()` remains the externally-callable atomic contract (for callers other than this plugin, e.g. future admin manual-adjustment tooling) and internally reuses the same non-transactional allocation logic. This is an implementation-shape clarification, not a contract change — flagging it here because "owns the lock/transaction lifecycle" is ambiguous about the proceed()-inside-transaction requirement until you draw the sequence out, and Build should not have to rediscover this.

**Order Item Resolution.** `ItemToDeductInterface` (MSI core) exposes only `sku` and `qty` — it does not carry `order_item_id`, confirmed by reading the interface and its factories (`SourceDeductionRequestFromShipmentFactory`, `GetSourceDeductionRequestFromSourceSelection`) in `vendor/magento/module-inventory-shipping` and `vendor/magento/module-inventory-sales`. Both shipment-driven and creditmemo-driven requests populate `SalesEvent::getObjectId()` with the order's entity id (as a string) and `getObjectType()` as `SalesEventInterface::OBJECT_TYPE_ORDER`. The plugin must resolve `order_item_id` by:

```
Magento\Sales\Api\OrderItemRepositoryInterface::getList(
    SearchCriteria filtering: order_id = (int) $salesEvent->getObjectId() AND sku = $item->getSku()
)
```

taking the single matching `order_item_id`. Edge cases to handle (Build's responsibility, documented here so the error path isn't invented ad hoc):
- **Zero matches** (e.g. `getObjectType()` is not `OBJECT_TYPE_ORDER`, or a non-order-bound deduction call in some future channel): pass `orderItemId = null` through to `deduct()`. Contract explicitly allows null.
- **Multiple matches** (bundle/configurable child items sharing a SKU is not possible in Magento — SKUs are unique per order item — but a product with the same SKU appearing as both a simple line and inside a bundle in the same order is not possible either since bundle children get their own order item rows with their own SKU): in practice this should not occur; if it does, treat it as a data integrity condition and pass the first match, logged as a warning (not a thrown exception — must not block the sale).

**Concurrency / locking (confirming, not redesigning).** Within `deduct()`'s allocation step:
```sql
SELECT batch_id, batch_number, expiry_date, remaining_qty
FROM rgd_inventory_batch
WHERE sku = :sku AND source_code = :sourceCode AND is_active = 1
  AND remaining_qty > 0
  AND (expiry_date IS NULL OR expiry_date > CURDATE())
ORDER BY expiry_date IS NULL, expiry_date ASC, batch_id ASC
FOR UPDATE
```
`batch_id ASC` as a secondary sort is a tie-breaker for same-expiry-date batches, ensuring deterministic allocation order (oldest-created-first within a shared expiry date) and, more importantly, a **stable lock acquisition order across concurrent transactions** — without a deterministic ORDER BY, two concurrent checkouts could lock the same set of rows in different orders and deadlock instead of one blocking on the other.

**Module load order** (`module.xml` sequence), confirming the architecture doc's list and adding the two dependencies the resolution step above introduces:
```xml
<sequence>
    <module name="Magento_InventorySourceDeductionApi"/>
    <module name="Magento_InventorySalesApi"/>
    <module name="Magento_InventorySales"/>
    <module name="Magento_InventoryShipping"/>
    <module name="Magento_Sales"/>
    <module name="Magento_InventoryCatalogApi"/>
</sequence>
```
`Magento_Sales` is already listed and covers `OrderItemRepositoryInterface` (it lives in `Magento\Sales\Api`, same module) — no addition needed there; confirmed no new module dependency is introduced by the Order Item Resolution design above.

**Plugin scope.** `di.xml` plugin definition targets `Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface` (the interface, not the concrete `SourceDeductionService`), `sortOrder` unspecified (only plugin on this interface expected; no ordering conflict anticipated, but Build should check for third-party plugins on this interface if any other modules are later installed).

---

### Error handling strategy

All thrown exceptions are `\Magento\Framework\Exception\LocalizedException` (or a named subclass where noted), each carrying a distinct message intended to be both customer/order-log-safe and specific enough for admin diagnosis. None of these are caught-and-swallowed anywhere in the deduction path — insufficient stock must fail the shipment/invoice operation, not silently under-ship.

| Condition | Thrown from | Exception | Message |
|---|---|---|---|
| Sum of active, non-expired, in-stock batches for SKU < requested qty | `FefoBatchSelectorInterface::selectForDeduction()` | `LocalizedException` | `Insufficient batch stock for SKU "%1": requested %2, available %3.` (available = sum actually found, so admin can see the shortfall size) |
| All existing batches for SKU are expired or expiring today (i.e. batches exist but none satisfy the `expiry_date > CURDATE()` filter — a batch expiring today is treated as already expired — remaining_qty otherwise > 0) | `FefoBatchSelectorInterface::selectForDeduction()` | `LocalizedException` | `No usable batch stock for SKU "%1": %2 unit(s) exist across %3 batch(es) but all are expired.` — distinct message from generic insufficient-stock so admins immediately know it's an expiry problem, not a receiving problem |
| No batch rows exist at all for SKU (not yet onboarded into batch tracking) | `FefoBatchSelectorInterface::selectForDeduction()` | `LocalizedException` | `No batch inventory configured for SKU "%1".` — distinct from both above; this is a setup/data gap, not a stock or expiry problem, and should point admins at "go create a batch," not "go check expiry dates" |
| Concurrent exhaustion: locked-row allocation still comes up short because a concurrent transaction consumed the stock between the (hypothetical) non-locking preview and the locking allocation, OR simply because two simultaneous orders both raced for the last units and this one lost | `BatchDeductionServiceInterface::deduct()` (surfaces from the allocation step running against freshly-locked rows, not from the earlier non-locking selector) | `LocalizedException` | Same message/shape as "Insufficient batch stock" above — from the caller's/customer's perspective concurrent exhaustion and genuine insufficient stock are indistinguishable and should be handled identically (fail the operation, roll back, standard Magento "could not place order" / "could not create shipment" surfacing). Internally this spec still calls it out as a distinct row in this table because the two must be *tested* as distinct scenarios (see acceptance criteria below) even though they share one exception class and message template. |
| `save()` on a batch whose `remaining_qty > received_qty` (data integrity guard, e.g. admin form manual override), or unique-key violation on save | `BatchRepositoryInterface::save()` | `CouldNotSaveException` | `Cannot save batch: remaining quantity cannot exceed received quantity.` / `A batch with number "%1" already exists for SKU "%2" and source "%3".` |
| `delete()`/`deleteById()` on a batch with existing `rgd_inventory_batch_transaction` rows | `BatchRepositoryInterface::delete()` | `CouldNotDeleteException` | `Cannot delete batch "%1": it has recorded inventory transactions. Deactivate it instead.` (see Data Model delta 3 — this is the application-layer guard against the audit-trail footgun) |
| `getById()` / `getBySkuAndBatchNumber()` with no match | `BatchRepositoryInterface` | `NoSuchEntityException` | Standard Magento `NoSuchEntityException::singleField()` / `::doubleField()` usage |
| Ledger write fails after successful allocation (DB error, deadlock victim, etc.) mid-transaction | `BatchDeductionServiceInterface::deduct()` | `CouldNotSaveException` | `Unable to record batch deduction for SKU "%1".` — thrown only after rollback has already occurred; caller sees a clean failure, no partial state |

**Why three distinct insufficient-stock-family messages instead of one generic one.** The requirement doc's acceptance criteria explicitly calls out "Expired batches must not be used for dispensing" as a named behavior, and this is a pharmacy context where "we have stock but it's expired" vs. "we genuinely have no stock" vs. "nobody set up batches for this SKU yet" are operationally different problems an admin needs to triage differently (reorder vs. write off vs. onboard the SKU). Collapsing them into one generic `LocalizedException` message would satisfy the interface contract but not the spirit of the acceptance criteria around traceability and diagnosability. All three remain the same exception *class* (`LocalizedException`) so callers that only care about "did this fail" don't need to catch three types — only the message differs.

**What is explicitly not a thrown-exception path.** A batch reaching `remaining_qty = 0` is normal operation, not an error — the selector simply excludes it (`remaining_qty > 0` filter) and moves to the next FEFO candidate. Only exhaustion of *all* candidates is an error. Per the requirement doc's "done" criteria, product-level in-stock/out-of-stock status is a separate concern (MSI's existing salable-quantity aggregation across the SKU's sources already reflects `SUM(remaining_qty)` indirectly once MSI's own `source_item.quantity` is kept in sync via the same-transaction `proceed()` call) — this spec does not introduce a new out-of-stock signal; it relies on MSI's native salability calculation continuing to work because `source_item.quantity` and our ledger move together.

---

### Threat model

Not applicable — `flow_type=backend-feature`, not `security-patch`. Section omitted per output format (mandatory only for security-patch).

---

### Admin UI component plan

Phase 1 scope per architecture doc: **admin grid CRUD only**, no CSV import. Standard Magento UI Components (`Magento_Ui`) grid + form, consistent with core admin grids (e.g. `Magento_InventoryApi`'s source/stock grids) for look-and-feel and keyboard/ARIA behavior consistency — no custom grid JS.

#### Menu / ACL
- New admin menu item under **Catalog** (or a new top-level **Inventory Batches** group if Design prefers — Design stage to confirm placement; not an Eng decision) → "Batch Inventory"
- ACL resource: `Rgd_Inventory::batch` (and `Rgd_Inventory::batch_manage` for save/delete actions if Design wants granular view-vs-edit permissions; single resource is sufficient for phase 1 unless Design flags a need for read-only staff roles)

#### Grid: `rgd_inventory_batch_listing`

Data source: UI Component grid backed by a `Rgd\Inventory\Model\ResourceModel\Batch\Grid\Collection` (or a data provider joining `rgd_inventory_batch` directly — no join needed against transaction table for the listing itself).

**Columns:**

| Column | Source | Type | Notes |
|---|---|---|---|
| ID | `batch_id` | text | hidden by default (standard Magento pattern), sortable |
| SKU | `sku` | text | sortable, filterable (text) |
| Product Name | joined from `catalog_product_entity`/`catalog_product_entity_varchar` (name attr) via SKU | text | convenience column, not stored on this table; read-only display, not filterable in phase 1 unless trivial — flagging as nice-to-have, not blocking |
| Batch Number | `batch_number` | text | sortable, filterable (text) |
| Expiry Date | `expiry_date` | date | sortable, filterable (date range — see Filters below); render "—" for null |
| Received Qty | `received_qty` | text (numeric, 4-decimal formatted) | sortable |
| Remaining Qty | `remaining_qty` | text (numeric, 4-decimal formatted) | sortable, filterable (numeric range — see Filters below); visually flag rows where `remaining_qty = 0` (grayed/muted row, standard Magento "disabled row" styling) |
| Source | `source_code` | text | filterable (select, populated from configured sources) — most single-source pharmacy deployments will only ever show `default`, but the column earns its place because `source_code` is part of the uniqueness key |
| Status | `is_active` | select (Active/Inactive) | filterable (select), editable inline via mass-action (Activate/Deactivate) consistent with core grid patterns |
| Created At | `created_at` | date | sortable |
| Actions | — | actions column | Edit, Deactivate (soft — see Error Handling delta 3), Delete (hard — disabled/hidden if the batch has transaction history; the edit form and/or grid action must call `BatchRepositoryInterface::delete()` and surface its `CouldNotDeleteException` message via the standard admin error-message mechanism if the user forces it through a direct action) |

**Filters** (per acceptance criteria: "Reports should support filtering by SKU, Batch Number, Expiry Date, and remaining quantity"):
- SKU — text, `LIKE %value%` (standard Magento text filter behavior)
- Batch Number — text, `LIKE %value%`
- Expiry Date — range (`expiry_date_from` / `expiry_date_to`), standard Magento `dateRange` filter component
- Remaining Quantity — range (`remaining_qty_from` / `remaining_qty_to`), standard Magento numeric range filter (two `input` type filters bound to the same field with `_from`/`_to` suffixes, same pattern as core price-range filters)
- Status (Active/Inactive) — select, in addition to the four required filters, since `is_active` is part of the schema and needs to be filterable for usability (batches deactivated instead of deleted per the delete-guard would otherwise clutter the default view — default grid state should filter `is_active=1`)

Mass actions: Activate, Deactivate (bulk `is_active` toggle) — Delete intentionally **not** offered as a mass action in phase 1 (see delete-guard discussion; bulk-deleting across possibly-audited batches is exactly the footgun to avoid, and a mass action bypasses the natural "you'll notice the per-row error" friction of doing it one at a time).

#### Form: `rgd_inventory_batch_form`

Single-section form (no need for tabs at this field count), backed by `Rgd\Inventory\Model\DataProvider\BatchDataProvider` extending `Magento\Ui\DataProvider\AbstractDataProvider`, saving through `BatchRepositoryInterface::save()`.

**Fields:**

| Field | Input | Validation | Notes |
|---|---|---|---|
| SKU | text, with a product autocomplete/suggest UI component if feasible (consistent with core "Add Products" pickers), plain text input acceptable as phase-1 fallback | required; must match an existing `catalog_product_entity.sku` — validated server-side against `ProductRepositoryInterface::get()` on save, not just client-side, since a typo'd SKU would silently create an orphaned batch never selected by the FEFO plugin (SKUs there come from real order items) | disabled/read-only on edit (SKU is part of the identity tuple; changing it should be a delete+recreate, not an in-place edit, to avoid orphaning transaction history under a mismatched SKU) |
| Batch Number | text | required, max 64 chars | disabled/read-only on edit, same rationale as SKU — identity tuple field |
| Source | select, populated from `Magento\InventoryApi\Api\SourceRepositoryInterface` list | required, default `default` | disabled/read-only on edit, same rationale |
| Expiry Date | date picker | required in phase-1 form validation (per Data Model delta 1 — DB allows null for future-proofing, but admin-entered batches should always have a real date; Design/Product can override this to optional if they want to support "unknown expiry" intake, which is a one-line validation change, not a schema change) | must be a valid calendar date; no future-date upper bound enforced (a batch can legitimately be received with a distant future expiry) |
| Received Qty | text (numeric, 4 decimals) | required, > 0 | on create, `remaining_qty` is set equal to `received_qty` (this is the intake movement — Build should also write a `rgd_inventory_batch_transaction` row with `movement_type=intake`, `qty=+received_qty` at this point, consuming the same repository/service path rather than a bare INSERT, to keep the audit trail complete from day one) |
| Remaining Qty | text (numeric, 4 decimals) | on edit only (hidden/not present on create — derived from received qty as above); required, `0 <= remaining_qty <= received_qty`; editable on the form phase-1 for manual corrections (stock takes, damage write-offs), which must write a `movement_type=adjustment` transaction row with `qty` = signed delta (new − old), not a bare UPDATE — this is the mechanism by which manual adjustments stay traceable | server-side validated against the current `received_qty` on save |
| Status (Active) | toggle/checkbox | default checked (active) | maps to `is_active` |

**Save flow requirement:** the form's save action must not directly UPDATE `rgd_inventory_batch.remaining_qty` on edit without also inserting the corresponding `adjustment` transaction row — this should go through a small application-service method (not itself one of the `Api/` contracts, since it's admin-UI-specific orchestration: "diff old vs new remaining_qty, call repository save, write adjustment row if delta != 0"), otherwise manual admin corrections become an untraceable gap in the ledger that undermines the traceability requirement from the acceptance criteria.

**Delete confirmation:** standard Magento "Are you sure?" modal; on server-side rejection (`CouldNotDeleteException` per the guard), the message from Error Handling surfaces via the standard admin messages block — no special UI handling needed beyond what core grid delete actions already do.

#### Out of scope for phase 1 (explicitly, per architecture doc)
- CSV import/export of batches
- Any UI for `BatchReturnServiceInterface` (no restore UI; restores, if implemented at all in phase 1, are system-driven off the creditmemo flow, not admin-triggered)
- Batch-level reporting/analytics beyond the grid's own filters (e.g. a dedicated "expiring soon" dashboard widget) — the grid's Expiry Date range filter covers the acceptance criteria's reporting requirement; anything beyond that is a new feature request, not implied by this spec

---

### Open questions

1. **`BatchReturnServiceInterface` phase-1 behavior.** Architecture doc says "define interface now, no implementation required yet... return empty/not-implemented is fine... or a v1 implementation using the audit trail if time allows." This spec fixes the signature but leaves the choice between throwing `NotImplementedException`-style rejection vs. a best-effort reverse-of-ledger implementation to Build's discretion/time budget. Recommend Build defaults to the stub (return `[]`, no-op) unless there's confirmed time, since a half-correct restore implementation (e.g. one that doesn't handle partial-quantity creditmemos correctly) is worse than an honest no-op for a phase-1 ship. Needs a call from whoever reviews Build's output, not from this spec.
2. **Admin menu placement** (under Catalog vs. a new top-level group) — left to Design stage as noted in the UI plan.
3. **Whether `expiry_date` should be hard-required at the DB layer** (currently: nullable column, required-by-form-validation only) — this spec's recommendation is to leave the DB permissive per Data Model delta 1; flagging for Product sign-off since it's a one-line validation change either way and not worth blocking on.
4. **Product Name column on the grid** requires a join back to `catalog_product_entity` — confirmed feasible but adds a dependency Build should be aware of when writing the grid's collection/data-provider; not a blocker, just noting the extra join isn't "free."

---

Spec file: `docs/spec-batch-based-inventory-management.md`

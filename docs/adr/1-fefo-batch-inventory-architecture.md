# ADR-1: FEFO Batch Inventory for Rgd_Inventory

**Status:** Accepted
**Date:** 2026-07-09

Source requirement: [docs/requirements/2026-07-08-batch-based-inventory-management.md](../requirements/2026-07-08-batch-based-inventory-management.md)
Engineering spec: [docs/spec-batch-based-inventory-management.md](../spec-batch-based-inventory-management.md)
Shipped in: commit c50dfb0, branch feature/fefo-batch-inventory (phase 1)

## Context

The pharmacy needs inventory tracked by **SKU + batch number**, where each batch has
its own expiry date and quantity. Dispensing/selling must automatically draw from the
batch with the earliest expiry that still has stock (First Expiry, First Out), switch to
the next batch when one is exhausted, keep the product salable while any batch has stock,
and record which batch every movement touched for traceability/compliance.

Constraints that shaped the design:
- We are on **Magento 2.4.7-p8** with MSI (Multi-Source Inventory) core-vendored. Any
  solution has to coexist with MSI's existing deduction, reservation, and salable-quantity
  machinery rather than fight it.
- This is **patient-safety-adjacent**: under-shipping, dispensing expired stock, or
  losing the audit trail are not acceptable failure modes. Correctness beats cleverness.
- Concurrent checkouts can race for the last units of a batch.
- Phase 1 is admin-grid CRUD plus the deduction path. Returns/restores and CSV import are
  explicitly deferred to phase 2, but anything expensive to reverse later must be decided now.

These five decisions are recorded together because they are interdependent: the transaction
boundary only makes sense given the ledger-as-source-of-truth choice, which only makes sense
given the decision not to reuse MSI's own source entities. Splitting them into separate ADRs
would hide those dependencies.

## Decision 1 - Custom module plus plugin on the deduction path (not MSI SourceItem/Source reuse)

### Options Considered
1. **Repurpose MSI's own SourceItem/Source entities to represent batches** - one MSI
   "source" per expiry lot.
   - Pros: no new tables; salable-quantity aggregation would work for free.
   - Cons: MSI sources model **physical warehouses/locations**, not expiry lots. Overloading
     them pollutes source-selection, stock-source assignment, and shipping UIs with pseudo-sources;
     expiry is not a source concept and there is nowhere natural to hang it; the abuse would be
     permanent and painful to unwind once orders reference these fake sources.
2. **New Rgd_Inventory module with dedicated batch tables, hooked into the deduction path via a
   plugin** on Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface::execute().
   - Pros: batches are modeled as what they are; MSI's source/warehouse model stays clean; the
     plugin sits at the single choke point where MSI turns a sale/shipment into a physical decrement,
     so FEFO allocation happens exactly once per deducting event and is reused across
     shipment- and creditmemo-driven flows.
   - Cons: a new module to own; a plugin on a core interface couples us to that interface's shape.

### Decision
Chose option 2. A dedicated Rgd_Inventory module with its own entities, integrated through an
around plugin on SourceDeductionServiceInterface::execute(). Sources model warehouses; batches
are a distinct concept and get their own model. The plugin targets the **interface**, not the
concrete SourceDeductionService.

## Decision 2 - Pessimistic SELECT ... FOR UPDATE locking (not optimistic version-column)

### Options Considered
1. **Optimistic locking** via a version/updated_at column, retry on conflict.
   - Pros: no held locks; scales well under low contention.
   - Cons: a single FEFO allocation spans **multiple batch rows** (drain B001, then B002, ...).
     Reconciling an optimistic conflict mid-allocation means recomputing which batches to draw
     from and how much from each - the allocation plan itself changes when a row moves underneath
     you, not just one counter. Retrying that correctly is awkward and error-prone.
2. **Pessimistic SELECT ... FOR UPDATE** on the candidate batch rows for the SKU, then allocate
   against the locked set.
   - Pros: the allocation is computed against rows that cannot change under it; matches how MSI
     itself guards source_item; straightforward to reason about for a correctness-critical path.
   - Cons: holds row locks for the duration of the transaction; requires a deterministic lock
     order to avoid deadlocks.

### Decision
Chose option 2. The FEFO selecting query locks candidate rows with FOR UPDATE, ordered
ORDER BY expiry_date IS NULL, expiry_date ASC, batch_id ASC. The batch_id ASC tie-breaker is
not cosmetic - it gives a **stable lock-acquisition order across concurrent transactions**, so two
simultaneous checkouts block rather than deadlock. NULL-expiry batches sort last ("unknown expiry"
is not "expired").

## Decision 3 - One shared transaction owned by a coordinator, spanning all items AND MSI's proceed()

This is the correctness decision, and the original design was **wrong** - worth recording so nobody
reintroduces it.

### Options Considered
1. **Per-item independent transactions (original design).** Each ItemToDeductInterface deducts its
   batches and commits on its own; MSI's original proceed() runs separately afterwards.
   - Cons: **This is a real integrity bug**, flagged HIGH by Security Testing. Our batch ledger
     committed *before* MSI's source_item decrement ran. If proceed() (or a later item) then
     failed, our ledger was already committed and MSI's aggregate was not - leaving the two stores
     permanently disagreeing, with no rollback. In a pharmacy context this is unacceptable.
2. **One shared transaction, owned by a coordinator, wrapping every item's batch deduction *and*
   MSI's proceed().**
   - Pros: our ledger and MSI's source_item aggregate commit or roll back **together**; any failure
     at any point rolls the whole thing back with no partial state.
   - Cons: the lock is held across proceed() (accepted; the alternative is incorrectness); requires
     a coordinator that can pass $proceed through to the point where the transaction is still open.

### Decision
Chose option 2. A package-private Rgd\Inventory\Model\SourceDeductionCoordinator (deliberately
**not** part of the Api/ contracts) owns the transaction lifecycle:

    begin transaction
      for each item: SELECT ... FOR UPDATE, compute FEFO, UPDATE remaining_qty, INSERT ledger rows
      invoke proceed(sourceDeductionRequest)   // MSI native source_item decrement, inside the tx
    commit                                      // any exception anywhere -> rollback, propagate

Consequently BatchDeductionServiceInterface::deduct() is *not* a "call it, get a result, then call
proceed() separately" API - the atomicity requirement forces the transaction to open one level up,
in the coordinator, with the allocation logic exposed as a reusable non-transactional inner method.
deduct() remains the externally-callable atomic contract for non-plugin callers (e.g. future manual
adjustment tooling) and reuses that same inner logic.

## Decision 4 - Returns restore to the *original* batch(es) via the audit trail (contract locked, impl deferred)

### Options Considered
1. **Restore returned quantity to an arbitrary or newest batch.**
   - Pros: trivial to implement.
   - Cons: breaks traceability and FEFO integrity - stock physically came out of specific lots; putting
     it back somewhere else corrupts per-batch quantities and can resurrect or mis-date stock. Wrong for
     a pharmacy.
2. **Restore to the exact batch(es) the quantity was originally drawn from,** reconstructed from the
   rgd_inventory_batch_transaction audit trail (matched by order_item_id, movement_type=deduction,
   walked oldest-first up to the returned qty).
   - Pros: correct; preserves per-batch traceability across the full deduct/return lifecycle.
   - Cons: more work; depends on the audit trail being complete and correctly keyed by order_item_id.

### Decision
Chose option 2 **as the contract**, but defer the implementation to phase 2. BatchReturnServiceInterface
(restore(sku, qty, salesEvent, orderItemId, sourceCode)) is defined and locked now so phase 2 is a
drop-in. In phase 1 our ledger is **not** auto-restored on cancel/creditmemo beyond what MSI already does
natively at the source_item level - the phase-1 method is a documented no-op stub (returns empty array).
Locking the signature now is what makes this cheap later; a half-correct restore (e.g. mishandling
partial-quantity creditmemos) was judged worse than an honest no-op for phase 1.

## Decision 5 - Batch ledger is the authoritative source of truth; MSI's aggregate is kept in sync as a side effect

### Options Considered
1. **MSI's inventory_source_item.quantity stays authoritative; our batch table is a secondary
   annotation** derived from it.
   - Cons: MSI has no concept of batches or expiry, so it cannot be the authority for per-batch
     remaining quantity or FEFO ordering. Deriving batch state from an aggregate that doesn't know
     about batches is impossible without a parallel store anyway.
2. **rgd_inventory_batch.remaining_qty is authoritative;** MSI's source_item aggregate is kept in
   sync as a *side effect* of letting proceed() run inside the same transaction (Decision 3).
   - Pros: single source of truth for batch quantities and FEFO; MSI's salable-quantity /
     in-stock / out-of-stock behavior keeps working unchanged because source_item.quantity and our
     ledger move together atomically. Out-of-stock falls out naturally when all batches hit zero - no
     new stock-status signal invented.
   - Cons: correctness depends entirely on Decision 3's shared transaction; if the two stores ever
     commit independently they drift.

### Decision
Chose option 2. The batch ledger is the authority; MSI's aggregate is a synchronized consequence, not
the reverse. remaining_qty also carries a DB-level unsigned constraint plus an application-layer
floor guard as defense-in-depth - a negative write is treated as a bug, never an expected path.

## Consequences

**Easier**
- Batches are modeled honestly; MSI's warehouse/source model stays uncontaminated and its salable-qty,
  reservation, and shipping flows keep working with no changes.
- FEFO allocation lives at one choke point and is reused by every deduction flow.
- The append-only ledger gives complete per-batch traceability, satisfying the pharmacy audit requirement,
  and is already the substrate phase-2 returns will build on.
- Atomicity between our ledger and MSI's aggregate is guaranteed, so the two stores cannot silently drift.

**Harder / trade-offs accepted**
- We hold pessimistic row locks across MSI's proceed(). This narrows checkout concurrency on hot SKUs;
  accepted deliberately because independent commits are incorrect. A deterministic lock order
  (the batch_id ASC tie-breaker) is mandatory to avoid deadlocks - do not remove it.
- We are coupled to SourceDeductionServiceInterface::execute()'s shape and to SalesEvent /
  ItemToDeductInterface (which carries only sku+qty, so the plugin resolves order_item_id itself via
  OrderItemRepositoryInterface). A future Magento change to this interface is our maintenance burden.
- BatchDeductionServiceInterface::deduct() cannot be simplified into a fire-and-forget call - the
  transaction genuinely must open in the coordinator, above deduct(). Anyone refactoring the plugin must
  preserve the "proceed() inside our transaction" invariant or they reintroduce the Security-flagged
  integrity bug.

**Follow-up work created**
- Phase 2: implement BatchReturnServiceInterface::restore() against the audit trail (contract already
  fixed); wire it into the creditmemo/cancel flow so the ledger is restored to original batches.
- Phase 2: CSV batch import (the reason expiry_date is nullable at the DB layer while required at the
  admin-form layer - do not tighten the column to NOT NULL without accounting for import).
- Watch for third-party plugins added later on SourceDeductionServiceInterface; the plugin sortOrder is
  currently unspecified because we expect to be the only plugin.

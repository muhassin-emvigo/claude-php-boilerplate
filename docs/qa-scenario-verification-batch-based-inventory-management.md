# QA Scenario Verification — Batch-Based Inventory Management (FEFO)

**Source requirement:** `docs/requirements/2026-07-08-batch-based-inventory-management.md`
**Module:** `Rgd_Inventory` (`app/code/vendor/Rgd/Inventory`), branch `feature/fefo-batch-inventory`
**Verification date:** 2026-07-09
**Environment:** real, live MySQL database `magento` at `127.0.0.1` (not SQLite/in-memory, not mocked) — Magento 2.4.7-p8, `bin/magento`/object manager bootstrap, area code `adminhtml`
**Method:** a standalone bootstrap script (`app/bootstrap.php` + `Magento\Framework\App\Bootstrap`) resolves the real DI-wired implementations of `BatchRepositoryInterface`, `FefoBatchSelectorInterface`, and `BatchDeductionServiceInterface` via the object manager, and drives them exactly as production code (e.g. the `SourceDeductionServicePlugin` → `SourceDeductionCoordinator` → `BatchDeductionService::deduct()` path) would. Every "actual" value below is a real query result against `rgd_inventory_batch` / `rgd_inventory_batch_transaction`, not a re-statement of unit-test mocks or prior claims.

This is a real re-run performed as part of the final cleanup pass (phpmd/phpstan resolution), not a copy of a previous pass's numbers.

---

## Test data

SKU under test: `TAB-001-QA` (suffixed `-QA` to avoid colliding with any real catalog SKU; functionally identical to the requirement doc's `TAB-001` example). Source: `default`.

| Batch | Expiry (relative to run date 2026-07-08) | Received Qty | Starting Remaining Qty |
|---|---|---:|---:|
| B001 | +60 days (2026-09-06) — sooner | 50 | 50 |
| B002 | +180 days (2027-01-04) — later | 100 | 100 |

Pre-run baseline (confirmed via direct query before the script executed):

```sql
SELECT COUNT(*) FROM rgd_inventory_batch;              -- 0
SELECT COUNT(*) FROM rgd_inventory_batch_transaction;   -- 0
```

---

## Scenario 1 — Buy 20, deducted from B001 (earlier expiry)

**Call:** `BatchDeductionServiceInterface::deduct('TAB-001-QA', 20.0, $salesEvent, null, 'default')`

**Expected (per requirement doc):** all 20 units come from B001; B001 → 30, B002 stays at 100.

**Actual (real DB query after the call):**

```
allocations = [["B001", 20]]
B001 remaining_qty (db) = 30
B002 remaining_qty (db) = 100
```

**Result: PASS**

---

## Scenario 2 — Buy 30 more, B001 exhausted to 0

**Call:** `deduct('TAB-001-QA', 30.0, $salesEvent, null, 'default')`

**Expected:** remaining 30 units still come from B001 (it had exactly 30 left); B001 → 0.

**Actual:**

```
allocations = [["B001", 30]]
B001 remaining_qty (db) = 0
B002 remaining_qty (db) = 100
```

**Result: PASS**

---

## Scenario 3 — Buy 10, auto-drawn from B002, product still available

**Call:** `deduct('TAB-001-QA', 10.0, $salesEvent, null, 'default')`

**Expected:** since B001 is exhausted, the system automatically switches to B002 without any caller-side batch selection; product remains purchasable.

**Actual:**

```
allocations = [["B002", 10]]
B001 remaining_qty (db) = 0
B002 remaining_qty (db) = 90
```

Additionally verified "product still available for purchase" by calling `FefoBatchSelectorInterface::selectForDeduction('TAB-001-QA', 1.0)` directly afterward — it succeeded and returned 1 allocation (from B002), confirming the product is not (yet) out of stock.

**Result: PASS** (both the deduction and the "still available" follow-up check)

---

## Scenario 4 — Continue until both batches are 0 → product Out of Stock

**Call:** `deduct('TAB-001-QA', 90.0, $salesEvent, null, 'default')` (exhausts the rest of B002)

**Actual after this call:**

```
B001 remaining_qty (db) = 0
B002 remaining_qty (db) = 0
```

**4a — both batches now zero: PASS**

**Expected:** with both batches at 0, further selection/deduction requests must be rejected (product Out of Stock), not silently under-fulfilled.

**Actual — `FefoBatchSelectorInterface::selectForDeduction('TAB-001-QA', 1.0)`:**

```
Threw Magento\Framework\Exception\LocalizedException:
"Insufficient batch stock for SKU "TAB-001-QA": requested 1, available 0."
```

**4b — selector correctly rejects further requests: PASS**

**Actual — `BatchDeductionServiceInterface::deduct('TAB-001-QA', 1.0, ...)`:**

```
Threw Magento\Framework\Exception\LocalizedException:
"Insufficient batch stock for SKU "TAB-001-QA": requested 1, available 0."
```

**4c — deduction service (the locking path) also correctly rejects further requests: PASS**

Both the read-only planner (`FefoBatchSelectorInterface`) and the locking/transactional path (`BatchDeductionServiceInterface`) independently agree the product is out of stock once both batches hit 0 — no split-brain between the two call paths.

---

## Audit ledger — traceability check

Per the requirement doc's "each inventory transaction records the actual batch used for traceability," queried `rgd_inventory_batch_transaction` for all rows written against `TAB-001-QA` after Scenarios 1-4:

```sql
SELECT transaction_id, batch_id, batch_number, movement_type, qty, sales_event_type, reference
FROM rgd_inventory_batch_transaction
WHERE sku = 'TAB-001-QA'
ORDER BY transaction_id ASC;
```

**Actual (real rows):**

| transaction_id | batch_id | batch_number | movement_type | qty | sales_event_type |
|---:|---:|---|---|---:|---|
| 19 | 1233 | B001 | deduction | -20.0000 | order_placed |
| 20 | 1233 | B001 | deduction | -30.0000 | order_placed |
| 21 | 1234 | B002 | deduction | -10.0000 | order_placed |
| 22 | 1234 | B002 | deduction | -90.0000 | order_placed |

Total ledger rows: 4. Sum of absolute qty = 20 + 30 + 10 + 90 = 150 = 50 (B001 received_qty) + 100 (B002 received_qty) — the ledger fully accounts for every unit deducted, split correctly by the batch it actually came from, matching each scenario's expected batch attribution one-for-one.

**Result: PASS**

(`transaction_id`/`batch_id` values are non-sequential-looking because this MySQL instance's auto-increment counters have accumulated across many prior QA/dev passes on this same database — expected and harmless; uniqueness and correctness of the FK relationships were confirmed by the batch_number/movement_type/qty values themselves, not the raw ID magnitudes.)

---

## Expiry-cutoff boundary case

Per the requirement doc's "Expired batches must not be used for dispensing or sales" and the spec's explicit resolution that a batch expiring exactly today is treated as already expired (`expiry_date > CURDATE()`, not `>=`). Tested on a separate SKU (`TAB-001-QA-EXPCHECK`) to isolate from Scenarios 1-4.

### Case A — batch expiring exactly today is excluded

Created batch `BTODAY`: `expiry_date = 2026-07-08` (today, at run time), `remaining_qty = 25`.

**Actual — `selectForDeduction('TAB-001-QA-EXPCHECK', 1.0)`:**

```
Threw LocalizedException:
"No usable batch stock for SKU "TAB-001-QA-EXPCHECK": 25 unit(s) exist across 1 batch(es) but all are expired."
```

**Actual — `deduct('TAB-001-QA-EXPCHECK', 1.0, ...)` (the locking path):**

```
Threw LocalizedException:
"No usable batch stock for SKU "TAB-001-QA-EXPCHECK": 25 unit(s) exist across 1 batch(es) but all are expired."
```

Both the non-locking selector and the locking deduction path correctly and independently exclude the batch expiring today, and both surface the distinct "all expired" message (not the generic "insufficient stock" or "no inventory configured" message), matching the spec's three-distinct-message error-handling design.

**Result: PASS** (both call paths)

### Case B — batch expiring tomorrow (strictly future) is usable

Added a second batch `BTOMORROW` on the same SKU: `expiry_date = 2026-07-09` (tomorrow), `remaining_qty = 5`.

**Actual — `selectForDeduction('TAB-001-QA-EXPCHECK', 1.0)`:**

```
allocations = [["BTOMORROW", 1]]
```

Selection succeeded and drew from `BTOMORROW` (the only non-expired candidate), confirming the cutoff is strictly `> CURDATE()`, not `>= CURDATE()` — a batch expiring tomorrow is correctly treated as usable, not expired.

**Result: PASS**

---

## Bug found and fixed during this verification pass

While capturing the "1 batch(es)" wording in Case A above, the real-DB run initially reported **"25 unit(s) exist across 1227 batch(es)"** — an implausible count given only one batch existed for that SKU. Root-caused to `FefoBatchSelector::diagnoseAndThrow()`:

```php
// Before (bug): fetchOne() on a bare from($table) is `SELECT *`, so it returns
// the first COLUMN of the first matching ROW (batch_id) — not a row count.
$totalBatchCount = (int) $connection->fetchOne(
    $connection->select()->from($candidateCollection->getMainTable())
        ->where('sku = ?', $sku)->where('source_code = ?', $sourceCode)->where('is_active = 1')
);
```

`1227` was literally the matching row's `batch_id` (auto-increment had climbed there across many prior dev/QA passes on this shared database), misreported as a batch count in an admin-facing diagnostic message. The sibling method `BatchDeductionService::diagnoseAndThrow()` already did this correctly via `fetchAll()` + `count()`. Fixed `FefoBatchSelector.php` to select `COUNT(*)` explicitly:

```php
$totalBatchCount = (int) $connection->fetchOne(
    $connection->select()->from($candidateCollection->getMainTable(), 'COUNT(*)')
        ->where('sku = ?', $sku)->where('source_code = ?', $sourceCode)->where('is_active = 1')
);
```

This bug was **not caught by the existing unit test suite** (`FefoBatchSelectorTest`) because that test mocks `fetchOne()` directly to return a pre-supplied count, bypassing the actual SQL construction entirely — it could never have detected a "wrong column selected" defect. This is exactly the class of bug real-database verification exists to catch. Re-ran this scenario after the fix: message now correctly reads "1 batch(es)" (see Case A above, post-fix). Full unit suite (37/37), phpcs, and phpstan re-verified clean after the fix (see Final Verification below).

---

## Cleanup verification

The bootstrap script deletes all rows for `TAB-001-QA` and `TAB-001-QA-EXPCHECK` (transactions first, then batches, respecting the FK) via a `register_shutdown_function`, so cleanup runs even on failure/exception. Confirmed via direct query immediately after the run:

```sql
SELECT COUNT(*) AS batch_rows_remaining FROM rgd_inventory_batch;              -- 0
SELECT COUNT(*) AS txn_rows_remaining FROM rgd_inventory_batch_transaction;    -- 0
```

Both tables are empty. No test data was left behind in the `magento` database.

---

## Summary

| # | Scenario | Result |
|---|---|---|
| Setup | Create B001 (qty 50, sooner expiry) + B002 (qty 100, later expiry) | PASS |
| 1 | Buy 20 → B001=30, B002=100 | PASS |
| 2 | Buy 30 more → B001=0 | PASS |
| 3 | Buy 10 → auto-drawn from B002, B002=90, product still available | PASS |
| 4a | Continue until both batches = 0 | PASS |
| 4b | Selector rejects further requests once both batches are 0 (Out of Stock) | PASS |
| 4c | Deduction service (locking path) also rejects further requests | PASS |
| Ledger | 4 audit rows written, correctly attributed per batch, sum matches total deducted | PASS |
| Expiry A | Batch expiring exactly today is excluded (both selector and deduct()) | PASS |
| Expiry B | Batch expiring tomorrow (strictly future) is usable | PASS |
| Cleanup | Both tables empty after run | PASS |

**11/11 checks passed against the real, live `magento` MySQL database.** One real bug (miscounted diagnostic message in `FefoBatchSelector::diagnoseAndThrow()`) was found and fixed during this pass — see "Bug found and fixed" above.

---

## Final verification suite (run after all fixes in this pass)

`php vendor/bin/phpunit -c phpunit.xml.dist`:
```
OK (37 tests, 112 assertions)
```

`php vendor/bin/phpcs --standard=phpcs.xml.dist app/code/vendor/Rgd/Inventory`:
No file/line violations reported (only a tool-level deprecation notice about a CSS sniff, unrelated to this module's code).

`php vendor/bin/phpstan analyse -c phpstan.neon.dist app/code/vendor/Rgd/Inventory`:
```
[OK] No errors
```

`php vendor/bin/phpmd app/code/vendor/Rgd/Inventory text phpmd.xml.dist`:
No output (exit code 0 — zero findings).

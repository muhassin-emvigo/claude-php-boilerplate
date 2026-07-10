## Technical spec — batch-based-inventory-management-run2

**Module:** `Rgd_Inventory` (vendor `Rgd`)
**Source requirement:** `docs/requirements/2026-07-08-batch-based-inventory-management.md`
**Relationship to Run 1:** This is an incremental spec. Run 1 (`docs/spec-batch-based-inventory-management.md`,
shipped in commit `c50dfb0`) covers the FEFO deduction core (schema, repository, deduction service,
plugin/coordinator, admin CRUD design). This run does not revisit or supersede any Run 1 decision — it
specs the GraphQL read surface added on top of the shipped core, plus formalizes two admin-UI bug fixes
and one new `@api` selector method that landed uncommitted on this branch. Where this document restates a
Run 1 fact (e.g. the expiry cutoff rule), it is quoting the existing contract for context, not redefining it.
**Branch:** `feature/fefo-batch-inventory`. **Flow type:** `backend-feature` (not `security-patch` — no
Threat model section required; included below only as an explicit N/A per the output format).
**CEO/Architecture decisions already approved, not re-litigated here:** (1) new GraphQL query
`rgdInventoryStock(sku, sourceCode)`; (2) mark it `@cache(cacheable: false)`; (3) leave it unauthenticated;
(4) `FefoBatchSelectorInterface::getAvailableBatches()` as a new `@api` method co-located with
`selectForDeduction()`. This spec verifies each against the actual code on disk and fills in the contract
detail Build needs.

---

### API contracts

#### GraphQL query: `rgdInventoryStock`

Schema (`app/code/vendor/Rgd/Inventory/etc/schema.graphqls`), as it now stands after this run's edit:

```graphql
type Query {
    rgdInventoryStock(
        sku: String! @doc(description: "Product SKU to check batch stock for")
        sourceCode: String = "default" @doc(description: "MSI source code")
    ): RgdInventoryStock
        @resolver(class: "Rgd\\Inventory\\Model\\Resolver\\InventoryStock")
        @doc(description: "FEFO batch inventory and available-to-sell quantity for a SKU. Excludes inactive, expired, and out-of-stock batches — a batch expiring today is already treated as expired.")
        @cache(cacheable: false)
}

type RgdInventoryStock @doc(description: "Batch inventory summary for a SKU") {
    sku: String! @doc(description: "Product SKU")
    available_qty: Float! @doc(description: "Total sellable quantity across active, non-expired batches")
    batches: [RgdInventoryBatch] @doc(description: "Active, non-expired, in-stock batches ordered earliest-expiry-first (FEFO order)")
}

type RgdInventoryBatch @doc(description: "A single inventory batch") {
    batch_number: String! @doc(description: "Batch/lot number")
    expiry_date: String @doc(description: "Expiry date (Y-m-d), null if the batch does not track expiry")
    available_qty: Float! @doc(description: "Remaining sellable quantity in this batch")
    received_at: String @doc(description: "When this batch was received into inventory")
}
```

This matches what was on disk except for the added `@cache(cacheable: false)` directive on the
`rgdInventoryStock` field, which this spec adds directly (see "Cache directive" below) — everything else
in the schema was already correct against the resolver and is confirmed, not changed.

**Field → data mapping** (`RgdInventoryStock`/`RgdInventoryBatch` ← `BatchInterface`), as implemented in
`Model/Resolver/InventoryStock.php`:

| GraphQL field | Source |
|---|---|
| `sku` | echoes the trimmed input `sku` argument (not re-read from any batch row) |
| `available_qty` | `array_sum` of `BatchInterface::getRemainingQty()` across all batches returned by the selector |
| `batches[].batch_number` | `BatchInterface::getBatchNumber()` |
| `batches[].expiry_date` | `BatchInterface::getExpiryDate()` |
| `batches[].available_qty` | `BatchInterface::getRemainingQty()` |
| `batches[].received_at` | `BatchInterface::getCreatedAt()` — "received at" is modeled as the batch row's `created_at`, i.e. when it was intaken into `rgd_inventory_batch`, not a separate receiving-date field (there isn't one) |

**Resolver contract** (`Rgd\Inventory\Model\Resolver\InventoryStock`, implements
`Magento\Framework\GraphQl\Query\ResolverInterface`):

```php
public function __construct(
    private FefoBatchSelectorInterface $fefoBatchSelector,
) {}

public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
```

- `sku` is read from `$args['sku']`, cast to string, trimmed. Empty (or missing) after trim →
  `throw new GraphQlInputException(__('"sku" is required.'))`. This is enforced in the resolver even though
  the schema already declares `sku: String!` — the schema-level `!` guarantees the argument is *present*
  and non-null, it does not guarantee non-empty-string, so the resolver-level check is not redundant.
- `sourceCode` is read from `$args['sourceCode']`, defaulting to `'default'` if absent — this mirrors the
  schema default but the resolver re-applies it defensively (`$args['sourceCode'] ?? 'default'`) rather
  than assuming the schema default always reaches the resolver.
- Delegates entirely to `FefoBatchSelectorInterface::getAvailableBatches($sku, $sourceCode)` — see next
  section. No other business logic in the resolver; it is a pure mapping layer.
- Return type is a plain associative array matching the `RgdInventoryStock` GraphQL type shape — correct
  per `graphql.md`'s "resolvers return arrays matching the schema type, never Magento model/data objects
  directly" rule (the resolver does not leak `BatchInterface` objects into the response array, it maps
  each one to a plain array first).

**Auth (confirmed, not re-decided here).** No `$context`-based auth check. `$context` is accepted (interface
requirement) but unused. This is deliberate parity with Magento's own unauthenticated stock-status queries
(e.g. `products` query salable-quantity fields) — customers must be able to see stock before logging in.
There is no ACL resource, no customer-token check, and no `webapi.xml` involved (GraphQL, not REST) — this
is intentional and matches `graphql.md`'s "Customer-scoped fields must check `$context->getUserId()`" rule
by exclusion: this field is not customer-scoped, so that rule does not apply, and no such check should be
added.

**Cache directive.** `@cache(cacheable: false)` (added by this spec directly, per the approved architecture
decision) is required because:
- The field takes a `sku` argument and is resolved by a custom resolver at the `Query` root — it is not a
  sub-field of an already-identity-varying root like `products(filter: ...)`, so Magento's GraphQL
  full-page-cache layer would otherwise treat it as cacheable-by-default and vary the cached response only
  by the standard cache context (customer group, store, currency), **not** by the `sku`/`sourceCode`
  arguments. A second request for a different `sku` could be served the first request's cached batch data.
- Even scoped correctly per-argument, `available_qty`/`remaining_qty` changes on every order — caching it
  at all (under any key) risks serving stale stock during a cache TTL window, which is unacceptable for a
  field whose whole purpose is real-time dispensing-safe stock visibility.
- This is the same rationale and the same directive already used by core for comparable
  argument-driven/volatile fields — confirmed by grep against vendor: `customer` (`module-customer-graph-ql`),
  `cart`/`customerCart` (`module-quote-graph-ql`), `customerDownloadableProducts`
  (`module-customer-downloadable-graph-ql`) all carry `@cache(cacheable: false)` for the same class of reason.
- The `@cache` directive itself is declared once, in core, at
  `vendor/magento/module-graph-ql/etc/schema.graphqls:42`
  (`directive @cache(cacheIdentity: String="" cacheable: Boolean=true) on QUERY`) — `Rgd_Inventory` does not
  need to (and must not) redeclare it, only reference it.

**Error paths — exhaustive:**

| Condition | Thrown from | Exception | Notes |
|---|---|---|---|
| `sku` argument missing, or present but empty/whitespace-only after `trim()` | `InventoryStock::resolve()` | `GraphQlInputException` | Message: `"sku" is required.` Surfaces in the GraphQL response `errors[]` array with `extensions.category = "graphql-input"` (standard Magento categorization) — not an HTTP-level 4xx, per `graphql.md`. |
| Any `\Throwable` from `FefoBatchSelectorInterface::getAvailableBatches()` (e.g. DB connectivity failure) | not caught in the resolver | propagates as an uncaught exception → Magento's GraphQL error formatter reports it as an internal/`graphql` category error | Not a new error path introduced by this spec — `getAvailableBatches()` itself never throws on business-logic grounds (see next section); anything that does escape it is an infrastructure failure, which is out of scope to special-case here (consistent with `error-handling.md`: only catch what you can meaningfully convert). |
| SKU not found in catalog, SKU has zero batches configured, SKU has batches but all expired/inactive, or `sourceCode` does not match any configured MSI source | none — **not an error** | — | Returns `{sku, available_qty: 0, batches: []}` in every one of these cases. This is a deliberate, single unified "no usable stock" response shape rather than a distinct error per cause — see rationale below. |

**Why "no usable stock" is not an error here, even though the equivalent condition throws three distinct
`LocalizedException` variants in `selectForDeduction()` (Run 1 spec, Error handling strategy).** The
deduction path throws because failing to dispense against an order is an operational failure the admin
needs to triage (insufficient stock vs. all-expired vs. never-onboarded are different fixes). The GraphQL
read path is answering "what's currently available," for which "nothing is available" is a completely valid,
expected answer — not an error — the same way a storefront stock query for an out-of-stock product returns
`in_stock: false`, not a thrown exception. Collapsing FEFO's three distinct diagnostic conditions into one
`available_qty: 0` here is correct for a public read API; distinguishing *why* stock is unavailable is an
admin-facing concern already served by the batch grid, not something to expose to storefront/mobile
clients. Build must not port `selectForDeduction()`'s throwing behavior onto this resolver.

**Unauthenticated SKU/source enumeration — accepted risk, documented.** Any caller can probe `sourceCode`
with an arbitrary string and get back an empty-but-200 response rather than a "no such source" error (there
is no validation against `Magento\InventoryApi\Api\SourceRepositoryInterface`). This means the existence of
non-default source codes cannot be *confirmed* via this query (a real, populated internal-only source would
return actual data; a bogus one and an empty real one look identical), but it also means the query leaks no
signal distinguishing "source doesn't exist" from "source exists but has no stock for this SKU." Accepted
as consistent with the "parity with public stock-status queries" decision — core stock queries have the
same non-distinguishing behavior for unknown identifiers. No action required from Build; flagged here so
QA doesn't mistake it for a gap in this spec.

#### `Api\FefoBatchSelectorInterface::getAvailableBatches()` (new `@api` method)

Current interface, confirmed on disk (`app/code/vendor/Rgd/Inventory/Api/FefoBatchSelectorInterface.php`):

```php
/**
 * Get all active, non-expired, in-stock batches for a SKU in FEFO order
 * (earliest expiry_date first; NULL-expiry batches ordered last), without
 * allocating against a target quantity and without throwing on empty stock.
 *
 * Applies the same expiry rule as selectForDeduction() — a batch expiring
 * today is already treated as expired. Intended for read-only stock/expiry
 * checks (e.g. GraphQL) rather than the deduction path.
 *
 * @param string $sku
 * @param string $sourceCode
 * @return BatchInterface[] Empty array if no usable stock
 */
public function getAvailableBatches(string $sku, string $sourceCode = 'default'): array;
```

This spec confirms the signature and locks the following contract points, verified against the
implementation in `Model/FefoBatchSelector.php`:

1. **Return type is `BatchInterface[]`, not `BatchAllocationInterface[]`.** This is a deliberate divergence
   from `selectForDeduction()`, not an inconsistency to "fix." `BatchAllocationInterface` (Run 1) is a
   narrow ephemeral DTO (`batchId`, `batchNumber`, `expiryDate`, `qty` only) built for the deduction
   audit-write path. The GraphQL resolver needs `getCreatedAt()` (→ `received_at`) which
   `BatchAllocationInterface` does not carry, and conceptually this method is exposing "what batches exist
   and are usable," not "what got allocated to satisfy a request" — the full entity is the right return
   type. Build must not change this to `BatchAllocationInterface[]` for symmetry with `selectForDeduction()`.
2. **Never throws on empty/no stock.** Confirmed: unlike `selectForDeduction()`, there is no
   `diagnoseAndThrow()` call anywhere in `getAvailableBatches()` — an empty candidate set simply returns
   `[]`. This is the load-bearing behavioral contract the GraphQL resolver depends on (see previous
   section's "not an error" table row) and is explicitly asserted by
   `FefoBatchSelectorTest::testGetAvailableBatches_DoesNotThrowOnEmptyStock()`.
3. **Filter and FEFO ordering are shared with `selectForDeduction()`** via a private helper,
   `applyCandidateFilterAndFefoOrder(Collection $collection, string $sku, string $sourceCode): void`, so the
   two methods cannot silently drift apart:
   ```sql
   WHERE sku = :sku AND source_code = :sourceCode AND is_active = 1 AND remaining_qty > 0
     AND (expiry_date IS NULL OR expiry_date > CURDATE())
   ORDER BY expiry_date IS NULL, expiry_date ASC, batch_id ASC
   ```
   — identical predicate and ordering to Run 1's locked `SELECT ... FOR UPDATE` query, minus the
   `FOR UPDATE` itself (this method takes no lock; see next point).
4. **Expiry cutoff rule — reconfirmed, unchanged from Run 1.** `expiry_date > CURDATE()` is a **strict**
   inequality: a batch whose `expiry_date` equals today is excluded, i.e. treated as already expired for
   both allocation and read purposes. `expiry_date IS NULL` batches are always included and sort last.
   `FefoBatchSelectorTest::testSelectForDeduction_BatchExpiringToday_IsExcludedFromCandidates()` and
   `::testSelectForDeduction_BatchExpiringTomorrow_IsIncluded()` assert this through the shared filter (see
   Test coverage section below for the one gap: no equivalent assertion runs the check through
   `getAvailableBatches()`'s own entry point).
5. **No locking, no mutation** — confirmed: `getAvailableBatches()` opens a plain collection, no
   `FOR UPDATE`, no transaction, no write. This is correct and required — it is called from an
   unauthenticated, high-traffic, read-only GraphQL path; taking row locks there would create lock
   contention against real checkout transactions for no reason. Do not add locking to this method.
6. **Column selection is intentionally full-row**, unlike `selectForDeduction()`'s narrowed
   `addFieldToSelect(['batch_id', 'batch_number', 'expiry_date', 'remaining_qty'])`. `getAvailableBatches()`
   does not narrow the select, because it must expose `created_at` (`received_at`) in addition to the four
   columns `selectForDeduction()` needs — this is correct, not an oversight to "optimize" by copying the
   narrower select.

---

### Data model

**No schema changes in this run.** `getAvailableBatches()` reads the existing `rgd_inventory_batch` table
only, through the same WHERE/ORDER shape as `selectForDeduction()`, and is therefore covered by the same
index Run 1 already shipped: `RGD_INVENTORY_BATCH_SKU_SOURCE_CODE_EXPIRY_DATE` (`sku`, `source_code`,
`expiry_date`) — confirmed present in `app/code/vendor/Rgd/Inventory/etc/db_schema.xml`. (Note for anyone
cross-referencing: Run 1's spec document used a different provisional index name,
`RGD_INVENTORY_BATCH_SKU_EXPIRY_DATE_REMAINING_QTY`, in its Data Model section; the name that actually
shipped is `RGD_INVENTORY_BATCH_SKU_SOURCE_CODE_EXPIRY_DATE`. That's a pre-existing Run 1
spec-vs-implementation naming drift, not something this run introduces or needs to reconcile — flagging
only so Build/QA don't go looking for the Run-1-spec name and conclude the index is missing.)

No migration plan needed — no `db_schema.xml` diff, no `db_schema_whitelist.json` regeneration required for
this run.

---

### Service boundaries

```
GraphQL request: { rgdInventoryStock(sku: "...", sourceCode: "...") { ... } }
        │
        ▼
Magento GraphQL schema stitching (schema.graphqls, unauthenticated route, @cache(cacheable:false))
        │
        ▼
Rgd\Inventory\Model\Resolver\InventoryStock::resolve()
        │  validates sku, defaults sourceCode, maps result to GraphQL array shape
        ▼
Rgd\Inventory\Api\FefoBatchSelectorInterface::getAvailableBatches($sku, $sourceCode)
        │  (same interface/implementation already used by the deduction path — no new
        │   service class introduced; both entry points share applyCandidateFilterAndFefoOrder())
        ▼
Rgd\Inventory\Model\ResourceModel\Batch\Collection (read-only, no lock)
        │
        ▼
rgd_inventory_batch table
```

**Reuse, not duplication.** This run does not introduce a second selection/filtering implementation for the
read path — `getAvailableBatches()` and `selectForDeduction()` live on the same interface, in the same
class, sharing the same private filter/order helper. This is the correct shape per `magento-module.md`
("Use Plugins... Data Patches... Use `SearchCriteriaBuilder`..." — generally, avoid parallel logic paths for
the same domain rule) and avoids the FEFO ordering/expiry-cutoff rule ever needing to be kept in sync by
hand across two places.

**Admin UI data providers — architectural note, not a new decision.** Both `BatchListingDataProvider` (new
this run, backs `rgd_inventory_batch_listing`) and `BatchDataProvider` (Run 1, form data source) extend
`Magento\Ui\DataProvider\AbstractDataProvider` and are constructed directly from
`Rgd\Inventory\Model\ResourceModel\Batch\CollectionFactory` — neither goes through
`BatchRepositoryInterface::getList(SearchCriteriaInterface)`. This is **not** a violation of `api.md`'s
"Use `SearchCriteriaInterface` for list endpoints" rule — that rule governs `Api/` service-contract list
methods (webapi/GraphQL/programmatic callers), not UI Component admin-grid data providers, which
conventionally bind directly to a collection in core Magento too (`Magento\Ui\DataProvider\AbstractDataProvider`
exists specifically for this pattern). `BatchRepositoryInterface::getList()` remains the correct,
unchanged entry point for any programmatic/service-contract caller that needs a paginated batch list.

**What changed on this branch to reach `BatchListingDataProvider`, precisely (for Build/QA context, not a
new decision — already implemented and manually verified per BUGS.md):** the listing's data source
previously used the generic `Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider` class,
which requires `filterPoolName`/`searchCriteriaBuilder` arguments wiring a search-criteria-based filter
pool; that wiring was incomplete/misconfigured on this branch. It was replaced with the purpose-built
`BatchListingDataProvider` (collection-backed, same pattern as `BatchDataProvider`), and the
`filterPoolName`/`searchCriteriaBuilder` arguments were removed from
`rgd_inventory_batch_listing.xml` accordingly. `BatchListingDataProvider` deliberately does **not** override
`getData()` — the inherited `AbstractDataProvider::getData()` (`$this->getCollection()->toArray()`) already
produces the `{items, totalRecords}` shape the grid JS component expects, in contrast to `BatchDataProvider`
(the form provider), which needs the different `[$id => $itemData]` shape and does override `getData()`.

**requirejs mixin boundary (admin, JS layer only).** `view/adminhtml/requirejs-config.js` (new) registers a
mixin on `js/theme` (core Magento admin theme script), scoped to `adminhtml` area via the module's own
`requirejs-config.js`, not a global override. This is the RequireJS-layer equivalent of a plugin —
targeted interception of one script's behavior without a core class rewrite — and is within the bounds of
`magento-module.md`'s "never use rewrites" rule, which concerns PHP class preferences, not client-side
mixins (a standard, supported Magento mechanism for this exact class of problem). The mixin
(`view/adminhtml/web/js/force-collapse-plugin-mixin.js`) requires `jquery/bootstrap/collapse` and attaches
`$.fn.collapse` synchronously before `theme.js` runs, working around Bootstrap 5 only registering that
jQuery plugin on `DOMContentLoaded` (too late for `theme.js`'s own `.collapse('show')` call). See BUGS.md
"Fixed" entry for the original symptom and repro.

---

### Error handling

Covered inline above for the GraphQL resolver and selector (see the error-path table under API contracts).
Summarizing the delta from Run 1's Error Handling Strategy table:

| Condition | Layer | Behavior |
|---|---|---|
| Empty/missing `sku` GraphQL argument | Resolver | `GraphQlInputException`, category `graphql-input` |
| No usable stock for any reason (unknown SKU, zero batches, all expired, all inactive, bad `sourceCode`) | Selector → Resolver | **Not an error.** `available_qty: 0, batches: []` |
| Infrastructure failure inside the selector/collection (DB down, etc.) | Selector → Resolver (uncaught) | Propagates as an uncaught exception; Magento's GraphQL error formatter reports a generic `graphql`-category error. Not specially handled — consistent with `error-handling.md`: don't catch what you can't meaningfully convert. |

No new `LocalizedException`, `CouldNotSaveException`, or `CouldNotDeleteException` paths are introduced by
this run — `getAvailableBatches()` is read-only and the admin UI fixes in this run (data provider, form XML,
requirejs mixin) are presentation-layer corrections to Run 1's existing CRUD flow, not new error-producing
operations.

---

### Threat model

Not applicable — `flow_type=backend-feature`, not `security-patch`. Section included per output format,
omitted in substance. (For the record: the GraphQL query is read-only, unauthenticated by design and
parity-justified above, and introduces no new write path, no new SQL construction outside the existing
collection/resource-model layer, and no new admin ACL surface — nothing here meets the bar for a dedicated
threat-model writeup, and Architecture did not flag this as security-sensitive.)

---

### Test coverage — gap analysis (what Build must still produce)

Verified directly against `Test/**` on disk (no assumptions):

1. **`Model/Resolver/InventoryStock.php` has zero test coverage.** No `Test/Unit/Model/Resolver/` directory
   exists at all. Build must add
   `app/code/vendor/Rgd/Inventory/Test/Unit/Model/Resolver/InventoryStockTest.php` covering, at minimum:
   - Missing `sku` key in `$args` → `GraphQlInputException` with the exact message `"sku" is required.`
   - Empty-string and whitespace-only `sku` → same exception (confirms the resolver-level `trim()` check,
     not just schema-level non-null).
   - `sourceCode` omitted from `$args` → selector is called with `'default'`.
   - `sourceCode` explicitly provided → selector is called with that exact value (assert via mock
     expectation on the argument, not just the return value).
   - Happy path: selector returns a mix of batches → resolved array has correct `sku`, `available_qty` (sum
     of `getRemainingQty()` across all returned batches), and `batches[]` mapped with the exact key names
     `batch_number`, `expiry_date`, `available_qty`, `received_at` sourced from `getBatchNumber()`,
     `getExpiryDate()`, `getRemainingQty()`, `getCreatedAt()` respectively.
   - Selector returns `[]` → `available_qty: 0`, `batches: []` (not an exception — this is the behavior the
     whole "no usable stock is not an error" contract depends on; must be asserted at the resolver level,
     not only at the selector level).
   Mock `FefoBatchSelectorInterface` directly (constructor DI, one dependency) — no need to reach into the
   collection/resource-model layer for this test.

2. **`FefoBatchSelector::getAvailableBatches()` — mostly covered, one gap.** Verified against
   `FefoBatchSelectorTest.php` (it does exist and was already extended on this branch, +58 lines): three
   tests already exercise `getAvailableBatches()` directly —
   `testGetAvailableBatches_ReturnsCandidatesInFefoOrder`,
   `testGetAvailableBatches_NoUsableStock_ReturnsEmptyArray`, and
   `testGetAvailableBatches_DoesNotThrowOnEmptyStock`. These correctly assert FEFO ordering, empty-array
   return, and the never-throws contract *through the `getAvailableBatches()` entry point itself*.
   **Gap:** the "expires today = already expired" cutoff and the "NULL-expiry sorts last" ordering rule are
   only asserted through `selectForDeduction()`'s tests
   (`testSelectForDeduction_BatchExpiringToday_IsExcludedFromCandidates`,
   `testSelectForDeduction_BatchExpiringTomorrow_IsIncluded`,
   `testSelectForDeduction_NullExpiryBatch_SortsLastAfterDatedBatches`) — there is no equivalent assertion
   run through `getAvailableBatches()`'s own entry point, even though both methods share the same private
   `applyCandidateFilterAndFefoOrder()` helper today. This is low risk right now (shared code path), but it
   is not defended against a future refactor that accidentally forks the two methods' filtering logic.
   Build should add at least one `getAvailableBatches()`-specific test asserting the today-expires cutoff
   (e.g. a `testGetAvailableBatches_BatchExpiringToday_IsExcluded` mirroring the existing
   `wireAvailableBatchesCollection()` helper pattern already in the test file).

3. **`InventoryStockTest` integration-level coverage (nice-to-have, not blocking).** No integration test
   exercises the actual GraphQL schema (query parsing, `@cache(cacheable: false)` wiring, real DB round
   trip). `Test/Integration/` currently contains only a `.gitkeep`. Given the module's existing test
   strategy is unit-only so far (Run 1 shipped with `Test/Unit/**` only), this spec does not block on an
   integration test for this run, but flags it as a natural Phase-2/hardening item — particularly to catch
   schema-stitching mistakes (e.g. a typo in the `@resolver` class path) that a unit test cannot catch.

4. **Admin UI fixes (`BatchListingDataProvider`, form/listing XML, requirejs mixin) — verified manually,
   not test-covered.** Per BUGS.md, these were verified via `setup:static-content:deploy` +
   `curl` + manual browser console check, not automated tests. Build should treat these as "done, working,
   needs regression coverage" rather than "build from scratch" — no PHP unit test currently exists for
   `BatchListingDataProvider` (it has no logic beyond constructor wiring, so a unit test would mostly assert
   the collection is set — low value but cheap; Build's call whether to add one). The XML/JS changes are not
   unit-testable in this module's current test setup; the practical regression guard is the integration test
   noted in point 3, once it exists — until then, re-verify manually (per BUGS.md's verification steps) if
   these files are touched again.

---

### Open questions

1. **`module.xml` sequence hygiene — optional, not required.** Architecture flagged adding
   `Magento_GraphQl` / `Magento_GraphQlCache` to `<sequence>` in `etc/module.xml` as optional. Checked
   against core precedent: modules that use `@resolver`/`@cache` directives in their own `schema.graphqls`
   (e.g. `Magento_CustomerGraphQl`, `Magento_QuoteGraphQl`) do **not** declare `Magento_GraphQl` in their own
   `<sequence>` — GraphQL schema stitching does not depend on `module.xml` load order the way DI
   compilation does, and the `@cache` directive is resolved from core's own schema regardless of this
   module's declared sequence. Recommendation: skip it — adding it would be harmless but has no core
   precedent and no functional effect. Build's call if there's a reason specific to this deployment to add
   it anyway.
2. **README.md does not yet mention the `@cache(cacheable: false)` directive.** The existing "GraphQL API"
   section in `README.md` documents the query shape, unauthenticated access, and the expiry-cutoff rule, but
   was written before this directive was added. Not blocking — a one-line addendum, left to Build/whoever
   next touches the README rather than this spec (Eng does not edit documentation beyond the spec itself,
   per this module's own conventions for who owns README updates).
3. **No SKU-exists-in-catalog validation anywhere in this path** — inherited from Run 1's already-documented
   gap ("No SKU-exists validation on batch save," README Known Limitations). This run's GraphQL query
   inherits the same posture: a typo'd or nonexistent SKU returns the same `available_qty: 0, batches: []`
   shape as a real, legitimately out-of-stock SKU, with no way for a client to distinguish "wrong SKU" from
   "right SKU, no stock." Flagged for Product awareness, not a defect in this spec — matches the requirement
   doc's explicit instruction not to introduce new out-of-stock/availability behavior.
4. **Grid data-provider architecture change deserves explicit QA re-verification.** The listing's data
   source class changed from the generic SearchCriteria-based `DataProvider` to the new collection-based
   `BatchListingDataProvider` (see Service boundaries). This fixed the reported bug, was manually verified,
   but has no automated regression test (see Test coverage point 4) — QA should explicitly re-exercise grid
   sorting, filtering (all six filter types per the listing), and pagination end-to-end before this run
   ships, since the underlying data-fetch mechanism, not just the requirejs collapse bug, changed.

---

Spec file: `docs/spec-batch-based-inventory-management-run2.md`

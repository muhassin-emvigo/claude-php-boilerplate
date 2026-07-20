# Progress — customer-registration-dynamic-customer-group-configuration-and-order-approval

Requirement: `docs/requirements/2026-07-13-customer-registration-dynamic-customer-group-configuration-and-order-approval.md`
Pipeline: `/full-flow` (full 16-agent pipeline, gates stop for explicit approval)
Started: 2026-07-13

- [x] 1. Classifier — `full`, branch `feature/customer-registration-approval`
- [x] 2. CEO — brief written, **APPROVED with corrections** (Q-001/Q-002/payment gateway resolved, see below)
- [x] 3. Architecture — **APPROVED** (self-selected group + Razorpay dev-mode confirmations received) — module `Rgd_CustomerCompliance`
- [x] 4. Eng — technical spec written directly (acceptEdits, no gate) → `docs/spec-customer-registration-dynamic-customer-group-configuration-and-order-approval.md`. Resolved: dropped the unused `dependency` column from v1 schema; group-selection UX = pre-serialized field defs + Knockout show/hide (no AJAX round-trip).
- [x] 5. Design — **APPROVED** (dropdown group selector, plain native date input, auto-disable Registration-Fields-Enabled when Approval-Required is off, Reject routes to full detail page requiring notes, **+ new requirement: rejection reason must be surfaced to the customer**, not just recorded internally) → `docs/design-compliance-registration.md` + `docs/design-tokens-compliance-registration.md`
- [x] 6. Build — **DONE, with known gaps — see Stage 6 summary below.** 162 files written and committed to `app/code/Rgd/CustomerCompliance/` on your machine. **Not yet compiled/tested** — see honest status note below before proceeding.
- [ ] 7. Unit Testing — drafted (5 PHPUnit test files included in Build output) but not yet executed
- [ ] 8. QA/Scenario Testing — not yet run
- [ ] 9. Security Testing — not yet run
- [ ] 10. Performance Testing — not yet run
- [ ] 11. Bug Fixing — conditional, not yet evaluated
- [ ] 12. Code Review — not yet run
- [ ] 13. Approver — not yet run
- [ ] 14. ADR — conditional, not yet evaluated
- [ ] 15. Documentation — not yet run
- [ ] 16. PR — not yet run

---

## Stage 1 — Classifier output

```
<task_size>full</task_size>
<branch>feature/customer-registration-approval</branch>
<reason>New module/system boundary spanning dynamic customer-group configuration, secure document storage, and an order-approval state machine that crosses Customer, Sales, and Email domains — cross-cutting, security-sensitive, and requires design decisions with multiple viable approaches.</reason>
```

## Stage 2 — CEO task brief (approved with corrections)

**Problem:** Magento Admins need to define per-customer-group dynamic registration fields and document requirements, and customer orders must be held in a "Pending Verification" state — with document review, approval/rejection, notification, and refund-on-rejection — before regulated products can be fulfilled.

**Acceptance criteria:** AC-001 through AC-008 (group-scoped dynamic fields incl. file/image upload with required-flag validation; registration persists customer+documents+audit+email; order placed under an approval-required group enters custom "Pending Verification" state, blocked from fulfillment until approved; admin approve/reject transitions with email + audit; reject triggers refund; documents ACL-protected; Admin CRUD UI + versioned REST API; reporting/audit grids; ≥80% PHPUnit coverage on business logic). Full text in original brief message.

**Out of scope:** Government API verification; OCR/AI document validation; cross-field conditional-visibility engine (see Q-001 resolution below — the actual need is already in scope); multi-store/multi-website group scoping unless later specified; mobile GraphQL app UI (fast-follow).

**Approach (selected):** Fully custom, module-owned declarative-schema tables (not EAV, not JSON blobs) — see Architecture stage for the finalized schema.

**Flow type:** `backend-feature` (with real admin UI + storefront UI components)

### Stakeholder-confirmed resolutions (2026-07-13)

1. **Q-001 (conditional/group-wise field configuration) — RESOLVED, in scope as originally planned.** Stakeholder confirmed the need is: a field's required flag is configurable per customer group (e.g., a document field is mandatory only if admin marked it mandatory for that group). This is fully satisfied by the group-scoped `field.is_required` column already in the schema design — **no additional cross-field conditional-visibility engine** ("show field B only if field A = X") is needed. A reserved, unused `dependency` column stays in the schema purely as a future forward-compat hook.
2. **Q-002 (re-upload after rejection) — CONFIRMED necessary.** Resubmission path required: customer can re-upload documents after rejection; new document version linked to the same customer/order, prior version retained for audit (never deleted).
3. **Payment gateway — Razorpay (official Magento module).** Researched: composer package `razorpay/magento` (Packagist), latest stable **v4.2.2** (released 2026-05-28), requires PHP `7.*|^8.1` and `razorpay/razorpay ^2.0`. **Not currently installed in this codebase** — checked `vendor/`, only `magento/module-braintree` and `magento/module-paypal` core integrations present. The module's webhook handling references a `payment.authorized` event (supports authorize-before-capture), but documentation does not explicitly confirm native Magento "Online Refund" (API-triggered credit memo refund) support — this needs empirical verification once installed. **Architecture decision:** refund is abstracted behind our own `Api\OrderRefundServiceInterface` + `RefundStrategyInterface`, with a `RazorpayOnlineRefundStrategy` (used if the installed module supports it) and an `OfflineFallbackRefundStrategy` (manual-refund-flag path) — so the design works correctly regardless of which capability the installed version turns out to have. **Action item for Build stage:** `composer require razorpay/magento` needs to be run (new dependency, not yet in composer.json).

**Risks carried forward:** payment capture/refund timing default (authorize-on-placement, capture-on-approval) still needs confirmation once Razorpay's actual admin config options are inspected; "unlimited" file-upload fields is a storage/abuse-vector risk needing rate limits + size caps + server-side MIME sniffing (flagged for Security Testing stage); field-definition versioning strategy for "admin edits fields after customers exist" is now designed in Architecture (§2.2 version/is_active columns).

---

## Stage 3 — Architecture output (module: `Rgd_CustomerCompliance`)

**Vendor namespace decision:** Use `Rgd` (matching the existing `Rgd\Inventory` module already in this repo) rather than the boilerplate's placeholder `vendor`/`CustomShipping`. Module: `Rgd_CustomerCompliance`, PHP namespace `Rgd\CustomerCompliance`, table prefix `rgd_customercompliance_`.

### Database schema (declarative, `etc/db_schema.xml`)
Six tables: `rgd_customercompliance_group_config` (per-group config, is_approval_required), `rgd_customercompliance_field` (field defs, group-scoped `is_required`, type, options, file constraints, reserved `dependency` col, `version`/`is_active` for edit-after-data-exists edge case), `rgd_customercompliance_field_value` (customer × field values), `rgd_customercompliance_document` (secure file metadata, `version`/`is_current` for resubmission), `rgd_customercompliance_order_approval` (order_id, status, reviewer, refund_status/reference, resubmission_count), `rgd_customercompliance_audit_log` (append-only, no FKs so it outlives referenced rows). Full column/index/FK detail in the architecture agent transcript (available on request) — key point: Q-001 is satisfied entirely by `field.is_required` being group-scoped; no extra engine needed.

### Service contracts (`Api/`)
Data DTOs for all 6 entities; repositories (`GroupConfigRepositoryInterface`, `FieldRepositoryInterface`, `DocumentRepositoryInterface`, `OrderApprovalRepositoryInterface`); domain services (`RegistrationComplianceServiceInterface`, `FieldConfigProviderInterface` — shared by storefront + REST, `OrderApprovalServiceInterface`, `DocumentStorageServiceInterface`, `DocumentResubmissionServiceInterface`, `OrderRefundServiceInterface` + `RefundStrategyInterface`, `AuditLoggerInterface`). These services are the ≥80% coverage target for Stage 7.

### Integration points (Plugin vs Observer decisions)
- Order placement → hold for verification: **Plugin** (after, on `OrderManagementInterface::place`) — must alter placement outcome synchronously.
- Custom "Pending Verification" state/status: declarative **`etc/sales.xml`**, no code.
- Block fulfillment while pending: **Plugin** (before/around on `ShipOrderInterface`/`InvoiceOrderInterface`) throwing `LocalizedException`.
- Registration field validation (must block on missing required): **Plugin/controller intercept** (can throw).
- Registration field/document persistence: **Observer** on `customer_register_success` (textbook Observer use case — reacting to a completed event, not altering it).
- Approve/reject side effects (email, audit, refund): owned by our own `OrderApprovalServiceInterface`, which dispatches our own custom events for extensibility — nothing to plug into on core here.

### Razorpay refund abstraction
`Api\OrderRefundServiceInterface` → `RefundStrategyInterface` selected by a `di.xml` virtual-typed pool: `RazorpayOnlineRefundStrategy` (used only if the installed module supports native online refund — soft dependency via composer `suggest`, runtime capability check, no hard `require`) with `OfflineFallbackRefundStrategy` as guaranteed fallback (creates offline credit memo, flags `refund_status=manual_fallback`, audit entry). Module compiles/functions with or without `razorpay/magento` installed. **Eng-stage action:** once installed, inspect its `Model/` for an online-refund/credit-memo implementation and wire the strategy accordingly.

### Secure document storage
Files under `var/customercompliance/{customer_id}/{document_id}/` (outside `pub/`), streamed only through ACL-guarded controllers, every access audit-logged. Upload validation: extension allowlist + MIME sniff + max size + filename sanitization + sha256 checksum.

### ACL structure
Root `Rgd_CustomerCompliance::compliance` with child resources: `group_config`, `field`, `approvals` (+ `approve`, `reject` sub-resources so refund authority is segregated from read access), `documents` (separate, PII-sensitive), `audit`, `config`.

### Admin UI
4 grids (group configs, fields, **Pending Verification** review with inline approve/reject, audit log) + 3 forms (group config, field editor, order-approval detail/review), modeled on the existing `vendor/Rgd/Inventory` Ui/Component + Ui/DataProvider pattern.

### REST API (`webapi.xml`, versioned `/V1/`)
CRUD for group-configs and fields, approvals list + approve/reject actions, document metadata list — all ACL-guarded. GraphQL scaffolding possible later but out of scope for this build.

### `module.xml` sequencing
```xml
<sequence>
  <module name="Magento_Customer"/>
  <module name="Magento_Sales"/>
  <module name="Magento_Email"/>
</sequence>
```
**No dependency on `Rgd_Inventory`** — no shared tables/contracts/runtime path found; sequencing on it would be a false coupling. Revisit only if a future requirement links approval to inventory reservation release.

### File/directory plan
Full tree modeled on `vendor/Rgd/Inventory`'s shape: `Api/` (+`Data/`), `Model/` (+`Service/`, `Refund/`, `ResourceModel/`), `Plugin/`, `Observer/`, `Controller/Adminhtml/*` + storefront `Controller/Document/Download.php`, `ViewModel/Registration/DynamicFieldsProvider.php`, `Ui/Component`+`Ui/DataProvider`, `etc/` (module.xml, db_schema.xml(+whitelist), di.xml, acl.xml, events.xml, sales.xml, webapi.xml, email_templates.xml, adminhtml/*, frontend/*), `view/adminhtml` + `view/frontend`, `email/*.html`, `i18n/en_US.csv`, `Setup/Patch/Data/InitDefaultGroupConfigPatch.php`, `Test/Unit` + `Test/Integration`.

### Go/No-Go
**GO — proceed to Eng (Stage 4).** Two non-blocking items carried forward: (1) empirical Razorpay online-refund capability check once installed, (2) confirm registration group-selection UX (default vs self-selected group) with stakeholder before form build — design already handles both cases via the ViewModel + success-observer re-validation split.

*(Full verbatim architecture agent output available — ask if you want the complete unabridged version with all column-level DDL detail included in this file.)*

## Stage 5 — Design output (module: `Rgd_CustomerCompliance`)

Baseline: stock Magento Luma (storefront) + stock Admin theme (Ui/Component), matching the existing `Rgd_Inventory` grids — no new component library introduced.

**Storefront (11 components):** Compliance Group Selector (native `<select>`, shown first, hides entirely if zero eligible groups — never a broken empty selector); Dynamic Field Block with a 200ms fade+slide reveal on group change (respects `prefers-reduced-motion`); one component per field type (text/textarea/number/email/phone/date, dropdown/radio/checkbox, file upload, image upload w/ thumbnail); form-level validation summary; a new "My Compliance Documents" My Account section (current version only); a dedicated Resubmit Documents page for post-rejection re-upload, showing the admin's decision notes for context.

**Admin (8 components):** the 4 grids + 3 forms from Architecture, plus one new shared Status Pill component. Two new visual elements only: the status pill itself, and a refund-failed (red) vs. offline-fallback (amber) tint pair — deliberately different colors since one means "the system failed" and the other means "the system correctly deferred to manual handling." The Approval Detail view uses validate-on-submit rather than pre-disabling Approve/Reject (reject without notes surfaces an inline error instead of a disabled button). Admin's field-type-driven form reveal is intentionally NOT animated (contrast with the storefront's animated reveal — admin power-users want instant, jank-free toggling).

Every component has all 7 states specified (default/hover/focus/disabled/loading/error/empty), with explicit "N/A + reason" where a state doesn't apply. Full accessibility annotations (contrast ratios, focus order, ARIA roles) on every component — color is never the sole signal anywhere (status pills, field errors, refund states all pair color with text).

**New design tokens (10):** a unified focus-ring color for the storefront compliance section, a dashed drop-zone border style, a pending-verification pill color pair, the refund-failed color (which reuses Luma's existing error red under an Admin-scoped name), the offline-fallback color pair, and the 200ms/-8px reveal-transition values. No conflicts with existing Luma/Admin tokens.

**4 items flagged for a quick confirmation (non-blocking):**
1. Group Selector: recommended `<select>` over radio buttons — confirm.
2. Date field: reuse an existing datepicker already used elsewhere in the codebase, or plain native `<input type="date">`? Needs a quick look at what's already loaded on this page.
3. Does "Registration Fields Enabled" depend on "Approval Required" being on, or are they independent toggles?
4. Inline "Reject" on the Pending Verification grid should route to the full Approval Detail view rather than reject in-place, since rejection requires notes text a grid row can't capture — Approve can stay a one-click inline action.

*(Full unabridged design doc + token table available — ask if you want the complete unabridged version attached here.)*

## Stage 6 — Build output (module: `Rgd_CustomerCompliance`)

**162 files generated** by 9 parallel build agents (naming contract front-loaded from the Architecture/Eng specs to keep cross-agent references consistent), then delivered and **committed onto your machine** at `app/code/Rgd/CustomerCompliance/` under `C:\xampppp\htdocs\rgd_dental\`. Directory listing on your machine confirms the full tree landed intact (`Api/`, `Block/`, `Controller/`, `Model/`, `Observer/`, `Plugin/`, `Setup/Patch/Data/`, `Test/Unit/`, `Ui/`, `ViewModel/`, `view/adminhtml/`, `view/frontend/`, `etc/`, `email/`, `i18n/`, `registration.php`, `composer.json`).

Confirmed implemented per your latest design answers: dropdown group selector, plain native `<input type="date">`, "Registration Fields Enabled" tied to "Approval Required," Reject routes to the full Approval Detail view (notes required, no inline reject), and **the rejection reason is surfaced to the customer** — both on the storefront order-view page (`Block/Order/ApprovalStatus.php` + its template, showing `Reason: {decision notes}` plus a Resubmit-documents link) and in the `order_rejected` transactional email template.

**Known functional gaps self-flagged by the build agents (not yet fixed):**
1. No `Plugin\Customer\RegistrationValidationPlugin` — required-field validation currently only happens *after* account creation via the Observer, not pre-emptively blocking registration submission.
2. The Pending-Verification grid's Approve action targets a single-id controller; it doesn't yet handle Magento's massaction `selected[]` array payload for bulk approve.
3. `OrderApprovalDataProvider` doesn't join in submitted field values / documents yet, so the Approval Detail view's "submitted data" section is currently blank placeholder content.
4. `field_form.xml` has no input for the `field_code` column, despite it being `NOT NULL` + unique in the schema — admins currently cannot set it from the UI.
5. Email templates exist in two locations: `email/*.html` (module root, non-standard) and `view/frontend/email/*.html` (Magento's actual resolution path). Both were committed harmlessly; the module-root copies should be deleted as cleanup — they are dead weight, not a functional bug.
6. `razorpay/magento` is listed only as a composer `suggest` (per the abstraction design) — it has **not actually been composer-installed**, so `RazorpayOnlineRefundStrategy`'s real refund-capability is still unverified against the live extension.

**Honest status — please read before I continue:** the device shell bridge (`device_bash`) has not come online at any point in this session, so **none of this code has been executed** — no `composer install`, no `bin/magento setup:upgrade`/`setup:di:compile`, no PHPUnit, no PHPCS/PHPStan static analysis. Everything above is a desk-level (LLM-authored, code-reviewed-by-construction) build, not a verified one. Since you chose "stop at every gate," I'm pausing Stage 6 here rather than quietly rolling into Testing/Security/Review stages on unexecuted code. Options going forward: (a) wait for the shell bridge to come back and let me run the verification commands myself, (b) you run `composer install && bin/magento setup:upgrade && bin/magento setup:di:compile && vendor/bin/phpunit` yourself on your machine and paste back any errors, or (c) I proceed with desk-review-style Testing/Security/Review passes now, explicitly flagged as not equivalent to real execution, while gap items 1-6 above get fixed in parallel.

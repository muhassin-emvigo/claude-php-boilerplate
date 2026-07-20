## Technical spec — customer-registration-dynamic-customer-group-configuration-and-order-approval

Module: `Rgd_CustomerCompliance` — Magento 2.4.7-p8, PHP 8.2+, `declare(strict_types=1)`, declarative schema, constructor injection only. Assumed Build-stage environment: Magento **developer mode** (auto-recompile; `setup:upgrade` still required after schema/patch changes, `setup:di:compile` not required per-change). This stage is planning-only — no shell/composer access assumed.

---

### API contracts

All REST routes are versioned under `/V1/customer-compliance/`, defined in `etc/webapi.xml`, each with an ACL `<resource ref="...">`. All list endpoints accept `searchCriteria` and return a `*SearchResultsInterface` (items + `search_criteria` + `total_count`). All DTOs live in `Api/Data/`; service interfaces in `Api/`. Request/response bodies are JSON. `SearchCriteriaInterface` is used for every list.

Auth model:
- Admin-facing routes: `<resource ref="Rgd_CustomerCompliance::<child>"/>` — Magento admin token / session with ACL check → **403** on ACL failure, **401** on missing/invalid token.
- Storefront self-service routes: `<resource ref="self"/>` (authenticated customer, `%customer_id%` bound from token). A customer may only read their own compliance data → **403** on cross-customer access.
- Registration itself happens pre-auth via the standard Magento customer create flow (form controller + observer), NOT a bespoke REST write endpoint; the REST field endpoints below are read-only providers plus admin CRUD.

#### Global error envelope
Magento webapi standard shape reused for every error:
```
{ "message": "Human template with %params", "parameters": {...}, "trace": "<dev-mode only>" }
```
HTTP status conventions applied uniformly:
- **400** — malformed request / missing required param / bad type (`InputException`).
- **401** — no/invalid auth token (framework-level).
- **403** — authenticated but ACL/ownership denied (`AuthorizationException`).
- **404** — entity id not found (`NoSuchEntityException`).
- **409** — state conflict; illegal transition (approving an already-approved/rejected order) (`StateException` mapped to 409 via custom `webapi` exception mapping).
- **422** — request well-formed but violates a business rule (reject without notes, resubmission when order not in rejected state, group has no active config) (`LocalizedException` subclass `ComplianceValidationException` mapped to 422).
- **500** — unexpected (storage failure, gateway transport error not otherwise mapped).

Because Magento's default webapi maps most `LocalizedException` to 400, the spec **requires** an `exception_map` in `etc/webapi.xml`-adjacent DI (`Magento\Framework\Webapi\ErrorProcessor` is not extensible per-code; instead we throw framework exceptions whose `httpCode` is honored): use `Magento\Framework\Exception\StateException` (409) and a custom `Rgd\CustomerCompliance\Exception\BusinessRuleException extends LocalizedException` that sets `protected $httpCode = 422` and is registered so `ErrorProcessor::getHttpCode()` returns it. This is called out in Open Questions as the one framework-mapping risk.

#### Route list

**1. Group configs (admin)**
| Method | Route | ACL | Success | Errors |
|---|---|---|---|---|
| GET | `/V1/customer-compliance/group-configs` | `::group_config` | 200 `GroupConfigSearchResultsInterface` | 401,403 |
| GET | `/V1/customer-compliance/group-configs/:configId` | `::group_config` | 200 `GroupConfigInterface` | 401,403,404 |
| POST | `/V1/customer-compliance/group-configs` | `::group_config` | 200 `GroupConfigInterface` | 400,401,403,409(dup customer_group_id),422 |
| PUT | `/V1/customer-compliance/group-configs/:configId` | `::group_config` | 200 `GroupConfigInterface` | 400,401,403,404,422 |
| DELETE | `/V1/customer-compliance/group-configs/:configId` | `::group_config` | 200 `bool` | 401,403,404,409(config referenced by pending approvals) |

Interface: `GroupConfigRepositoryInterface::save(GroupConfigInterface $c): GroupConfigInterface; getById(int $id): GroupConfigInterface; getByCustomerGroupId(int $groupId): GroupConfigInterface; getList(SearchCriteriaInterface $sc): GroupConfigSearchResultsInterface; delete(GroupConfigInterface $c): bool; deleteById(int $id): bool;`

409 on POST when a `group_config` row already exists for that `customer_group_id` (unique constraint). 409 on DELETE when `order_approval` rows in `pending_verification` reference orders of that group (soft guard — see Error handling).

**2. Fields (admin CRUD + storefront/read provider)**
| Method | Route | ACL | Success | Errors |
|---|---|---|---|---|
| GET | `/V1/customer-compliance/fields` | `::field` | 200 `FieldSearchResultsInterface` | 401,403 |
| GET | `/V1/customer-compliance/fields/:fieldId` | `::field` | 200 `FieldInterface` | 401,403,404 |
| POST | `/V1/customer-compliance/fields` | `::field` | 200 `FieldInterface` | 400,401,403,404(config),409(dup field_code within config),422(bad options_json for dropdown/radio; bad allowed_extensions for file/image) |
| PUT | `/V1/customer-compliance/fields/:fieldId` | `::field` | 200 `FieldInterface` | 400,401,403,404,422 |
| DELETE | `/V1/customer-compliance/fields/:fieldId` | `::field` | 200 `bool` | 401,403,404 |
| GET | `/V1/customer-compliance/fields/for-group/:customerGroupId` | `self` OR `::field` | 200 `FieldInterface[]` | 401,404(no active config for group) |

`fields/for-group` is the read-only projection of `FieldConfigProviderInterface::getFieldsForGroup(int $customerGroupId): FieldInterface[]` — the single source of truth shared by storefront JS, the registration validator, and REST. It returns only `is_active=1` fields for the current `version` of that group's config, sorted by `sort_order`. Public-safe: never emits internal ids beyond `field_id`, never emits stored customer values.

**3. Eligible groups (storefront, pre-auth read)**
| Method | Route | ACL | Success | Errors |
|---|---|---|---|---|
| GET | `/V1/customer-compliance/eligible-groups` | `anonymous` | 200 `EligibleGroupInterface[]` | 500 |

Returns groups with an active `group_config` (`is_registration_fields_enabled=1`) for the registration selector: `{ customer_group_id, group_label, is_approval_required }`. `group_label` resolved from `Magento\Customer\Api\GroupRepositoryInterface`. Marked `anonymous` because it is needed on the pre-login registration page; it exposes only non-sensitive group labels/flags, no field values. This is deliberately the *only* anonymous route.

**4. Approvals (admin)**
| Method | Route | ACL | Success | Errors |
|---|---|---|---|---|
| GET | `/V1/customer-compliance/approvals` | `::approvals` | 200 `OrderApprovalSearchResultsInterface` | 401,403 |
| GET | `/V1/customer-compliance/approvals/:approvalId` | `::approvals` | 200 `OrderApprovalInterface` | 401,403,404 |
| POST | `/V1/customer-compliance/approvals/:approvalId/approve` | `::approvals::approve` | 200 `OrderApprovalInterface` | 401,403,404,409(not in pending_verification),422 |
| POST | `/V1/customer-compliance/approvals/:approvalId/reject` | `::approvals::reject` | 200 `OrderApprovalInterface` | 400(missing notes body),401,403,404,409(not pending),422(refund precondition/empty notes after trim) |

Approve/reject bodies:
```
approve: { "decisionNotes": "string|optional" }
reject:  { "decisionNotes": "string, REQUIRED, non-empty after trim, ≤2000" }
```
Interface: `OrderApprovalServiceInterface::holdForVerification(int $orderId): void; approve(int $approvalId, int $adminUserId, ?string $notes): OrderApprovalInterface; reject(int $approvalId, int $adminUserId, string $notes): OrderApprovalInterface;`

Error path detail:
- **409** approve/reject: current `status !== 'pending_verification'`. Enforced with an atomic UPDATE guard (`WHERE status='pending_verification'`, affected-rows check) to prevent two admins double-deciding a race.
- **422** reject: `trim(notes) === ''`.
- Reject triggers refund asynchronously-safe path (see Error handling); a refund *failure* does NOT roll back the rejection — approval row transitions to `status=rejected, refund_status=failed`, surfaced in grid for manual retry (retry route below).

| POST | `/V1/customer-compliance/approvals/:approvalId/retry-refund` | `::approvals::reject` | 200 `OrderApprovalInterface` | 401,403,404,409(refund_status not in {failed,pending}),422 |

**5. Documents (admin + owner storefront, metadata + secure download)**
| Method | Route | ACL | Success | Errors |
|---|---|---|---|---|
| GET | `/V1/customer-compliance/documents` | `::documents` | 200 `DocumentSearchResultsInterface` | 401,403 |
| GET | `/V1/customer-compliance/documents/:documentId` | `::documents` OR `self`(owner) | 200 `DocumentInterface` (metadata only) | 401,403,404 |
| GET | `/V1/customer-compliance/documents/:documentId/download` | `::documents` OR `self`(owner) | 200 stream (see below) | 401,403,404,410(superseded version if `is_current=0` and policy=deny) |

Download is NOT a plain JSON webapi response — file bytes must not transit the JSON serializer. The webapi route returns a **short-lived signed URL** `{ "url": "...", "expiresAt": "ISO8601" }` from `DocumentStorageServiceInterface::getSecureUrl(int $documentId): string`; the actual byte stream is served by an ACL-guarded admin controller / customer-account controller calling `streamDownload(int $documentId): void` (which sets headers + `Magento\Framework\App\Response\Http\FileFactory`). Rationale: keeps binary out of REST envelope, enforces auth at stream time, supports Range not required. Interface: `DocumentStorageServiceInterface::store(int $customerId, int $fieldId, ?int $orderId, UploadedFileDto $file): DocumentInterface; getSecureUrl(int $documentId): string; streamDownload(int $documentId): void;`

**6. Audit log (admin, read-only)**
| Method | Route | ACL | Success | Errors |
|---|---|---|---|---|
| GET | `/V1/customer-compliance/audit-logs` | `::audit` | 200 `AuditLogSearchResultsInterface` | 401,403 |

No write route — audit rows are only written by `AuditLoggerInterface::log()` server-side.

Registration submission is intentionally NOT a REST endpoint. It flows through the native Magento `customer/account/createPost` (CSRF form-key validated) → `Observer` on `customer_register_success` → `RegistrationComplianceServiceInterface::processRegistration(int $customerId, array $submittedValues, array $uploadedFiles): void`. Server-side revalidation there is the authoritative gate (see Component tree section).

---

### Data model

Declarative schema `etc/db_schema.xml` + generated `db_schema_whitelist.json`. Table prefix `rgd_customercompliance_`. All tables InnoDB, `utf8mb4`. No raw SQL anywhere. FK to core tables uses core PK columns; FK to `customer_group` uses `customer_group.customer_group_id`. Note Magento core `customer_group_id` is `smallint unsigned`, `customer_entity.entity_id` and `sales_order.entity_id` are `int unsigned` — FK column types must match exactly or the FK creation fails.

**Table `rgd_customercompliance_group_config`**
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `config_id` | int unsigned | no | — | PK, identity |
| `customer_group_id` | smallint unsigned | no | — | FK→`customer_group.customer_group_id` ON DELETE CASCADE |
| `is_approval_required` | smallint(1)/boolean | no | 0 | |
| `is_registration_fields_enabled` | smallint(1) | no | 1 | drives eligible-groups list |
| `created_at` | timestamp | no | CURRENT_TIMESTAMP | |
| `updated_at` | timestamp | no | CURRENT_TIMESTAMP ON UPDATE | |

Indexes: PK(`config_id`); **UNIQUE** `RGD_CC_GROUP_CONFIG_CUSTOMER_GROUP_ID`(`customer_group_id`) — one config per group (backs the POST 409). FK `RGD_CC_GROUP_CONFIG_CUSTOMER_GROUP_ID_CUSTOMER_GROUP` cascade delete.

**Table `rgd_customercompliance_field`**
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `field_id` | int unsigned | no | — | PK, identity |
| `config_id` | int unsigned | no | — | FK→`group_config.config_id` ON DELETE CASCADE |
| `field_code` | varchar(64) | no | — | machine key, `^[a-z][a-z0-9_]{0,63}$` |
| `field_type` | varchar(20) | no | — | enum-in-app: text/textarea/number/email/phone/date/dropdown/radio/checkbox/file/image |
| `label` | varchar(255) | no | — | |
| `is_required` | smallint(1) | no | 0 | per-group required flag |
| `sort_order` | int unsigned | no | 0 | |
| `options_json` | text | yes | NULL | required for dropdown/radio/checkbox; validated JSON array of `{value,label}` |
| `allowed_extensions` | varchar(255) | yes | NULL | csv, file/image only; server enforces allowlist |
| `max_file_size_kb` | int unsigned | yes | NULL | file/image only |
| `version` | int unsigned | no | 1 | config revision; provider reads max version's active rows |
| `is_active` | smallint(1) | no | 1 | soft-disable |
| `created_at` | timestamp | no | CURRENT_TIMESTAMP | |
| `updated_at` | timestamp | no | CURRENT_TIMESTAMP ON UPDATE | |

**`dependency` column — RESOLVED (Q-001): DROP from initial schema.** Rationale: the CEO brief confirms no conditional/cross-field engine is being built and the Architecture doc flags it "[reserved unused]". This repo's conventions mandate declarative schema + whitelist; carrying a nullable column with zero readers and zero writers is dead schema that (a) still forces a whitelist entry, (b) implies a capability that doesn't exist and invites misuse, and (c) provides no forward-compat benefit that a real patch couldn't. Declarative schema makes additive columns cheap and safe later — adding `dependency` when a conditional engine is actually specced is a one-line `db_schema.xml` addition auto-diffed by `setup:upgrade` with no data backfill needed (nullable). Deferring costs nothing; carrying it now is speculative clutter. **Decision: not in v1 `db_schema.xml`; documented in module README as a planned additive column behind a future conditional-visibility feature.**

Indexes: PK(`field_id`); FK `..._FIELD_CONFIG_ID_GROUP_CONFIG` cascade; INDEX(`config_id`,`is_active`,`sort_order`) for provider read; **UNIQUE** `RGD_CC_FIELD_CONFIG_CODE_VERSION`(`config_id`,`field_code`,`version`) backs field POST 409 dup and versioning.

**Table `rgd_customercompliance_field_value`**
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `value_id` | int unsigned | no | — | PK, identity |
| `customer_id` | int unsigned | no | — | FK→`customer_entity.entity_id` ON DELETE CASCADE |
| `field_id` | int unsigned | no | — | FK→`field.field_id` ON DELETE CASCADE |
| `value` | text | yes | NULL | scalar/serialized non-file value; NULL for file fields (points via document_id) |
| `document_id` | int unsigned | yes | NULL | FK→`document.document_id` ON DELETE SET NULL |
| `created_at` | timestamp | no | CURRENT_TIMESTAMP | |
| `updated_at` | timestamp | no | CURRENT_TIMESTAMP ON UPDATE | |

Indexes: PK; UNIQUE(`customer_id`,`field_id`) — one current value per customer per field (upsert on resubmission of scalar); FK ×3; INDEX(`document_id`).

**Table `rgd_customercompliance_document`**
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `document_id` | int unsigned | no | — | PK, identity |
| `customer_id` | int unsigned | no | — | FK→`customer_entity.entity_id` ON DELETE CASCADE |
| `field_id` | int unsigned | no | — | FK→`field.field_id` ON DELETE CASCADE |
| `order_id` | int unsigned | yes | NULL | FK→`sales_order.entity_id` ON DELETE SET NULL; set when doc tied to an order/resubmission |
| `file_name` | varchar(255) | no | — | original client name, sanitized for display only |
| `file_path` | varchar(500) | no | — | relative path under `var/customercompliance/` — never under pub/ |
| `content_type` | varchar(127) | no | — | server-detected MIME, not client-supplied |
| `file_size` | int unsigned | no | — | bytes |
| `version` | int unsigned | no | 1 | increments per resubmission for same (customer,field[,order]) |
| `is_current` | smallint(1) | no | 1 | only one current per (customer,field,order) |
| `checksum` | varchar(64) | no | — | SHA-256 hex of stored bytes; dedupe + integrity |
| `created_at` | timestamp | no | CURRENT_TIMESTAMP | |

Indexes: PK; FK ×3; INDEX(`customer_id`,`field_id`,`is_current`); INDEX(`order_id`); INDEX(`checksum`). `file_path` is stored outside webroot; access only via `streamDownload`. Storage layout: `var/customercompliance/<customer_id>/<field_id>/<version>_<random32>.<ext>` — random suffix prevents path guessing even if a path leaks.

**Table `rgd_customercompliance_order_approval`**
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `approval_id` | int unsigned | no | — | PK, identity |
| `order_id` | int unsigned | no | — | FK→`sales_order.entity_id` ON DELETE CASCADE |
| `customer_id` | int unsigned | no | — | FK→`customer_entity.entity_id` ON DELETE CASCADE |
| `status` | varchar(20) | no | 'pending_verification' | pending_verification/approved/rejected |
| `reviewer_admin_id` | int unsigned | yes | NULL | FK→`admin_user.user_id` ON DELETE SET NULL |
| `decision_notes` | text | yes | NULL | required when status=rejected (app-enforced) |
| `decision_at` | timestamp | yes | NULL | set on approve/reject |
| `refund_status` | varchar(20) | yes | NULL | none/pending/completed/failed/offline_fallback |
| `refund_reference` | varchar(128) | yes | NULL | gateway refund id or offline credit-memo id |
| `resubmission_count` | int unsigned | no | 0 | |
| `created_at` | timestamp | no | CURRENT_TIMESTAMP | |
| `updated_at` | timestamp | no | CURRENT_TIMESTAMP ON UPDATE | |

Indexes: PK; **UNIQUE**(`order_id`) — one approval record per order (idempotent hold via `place` plugin); FK ×3; INDEX(`status`); INDEX(`customer_id`). The unique(`order_id`) makes `holdForVerification` idempotent (INSERT…ON DUPLICATE / catch AlreadyExists) if the place plugin fires twice.

**Table `rgd_customercompliance_audit_log`** — intentionally NO foreign keys (must outlive referenced rows).
| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `log_id` | bigint unsigned | no | — | PK, identity (bigint — high volume) |
| `actor_type` | varchar(20) | no | — | admin/customer/system |
| `actor_id` | int unsigned | yes | NULL | no FK by design |
| `action` | varchar(64) | no | — | e.g. approve/reject/resubmit/document_download/config_change |
| `entity_type` | varchar(64) | no | — | order_approval/document/group_config/field/field_value |
| `entity_id` | varchar(64) | no | — | string to tolerate composite/non-int keys |
| `notes` | text | yes | NULL | |
| `created_at` | timestamp | no | CURRENT_TIMESTAMP | |

Indexes: PK; INDEX(`entity_type`,`entity_id`); INDEX(`action`); INDEX(`created_at`). Append-only in the application layer (no update/delete service methods).

#### Migration / patch plan
Declarative schema handles all table/column/index/FK creation and future diffs via `setup:upgrade` (dev mode). Data changes go through `Setup/Patch/Data/*` implementing `DataPatchInterface`, idempotent (guarded, no reliance on version numbers). Order via `getDependencies()`:

1. **Schema** — `etc/db_schema.xml` (all 6 tables above) + `db_schema_whitelist.json`. No install/upgrade schema scripts (declarative only). No `dependency` column (Q-001).
2. **`Patch/Data/InitAclAndConfig`** (no DB deps) — seed default system config defaults if any; ensures nothing else. Idempotent: checks-before-write.
3. **`Patch/Data/InitSalesOrderStatus`** — insert the `pending_verification` order status→state mapping if `etc/sales.xml` declarative state is not sufficient for the label row in `sales_order_status`/`sales_order_status_state`. Guard: `SELECT` existing status before insert; skip if present. `getDependencies()`: none. (The state/status themselves are declared in `etc/sales.xml`; the patch only backfills the human label row idempotently.)
4. **`Patch/Data/BackfillEligibleGroupConfigs`** (optional, `getDependencies()=[schema present]`) — if stakeholders want zero-config groups pre-created, insert `group_config` rows for named groups; guard on unique(`customer_group_id`). Default: ship EMPTY (admin creates configs in UI) → this patch is a no-op stub unless data is provided, keeping install side-effect-free.

Idempotency approach: every data patch queries current state and no-ops when target rows exist (unique constraints as the backstop); patches never assume first-run. Rollback: `revert()` implemented for InitSalesOrderStatus and Backfill (delete seeded rows by known keys). Uninstall drops tables via declarative schema removal. No down-migrations needed for schema (declarative).

---

### Service boundaries

- **Registration/compliance domain** (`Model/Service`): `RegistrationComplianceServiceInterface`, `FieldConfigProviderInterface`. Boundary: consumes `Magento_Customer` group repo + our repos; produces persisted `field_value`/`document`. Sole authority for server-side field validation — storefront JS and REST both defer to `FieldConfigProviderInterface`.
- **Order-approval domain**: `OrderApprovalServiceInterface`. Boundary: reacts to `Magento_Sales` order place; owns the pending/approved/rejected state machine; emits custom events (`rgd_cc_order_approved`, `rgd_cc_order_rejected`) for side effects (email, refund, audit) so the state machine itself stays free of gateway/email coupling.
- **Document storage**: `DocumentStorageServiceInterface`. Boundary: filesystem under `var/customercompliance/` via `Magento\Framework\Filesystem` (DirectoryList::VAR), never `pub/`. Owns versioning/checksum. Only component that touches bytes.
- **Resubmission**: `DocumentResubmissionServiceInterface`. Boundary: order in `rejected` state → new document version (prior `is_current=0`, retained) + `resubmission_count++`; re-enters `pending_verification`. Depends on storage + approval services.
- **Refund abstraction**: `OrderRefundServiceInterface` + `RefundStrategyInterface` (`RazorpayOnlineRefundStrategy`, `OfflineFallbackRefundStrategy`). Boundary: isolates `razorpay/magento` (v4.2.2, NOT installed) entirely. Strategy chosen at runtime by an empirical capability probe (does the installed Razorpay adapter expose an online-refund method for the order's payment) — in dev mode this probe is a class/method + `canRefund()` check, falling back to offline. Module MUST function with Razorpay absent → `OfflineFallbackRefundStrategy` is the guaranteed-present default; Razorpay strategy is conditionally wired via `di.xml` only when the class exists (a virtual-type/factory guard, since composer dep is not declared as hard require to keep the module installable without Razorpay).
- **Audit**: `AuditLoggerInterface`. Cross-cutting, called by all domains. No FKs; append-only.
- **Integration/plugins** (`Plugin/`): after-`OrderManagementInterface::place` → hold; before/around `ShipOrderInterface`/`InvoiceOrderInterface` → block when approval not `approved`; observer `customer_register_success` → persist. `etc/sales.xml` declares state/status.
- **module.xml** sequences `Magento_Customer`, `Magento_Sales`, `Magento_Email` only. No `Rgd_Inventory` runtime dependency (only its Ui/Component *pattern* is copied).

---

### Error handling

- **Validation (registration)**: `RegistrationComplianceService` re-fetches `getFieldsForGroup(submittedGroupId)` and validates the ACTUAL submitted `group_id` — never a client-supplied field list. Missing required → collect all violations, throw `BusinessRuleException` (422 for REST path) / add form errors + redirect back with session messages (form path). File fields validated: extension allowlist, MIME sniff (`finfo`, not client type), size ≤ `max_file_size_kb`, magic-byte check for image types. On any failure the whole registration compliance persistence is transactional — partial documents are rolled back (DB txn + orphan file cleanup in a `try/finally`).
- **Approval state machine**: illegal transitions → 409 via atomic guarded UPDATE (affected-rows==0 ⇒ throw `StateException`). Reject without notes → 422 before any refund.
- **Refund failures do not roll back rejection**: reject persists first (`status=rejected`), then event → refund. Gateway/transport error ⇒ `refund_status=failed`, audit `refund_failed`, admin grid flag + `retry-refund` route; customer email still sent for the rejection decision, refund notification deferred until success. Offline fallback path sets `refund_status=offline_fallback` with credit-memo id in `refund_reference`.
- **Document access**: every `getById`/`download` enforces ownership (`self` ⇒ `customer_id` must equal token subject) or admin ACL. Superseded (`is_current=0`) docs: admins may download (audit trail); customers get 410/403 per policy (default: 403). Every download writes an `audit_log` `document_download` row.
- **Idempotency**: `holdForVerification` idempotent via unique(`order_id`). Fulfillment-block plugins are read-only guards (throw `LocalizedException` "Order pending verification") — safe to fire repeatedly.
- **Concurrency**: double-admin decision prevented by atomic status guard; double-resubmission prevented by version increment under row lock (`SELECT … FOR UPDATE` on the approval row within the resubmit txn).
- **Framework mapping risk**: custom 409/422 require `StateException`/custom `httpCode`-bearing exception so `ErrorProcessor` emits correct status (see API section + Open Questions).
- **All template/grid output escaped**; all admin controllers ACL-guarded; all POST admin forms CSRF form-key validated.

---

### Component tree / API consumption map (frontend aspects)

**Group-selection UX mechanism — DECISION: pre-serialized field defs + Knockout show/hide (NO per-change network round-trip).**

Justification: the set of eligible groups is small (groups with an active `group_config`), and their field definitions are non-sensitive public metadata (labels/types/required flags/options) — exactly what `fields/for-group` already exposes publicly. Serializing all eligible groups' field defs into the registration page via a **ViewModel** (`Rgd\CustomerCompliance\ViewModel\RegistrationFields` implementing `ArgumentInterface`, calling `FieldConfigProviderInterface` for each eligible group) and toggling field blocks client-side gives instant re-render on group change with zero latency, no loading state, works offline-of-network, and avoids an AJAX endpoint that would need anonymous exposure of the same data anyway. The AJAX-on-change alternative is rejected: it adds a network round-trip per selection, a spinner/error state, and a second anonymous endpoint surface, for data that is already page-cacheable and small. (If a future group count makes payload large, switch to lazy AJAX against the existing `eligible-groups`/`fields/for-group` routes — noted as scaling escape hatch.)

Critical: client-side show/hide is UX only. Server-side `RegistrationComplianceService` re-derives the authoritative field list from the *submitted* `group_id` via `FieldConfigProviderInterface` and validates against that — the serialized page data is never trusted for validation.

Storefront component tree (registration page, extends `Magento_Customer` create form):
- **Reused**: `Magento_Customer/js/model/*`, core `form` template + `Magento_Ui` `form` UiComponent, form-key CSRF, core validation mixins.
- **New**:
  - `ViewModel\RegistrationFields` (PHP, ArgumentInterface) → outputs JSON: `[{groupId, isApprovalRequired, fields:[{fieldId,fieldCode,fieldType,label,isRequired,sortOrder,options,allowedExtensions,maxFileSizeKb}]}]`.
  - `view/frontend/templates/register/dynamic_fields.phtml` — escaped output; hosts the KO scope.
  - `view/frontend/web/js/registration-dynamic-fields.js` (Knockout component): observable `selectedGroupId`; renders group selector (dropdown/radio of eligible groups, shown first), subscribes to change → filters serialized defs → renders matching field block; wires field-type widgets (date picker, file input with client-side ext/size hint mirroring server rules).
  - `view/frontend/web/template/*.html` KO templates per field-type group (scalar / choice / file).
  - `layout: customer_account_create.xml` referencing the block+viewmodel.

API consumption map (storefront):
- Page load (pre-auth): server-side ViewModel (no XHR) provides eligible groups + all field defs. `GET /V1/customer-compliance/eligible-groups` is available as the JS fallback/refresh source but not required on happy path.
- Submit: native `customer/account/createPost` (multipart, form-key) — files + values. No REST write.
- Post-login "My compliance documents" account section: `GET /documents?searchCriteria[customer_id]` (self) + `/documents/:id/download`.

Admin (Ui/Component, modeled on `vendor/Rgd/Inventory`):
- **4 grids** (`Ui/Component/Listing` + `view/adminhtml/ui_component/*_listing.xml`): group_configs, fields, approvals, audit_logs — each ACL-gated, `SearchCriteria`-driven via each `*RepositoryInterface::getList`.
- **3 forms** (`view/adminhtml/ui_component/*_form.xml` + `DataProvider`): group_config form (with nested fields), field form, approval detail/decision form (approve/reject actions, notes required-for-reject enforced client + server, document viewer links to `/documents/:id/download`).
- Admin API consumption: grids/forms call the REST routes / repositories above; approve/reject buttons POST to `approvals/:id/approve|reject`; refund status + `retry-refund` surfaced in approval form.

---

### Open questions

1. **Framework HTTP-status mapping (needs Build-stage verification, not a blocker):** Magento's `Webapi\ErrorProcessor` derives status from the thrown exception. 409 must come from `StateException` and 422 from a custom `LocalizedException` exposing `httpCode=422`. Confirm empirically in dev mode that `ErrorProcessor::getHttpCode()` honors a custom `httpCode` property on a `LocalizedException` subclass in 2.4.7-p8; if not, fall back to `WebapiException` with explicit code. Spec assumes it works; flagged for Build to verify first.
2. **Razorpay refund-capability probe specifics:** exact class/method to probe (`Razorpay\Magento\Model\PaymentMethod::canRefund()` vs adapter capability) can only be finalized against the installed v4.2.2 in dev mode. Strategy selection logic is specced; the concrete probe target is a Build-stage empirical detail. Module must not hard-`require` `razorpay/magento` in `composer.json` (would block install where it's absent) — use a soft/suggest dependency + class-exists guard in `di.xml`.
3. **Superseded-document customer access policy:** default chosen is 403 (customers cannot download old versions; admins can). Confirm with stakeholder if customers should retain read access to their own prior submissions (would change to 200 for owner). Non-blocking; default is the safer choice.
4. **Order↔group binding at place time:** approval hold keys on the customer's group *at order time*. If a customer changes group between registration and ordering, the order's approval requirement follows the order-time group. Assumed correct; confirm no requirement to freeze the registration-time group.

All API error paths are defined; all schema changes have a declarative + patch migration plan; Q-001 resolved (drop `dependency`). No blocking open questions remain — items above are Build-stage empirical confirmations, not undefined contracts.

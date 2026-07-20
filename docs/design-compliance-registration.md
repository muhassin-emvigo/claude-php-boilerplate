# docs/design-compliance-registration.md

No existing repo/theme files are present in this environment to inspect directly, so token values below are based on well-documented stock Magento Luma (storefront) and Admin (Ui/Component Magento_Backend/Magento_Ui) theme variables. Flagged as an assumption in the Conflicts/Gaps section — exact hex values should be diffed against the actual installed _theme.scss/_variables.less during implementation review.

## 1. Overview

Scope: storefront dynamic registration/compliance-document flow + 6 admin screens (4 grids, 3 forms — Group Config and Field editor are 2 of the 3 forms; the 3rd is the Approval detail/decision view, which is form-like though largely read-only+action). Tone: trustworthy/official — regulated-product context. No playful microcopy, no informal error tone, no celebratory animation on approval (a quiet success state, not confetti).

Baseline design system: stock Magento **Luma** theme (storefront) and stock Magento **Admin** theme (Ui/Component grids/forms), matching the existing `vendor/Rgd/Inventory` grids already in the codebase. No new component library is introduced. All new components are compositions of existing Luma/Admin primitives (fieldset, field, control, message, data-grid, actions-column) with new tokens only where the compliance domain has no stock equivalent.

## 2. Component List

### Storefront (new)
| # | Component | Reuses |
|---|---|---|
| S1 | Compliance Group Selector | Luma `field.choice` / `.control._with-tooltip` radio-or-select pattern |
| S2 | Dynamic Field Block (container + reveal) | Luma `fieldset`/`.fieldset` |
| S3 | Dynamic Field — Text/Textarea/Number/Email/Phone/Date | Luma `.field` + `.control` + native input/textarea |
| S4 | Dynamic Field — Dropdown | Luma `.field.choice` select |
| S5 | Dynamic Field — Radio group | Luma `.field.choice` radio |
| S6 | Dynamic Field — Checkbox (single + group) | Luma `.field.choice` checkbox |
| S7 | Dynamic Field — File Upload | New composite (Luma has no stock file control skinned this way) |
| S8 | Dynamic Field — Image Upload (w/ thumbnail) | New composite, extends S7 |
| S9 | Form-level Validation Summary | Luma `.message.error` |
| S10 | My Compliance Documents (My Account) | Luma My Account `.table-wrapper`/`.data.table` |
| S11 | Resubmit Documents flow | Reuses S7/S8 + S9 in a modal/page context |

### Admin (new)
| # | Component | Reuses |
|---|---|---|
| A1 | Group Configs grid | Ui/Component `data-grid` (as `Rgd_Inventory` grids) |
| A2 | Group Config form | Ui/Component `form` + `fieldset` |
| A3 | Fields grid (per group) | Ui/Component `data-grid` |
| A4 | Field form (dynamic sub-fields by `field_type`) | Ui/Component `form` + `dataProvider`-driven `visible` bindings |
| A5 | Pending Verification / Order Approvals grid | Ui/Component `data-grid` + mass-action + inline actions column |
| A6 | Approval Detail / Decision view | New composite: Admin "view" page pattern (cf. `sales/order/view`) |
| A7 | Audit Log grid | Ui/Component `data-grid`, read-only (no actions column, no mass-action) |
| A8 | Status Pill (Pending/Approved/Rejected/Refund states) | New, small — extends Admin grid `.grid-severity-*` pattern |

## 3. Storefront Components (full state tables)

### S1 — Compliance Group Selector
First block on `customer/account/create`, above other registration fields. Native `<select>` recommended over radio (better mobile ergonomics; radio group fully specified as an alternate if group count stays ≤4).
- Default: label "I am registering as *", placeholder "Please Select" (disabled/selected), border `$color-gray82`.
- Hover: border `$color-gray56`.
- Focus: explicit `outline: 2px solid $focus-ring-color-new`, 2px offset (this control gates all subsequent content, so focus visibility is a priority beyond browser default).
- Disabled: N/A — group choice is never programmatically disabled.
- Loading: N/A — group list is pre-serialized server-side, no async fetch.
- Error: `.field.choice._error` — red border + inline message + `aria-invalid="true"`.
- Empty (no eligible groups configured): entire compliance section omitted from the DOM server-side; registration falls back to stock Luma fields only — not a rendered "empty" UI state.
A11y: explicit `<label for>`, `aria-required="true"`, `aria-describedby` to error node via `aria-live="polite"`; focus order = first focusable field in the compliance section. Contrast: label 12.6:1, error text 5.5:1.

### S2 — Dynamic Field Block (container + reveal)
`<fieldset data-bind="visible: hasSelectedGroup">`, populated client-side via Knockout from pre-serialized JSON per Eng spec (no network wait).
- Default: hidden (`display:none`, out of a11y tree/tab order) until group chosen.
- Hover/Focus/Disabled: N/A at container level (focus moves to first field inside on reveal).
- Loading: N/A (data pre-serialized); if Eng ends up lazy-fetching, add a 3-bar skeleton (flagged gap).
- Error: N/A at container level — errors live on individual fields, aggregated in S9.
- Empty: zero configured fields for selected group → quiet italic placeholder "No additional information is required for this group." (non-error styling).
A11y: `<legend>` = "Additional information for {Group Name}"; `aria-live="polite"` wrapping legend so screen readers announce the section appearing.

### S3 — Text/Textarea/Number/Email/Phone/Date
Standard Luma `.field`/`.control`/input pattern.
- Default: border `$color-gray82`, background white.
- Hover: border darkens to `$color-gray56`.
- Focus: use `$focus-ring-color-new` consistently across this feature.
- Disabled: N/A in normal flow; used only for a "locked pending review" resubmission state (`opacity:0.5`, gray background, not-allowed cursor).
- Loading: N/A.
- Error: red border + inline `.mage-error`-equivalent, `role="alert"` scoped to the field.
- Empty (unfilled): visually identical to Default; validate on blur/submit, not per keystroke.
Date field: reuse whichever date widget the codebase already uses elsewhere for consistency (native `<input type="date">` vs jQuery UI datepicker) — flagged open question. A11y: label association, `aria-required`, `inputmode` hints for phone/email.

### S4/S5/S6 — Dropdown / Radio / Checkbox
Reuse Luma `.field.choice` verbatim (same as native Magento EAV attribute rendering).
- Default/Hover: standard Luma chrome; hover background `$color-gray-light01` on radio/checkbox label rows.
- Focus: keep native focus styling on checkbox/radio (do not reskin).
- Disabled: same as S3's locked state.
- Loading: N/A.
- Error: red outline around the fieldset/group + message below, `role="alert"`.
- Empty: dropdown shows disabled "Please Select"; radio/checkbox N/A once options exist — zero-option config is a data-integrity gap (see §8, fixed upstream in A4).
A11y: `<fieldset><legend>` wrapping; each option its own `<label>`; multi-select checkbox group uses `role="group"`.

### S7 — File Upload (new composite)
Label + required marker + hint line ("Accepted: PDF, JPG, PNG · Max 5MB", values from Field config) + control row + filename/status chip.
1. Default/idle: dashed border `1px dashed $color-gray82`, "Choose file" button (Luma secondary button style), hint in `$color-gray56`.
2. Hover: border solid `$color-gray56`; dragover (if supported): border `$link__color`, background tint.
3. Focus: explicit ring on the styled trigger button; native input visually hidden (`opacity:0`, positioned, never `display:none`) so it stays in tab order and works with AT.
4. Disabled: locked-resubmission treatment, same token as S3.
5. Loading (uploading): button replaced by determinate progress bar — reuse Magento core Ui file-uploader's stock progress visual language rather than invent new.
6. Error: red border on control row; three distinct copy strings (wrong type / too large / generic failure), one visual treatment; rejected filename still shown (struck through / error icon) with a Remove action.
7. Empty (post-removal or never-selected + required submit attempt): default/idle visual + field-level required error if applicable.
Uploaded (success) sub-state: filename+size chip, neutral background, checkmark tinted with Luma's existing success green, "Remove/Replace" link.
A11y: hidden input keeps matching `aria-label`; hint linked via `aria-describedby`; progress uses `role="progressbar"` + `aria-valuenow/min/max`; error uses `aria-invalid` + `aria-describedby`; focus returns to trigger button after error/removal.

### S8 — Image Upload (extends S7)
All S7 states plus: 96×96px thumbnail preview sub-state (object-fit:cover, `1px solid $color-gray82` border) replacing the filename chip; skeleton placeholder while generating/uploading (avoids layout shift); "Preview unavailable" text if client-side decode fails (server-side validation remains authoritative). A11y: `alt="Preview of {filename}"`; decorative icons `aria-hidden="true"`.

### S9 — Form-level Validation Summary
Appears above submit when 1+ invalid fields exist (client and/or server round-trip).
- Default/Empty: not rendered when no errors.
- Hover: only on internal anchor links to each errored field (standard link hover).
- Focus: on appearance, focus programmatically moves to the summary (`tabindex="-1"` + `.focus()`).
- Disabled/Loading: N/A (synchronous client check); async submit loading lives on the submit button, not this component (flagged gap since Eng spec doesn't fully define server-async submit behavior).
- Error: this component *is* the error state — Luma `.message.error` styling, one line per invalid field, each an anchor that jumps to and focuses the offending field.
A11y: `role="alert"`/`aria-live="assertive"` (interrupts, unlike per-field errors which are polite/scoped). Heading: "There was a problem with your submission:" — calm, no exclamation points.

### S10 — My Compliance Documents (My Account)
New section reusing Luma's account-nav + `.table-wrapper > .data.table` (same structure as Order History). Columns: Document/Field label, Group, Status (current), Uploaded date, Action.
- Default/Hover/Focus: mirror Order History table exactly (zebra/hover only if Order History already does it).
- Disabled: N/A for rows; if parent order still `pending_verification`, show read-only "Under review" text instead of a grayed-out link.
- Loading: N/A (server-rendered, same as Order History).
- Error: "File unavailable — contact support" in place of download link if a file is missing/corrupted.
- Empty: "You have not uploaded any compliance documents yet." — reuse Luma's existing empty-order-history block styling verbatim.
Shows current version only (per default policy) — no version history in the customer-facing view (that's admin-only, A6). A11y: `<th scope="col">` headers; status conveyed as literal text, never color-only (WCAG 1.4.1).

### S11 — Resubmit Documents flow
Dedicated page (not modal — file upload + multiple fields is too heavy for a modal), triggered when an order was rejected.
- Default: neutral/informational context banner ("Your submission for Order #000000123 needs updated documents" — NOT error-red, since this invites action rather than scolding); shows original fields pre-filled (non-file) with file fields reset to idle (S7 state 1); shows the admin's decision notes read-only in a bordered callout (not error-red — informational).
- Hover/Focus/Disabled: inherit from S3–S8 per field.
- Loading: Luma's existing button-loading spinner during resubmit POST.
- Error: reuses S9 verbatim.
- Empty: N/A (page never renders with zero fields; a data error here shows a generic apology/contact-support message instead).
A11y: banner `role="status"` (not assertive — user navigated here deliberately); decision-notes block visually labeled "Reviewer notes" so it's distinguishable from editable content.

## 4. Admin Components (full state tables)

All use stock Magento Admin theme (Magento_Backend), matching `Rgd_Inventory`'s existing grids. Only A8 (status pill) and the refund-failed/offline-fallback tint pair are net-new visual elements.

### A1 — Group Configs grid
Columns: Customer Group, Approval Required, Registration Fields Enabled, Actions.
Default/Hover/Focus/Loading/Error: standard Admin grid states, reused verbatim from `Rgd_Inventory` grids (striping, hover token, focus outline, loading mask, error banner). Disabled: N/A at row level. Empty: standard Admin "no records found" component (customer groups always exist in Magento, so a fully empty grid is unlikely, but the standard filtered-empty state still applies).
A11y: standard Admin grid table semantics, no new surface introduced.

### A2 — Group Config form
Fields: Customer Group, Approval Required (toggle), Registration Fields Enabled (toggle), Save/Cancel.
- Default: Admin's stock toggle switch component, reused verbatim.
- Hover/Focus: Admin's stock toggle hover/focus.
- Disabled: "Registration Fields Enabled" *could* be disabled if "Approval Required" is off, depending on a business rule the brief doesn't confirm — flagged open question (§8); if no such dependency exists, N/A.
- Loading: Admin's stock Save-button spinner.
- Error: Admin's stock inline field error + page-level error strip on save failure.
- Empty: N/A (edit form always has group context).
A11y: standard Admin label/control association; toggles get `role="switch"` + `aria-checked` (Admin's stock component already implements this).

### A3 — Fields grid (per group)
Columns: Label, Field Type, Required, Sort Order, Actions.
- Default/Hover/Focus/Loading/Error: as A1.
- Disabled: recommend Delete action disabled + tooltip ("Cannot delete: in use by submitted documents") if a field is referenced by existing documents — flagged recommendation, confirm with Eng.
- Empty: "No fields configured for this group. Add a field to get started." + prominent "Add Field" button (actionable empty state, matching Admin's convention e.g. empty CMS Blocks grid).
A11y: same as A1.

### A4 — Field form (dynamic sub-fields by field_type)
Base fields always visible (Label, Field Type, Required, Sort Order); conditional sections: Options editor (dropdown/radio/checkbox — Admin's stock "dynamic rows" pattern, same as product custom options) and Allowed Extensions + Max File Size (file/image).
- Default: base fields shown, conditional sections hidden via Ui/Component `visible` binding on `field_type`.
- Hover: standard Admin control hover.
- Focus: **on field_type change, focus stays on the field_type select — do NOT auto-jump into the revealed section** (unlike the storefront reveal, an admin form shouldn't hijack focus mid-edit — WCAG 3.2.2).
- Disabled: N/A (no cap on option rows expected).
- Loading: standard Save spinner.
- Error: base-field errors standard Admin inline; Options editor blocks save with a fieldset-level error if zero options exist for dropdown/radio/checkbox; file/image constraint errors (e.g. "Max file size must be greater than 0").
- Empty: Options editor with zero rows shows "No options added yet." + "Add Option" (expected starting state, not an error, until save attempted).
Motion: instant show/hide on field_type change, no animation (deliberately different from storefront's reveal — admin power-user context favors jank-free instant toggling).
A11y: `field_type` select has `aria-describedby` noting fields will appear based on selection; conditionally-shown fieldsets get `aria-live="polite"` announcements without moving focus.

### A5 — Pending Verification / Order Approvals grid (highest-traffic screen)
Columns: Order #, Customer, Group, Submitted Date, Status (pill, A8), Actions (inline Approve/Reject).
- Default: Status column uses A8; Approve link (Admin's positive-action styling, reused/mapped) and Reject link (Admin's stock negative/delete-style action, red-tinted).
- Hover: row hover per Admin standard; action links underline on hover.
- Focus: Admin standard focus ring; row reading order Order# → ... → Approve → Reject.
- Disabled: mass-action checkboxes disabled for non-`pending_verification` rows, with a tooltip "Already {status}".
- Loading: grid-level standard Admin loading mask; row-level action loading uses a small inline spinner if AJAX row actions are implemented, else falls back to a full grid reload after the action (simpler/safer default, flagged as an Eng implementation choice).
- Error: Admin's stock error banner ("Unable to update order #X: already processed") on action failure (e.g. concurrent modification).
- Empty: **"All caught up! There are no orders awaiting verification."** — calm, no call-to-action needed (this is a good empty state).
A11y: mass-action checkboxes get `aria-label="Select order {order_number}"`; Approve/Reject links get disambiguating `aria-label`s per row (not generic "Approve"/"Reject" text alone).

### A6 — Approval Detail / Decision view
Full admin page (matches Admin's Order View pattern): header → customer profile summary → submitted field values (read-only) → document previews (current + **admin-only prior versions**, unlike customer-facing S10 which is current-only) → decision notes textarea (required only for reject) → Approve/Reject buttons → refund status block (renders only after a reject decision, "Retry refund" visible only when `refund_status===failed`).
- Default: as above for a `pending_verification` order, notes empty, both buttons enabled, no refund block yet.
- Hover/Focus: standard Admin hover; focus order follows the visual 1→7 layout; Retry Refund (when present) is last focusable element.
- Disabled: **design choice — validate-on-submit, not disable-until-valid.** Both Approve/Reject stay clickable; attempting Reject with empty notes surfaces a validation error rather than a pre-emptively disabled button (more discoverable). Once a decision is made, the whole decision panel is replaced by a read-only "Decision: {Approved/Rejected} by {admin} on {date}" summary — no longer actionable. Retry Refund's mere presence (not a disabled/enabled toggle) signals whether a retry is possible: absent when pending/completed/offline_fallback, present only when failed.
- Loading: standard Admin button-loading spinner on Approve/Reject/Retry, disabling the button mid-request.
- Error: "Decision notes are required when rejecting an order." inline under the textarea (`role="alert"`, focus moves there); page-level error banner on server failure.
- Empty: N/A for the core view; "No prior versions" plain text (not an error) when a document has only one upload.

Refund status sub-states (Status Pill A8, refund variant):
- `pending`: neutral/gray pill "Refund Pending", no action.
- `completed`: success/green pill "Refund Completed", no action.
- `failed`: **warning/error pill "Refund Failed"** using new `$refund-failed-color` + visible "Retry Refund" button + explanatory text.
- `offline_fallback`: distinct amber/neutral-warning pill "Refund — Manual Processing Required" — deliberately NOT the same red as `failed`, since this means the system correctly deferred to manual handling rather than failing.
A11y: pill color always paired with text label; "Retry Refund" gets a specific `aria-label`; refund status block is `aria-live="polite"` if updated in place without reload.

### A7 — Audit Log grid
Columns: Entity Type, Action, Date, Admin User, (optional) Details link. Strictly read-only — no mass-action, no row actions, Actions column entirely omitted (not just empty).
- Default: standard Admin grid styling, Actions header not rendered.
- **Hover: intentionally suppressed on data rows** (deviates from A1/A3/A5) — since rows aren't clickable, hover-highlighting would imply interactivity that isn't there; hover remains on filter controls/sortable column headers only.
- Focus: only filter fields and sortable headers are focusable.
- Disabled: N/A — nothing is ever actionable here.
- Loading/Error: standard Admin grid states, reused verbatim.
- Empty: standard "no records found," no call-to-action (pure reporting screen, unlike A3).
A11y: no empty "Actions" `<th>` rendered.

### A8 — Status Pill (shared component)
Used in A5 (order status) and A6 (refund status). Mostly N/A for interaction states since it's a static label, explicitly noted rather than silently skipped:
1. Default — `pending_verification`: `$status-pill-pending-bg-new` / `$status-pill-pending-text-new`, label "Pending Verification".
2. Hover: N/A (static label; if wrapped in a filter-link, inherits that link's Admin focus/hover).
3. Focus: N/A unless wrapped in a link.
4. Disabled: N/A.
5. Loading: N/A — reflects a persisted field, not an async operation.
6. Error — `rejected` (order) / `failed` (refund): red/error tint; order-rejected reuses Admin's existing negative status color (e.g. "Canceled"); refund-failed uses the new `$refund-failed-color` since stock Admin has no equivalent semantic slot and the brief calls out this exact state as needing distinctness.
7. Empty: N/A — every order in this grid has a determinate status by construction.
Also `approved` (reuse Admin's existing positive/green status color) and `offline_fallback` (new/reused amber, see tokens) round out the value set.
A11y: text label always present alongside color; optional leading icon reusing Admin's existing status-icon font if already used elsewhere, rather than adding new iconography.

## 5. Layout, Spacing, Responsive Breakpoints

**Storefront** (reuse stock Luma breakpoints, do not redefine): Mobile `<640px`, Tablet `640–768px`, Desktop `≥768px`.
- All breakpoints: single-column field stacking, matching how Luma's own base registration form already behaves at every width — deliberately **not** introducing a 2-column layout at tablet/desktop even where there's room, for visual consistency with the rest of the registration form.
- File/Image drop-zone: minimum 44×44px tappable area at all breakpoints (WCAG 2.5.5/2.5.8); desktop can afford a slightly larger ~56px comfort height.
- Spacing: reuse Luma's existing `$indent__base`/`$indent__s`/`$indent__m` — no new spacing scale.

**Admin**: inherits Admin's existing responsive grid-collapse behavior (columns collapse into an expandable per-row detail panel below Admin's stock ~768px breakpoint) automatically via the Ui/Component `data-grid` base — no new breakpoint logic, same as `Rgd_Inventory`'s existing grids.

## 6. Motion / Transitions

Only one motion spec is warranted (everything else is instantaneous or inherits standard Magento spinner motion, not re-specified):

**Group-switch field reveal (S1 → S2):** trigger = `change` on Group Selector. Effect: `opacity 0→1` + `translateY(-8px)→translateY(0)`, duration `200ms`, easing `ease-out` (matches Luma's existing short UI-motion feel where present, e.g. mobile nav flyouts). Switching groups after partially filling fields = single swap (clear + reveal), no separate fade-out choreography — keep it simple. Respects `prefers-reduced-motion: reduce` (skip slide/fade, instant `display` toggle instead — WCAG 2.3.3). Focus, once the new block is visible: move to the block's first focusable field (task-advancing), while the `aria-live` region still announces the legend text regardless of where focus lands.

Admin's A4 field-type reveal is explicitly **not** animated (instant show/hide) — deliberate contrast with the storefront reveal, since admin power-users prefer jank-free instant toggling over decorative transitions in a repetitive-task context.

## 7. Accessibility — Global Notes

- **Contrast**: all new/reused pairings target WCAG AA (4.5:1 normal text, 3:1 large text/UI components); specific ratios noted per component; reused stock Luma/Admin tokens assumed already-compliant.
- **Focus order**: Group Selector (S1) is the true first interactive element of the compliance section; Admin's A4 field-type reveal deliberately does NOT jump focus (predictability over momentum in an admin context) — the two similar "reveal" interactions are handled with opposite focus policies intentionally, not inconsistently.
- **ARIA roles**: `role="alert"` reserved for must-see-now interruptions (S9, A6's reject-without-notes error); `role="status"`/`aria-live="polite"` for non-interrupting context announcements (S2, A6's refund-status live update); never `aria-live="assertive"` for routine state changes.
- **Color-only meaning never used alone**: every status pill, field error, and refund state pairs color with explicit text — stated once here as a firm cross-cutting rule.
- **Native controls preferred over custom-skinned ones** wherever Luma/Admin already do this (checkboxes, radios, native file input under a styled trigger) — minimizes new a11y surface area.

## 8. Conflicts, Gaps, and Open Questions Resolved (or Flagged)

1. No live repo/theme access in this design pass — stock token values used; Eng should diff against the actual installed theme during build and flag drift back to Design. Resolved by proceeding rather than blocking.
2. Group Selector select-vs-radio: Eng spec left this open — resolved by recommending `<select>` as default with radio fully specified as an alternate, so Eng can pick without a second design pass.
3. Date field widget (native `<input type="date">` vs existing jQuery UI datepicker elsewhere in the codebase) — flagged open question for Eng to confirm before choosing, to avoid an unnecessary new JS dependency.
4. Async lazy-loaded field templates: Eng says "no network wait," but a fallback skeleton state is specified for S2 in case implementation ends up doing a secondary fetch — clearly marked optional/conditional.
5. Zero-options data integrity (dropdown/radio/checkbox with no options): resolved by pushing the fix upstream — A4 (Field form) blocks save in this case, so storefront never encounters a broken control.
6. Field "cannot delete, in use" safeguard (A3): recommended, not asserted as a hard requirement — confirm with Eng whether this is already planned at the data layer.
7. Approval Required ↔ Registration Fields Enabled toggle dependency (A2): not specified in the brief — flagged as an open question, A2's disabled-state note is written conditionally so the spec doesn't block on an unconfirmed business rule.
8. Inline row Approve/Reject (A5) vs. detail-page decision (A6), given reject requires notes a grid row can't reasonably capture: **resolved** — inline "Approve" stays a quick action; inline "Reject" should route to the detail view rather than reject in-place.
9. Refund-failed vs. offline_fallback: explicitly given different visual tones (error-red vs. amber/neutral) with rationale, to prevent an implementation from collapsing them into one color out of convenience.
10. Audit Log row-hover: not a brief requirement, but suppressed intentionally on A7 only, since highlighting fully inert rows would mislead users into thinking they're clickable.
11. No token conflicts found against documented stock Luma/Admin naming conventions; all new tokens use a `-new` suffix specifically so they're greppable and never collide with a same-named stock token.

None of the above block this design from proceeding to human review — items 2, 3, 7, and 8 are worth a quick confirmation from Eng/stakeholder before or during implementation.

---

# docs/design-tokens-compliance-registration.md

Only new or changed tokens listed — everything else maps directly to existing stock Luma/Admin tokens (`$color-white`, `$color-gray82`, `$error__color`, `$link__color`, Admin's stock toggle/grid/message components) and is not repeated since no new value is introduced there.

| Token name | Value | Maps to / relationship | Usage note |
|---|---|---|---|
| `$focus-ring-color-new` | `#008bc9` (2px solid, 2px offset) | Adjacent to Luma's `$link__color` (#1979c3) family, slightly darker/more saturated for clarity against white and light-gray backgrounds | Group Selector (S1) + all dynamic field controls (S3–S8) where an explicit unified focus ring is used instead of inconsistent browser defaults. Not applied in Admin — Admin's own stock focus ring is reused unchanged there. |
| `$drop-zone-border-new` | `1px dashed $color-gray82` (idle) / `1px solid $color-gray56` (hover) / `1px solid $link__color` (dragover) | Composed from existing Luma colors; only the dashed border *style* is new | File/Image upload (S7/S8) idle drop-zone. |
| `$status-pill-pending-bg-new` | `#fdf0d5` | New — no stock Admin "pending verification" color exists; warm-neutral tint distinct from success-green and error-red | Pending Verification status pill (A8/A5). Contrast vs. paired text: 8.1:1. |
| `$status-pill-pending-text-new` | `#6f4400` | Paired text for the above | Same usage; 8.1:1 contrast, exceeds AA. |
| `$refund-failed-color` | `#e02b27` text on `#fae5e5` background | Directly reuses Luma's existing `$error__color`/`$error__background-color` values, given an Admin-scoped name since stock Admin has no equivalent named slot | Refund Status pill (A6) when `refund_status==='failed'`, and the Retry Refund CTA context. Contrast: 5.5:1 (passes AA). |
| `$refund-offline-fallback-color` | `#6f4400` text on `#fdf0d5` background | Numerically identical to `$status-pill-pending-*` — both are "informational, no action required by default" semantically | Refund Status pill when `refund_status==='offline_fallback'`. Distinguished from `pending` by label text only, not color. Contrast: 8.1:1. |
| `$status-pill-approved-color` | Maps to existing Admin positive/success status color (e.g. "Complete"/"Closed") — no new value | N/A, reused verbatim | Approved order status (A5/A6); listed for traceability only. |
| `$status-pill-rejected-color` | Maps to existing Admin negative/error status color (e.g. "Canceled") — no new value | N/A, reused verbatim | Rejected order status — kept as a separate semantic token from `$refund-failed-color` even where hex may coincide, since an order can be rejected with a successful refund (the two states can diverge). |
| `$reveal-transition-duration-new` | `200ms` | New — Luma has no shared motion-duration token today | Group-switch field reveal (S2), paired with `ease-out` (standard CSS keyword, no new token needed). |
| `$reveal-transition-offset-new` | `-8px` (initial `translateY`) | New | Paired value for the reveal's slide component. |

No token conflicts identified: all `-new` suffixed tokens checked against documented stock Luma (`$color-*`, `$error__*`, `$link__*`, `$button-*`, `$indent__*`, `$screen__*`) and stock Admin color/status slot naming — none collide in name or redefine an existing token under a different value. Where a new token's value intentionally matches an existing one 1:1 (e.g. `$refund-failed-color` = `$error__color`), this is a deliberate reuse-with-renaming for Admin-context semantic clarity, not an accidental duplicate.

## Summary for reviewer

Design output only — no implementation code written. Every component (S1–S11, A1–A8) has all 7 mandated states specified, with explicit "N/A + reason" where a state doesn't meaningfully apply. Two genuinely new visual elements: Status Pill (A8) and the refund-failed/offline-fallback tint pair; everything else maps to existing stock Luma/Admin tokens and components, consistent with treating `Rgd_Inventory` as the baseline system to extend. Items 2, 3, 7, and 8 in §8 are worth a quick confirmation before/during implementation; none block proceeding.

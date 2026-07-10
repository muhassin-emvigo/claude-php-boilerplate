# Batch-Based Inventory Management (FEFO)

**Requested by:** Suraj UK

**Date:** 2026-07-10

**Priority:** High

**Status:** Phase 1 Implemented — Pending Final Approval

---

# Implementation Phasing

This requirement is being delivered in two phases. Phase 1 (this implementation) covers
core batch tracking, FEFO deduction, admin batch CRUD, and the GraphQL stock API. The
following items described below are **deferred to Phase 2** and are not yet implemented:

- CSV/XLSX batch-wise inventory export (see "Inventory Export" section)
- Reports with batch-level filtering and inventory visibility (see "Reports" section)
- Frontend batch-number traceability on order-related pages (see "Reports" acceptance criteria)

---

# Objective

Implement a **Batch-Based Inventory Management** system using the **First Expiry, First Out (FEFO)** inventory strategy.

Inventory must be managed by **SKU + Batch Number**, while customers continue purchasing products using only the SKU. Batch selection must happen automatically in the backend.

---

# Business Requirements

## Inventory Structure

Each product (SKU) can have multiple inventory batches.

Each batch must contain:

- SKU
- Batch Number
- Expiry Date
- Available Quantity

Inventory uniqueness must be based on:

```
SKU + Batch Number
```

SKU itself is **not unique**.

---

# FEFO (First Expiry, First Out)

Whenever inventory is deducted (checkout, dispensing, order creation, etc.):

1. Ignore expired batches.
2. Find all batches for the SKU.
3. Sort by Expiry Date (ascending).
4. Select the earliest valid batch with available quantity.
5. Deduct inventory from that batch.
6. If quantity becomes zero, automatically continue with the next valid batch.
7. Customers must never choose the batch manually.

---

# Checkout Behaviour

Do NOT introduce new Out of Stock behaviour.

The existing checkout flow should continue to work.

Requirements:

- Product availability must continue following the existing business logic.
- The FEFO engine should automatically select the correct batch.
- If one batch becomes empty, the next valid batch should automatically be used.
- No customer interaction is required.

Do not add any feature that forces products Out of Stock because an individual batch is empty.

---

# Expired Batch Rules

Expired batches:

- cannot be sold
- cannot be allocated
- cannot be selected by FEFO
- must remain visible in Admin
- should be clearly marked as Expired

---

# Admin Requirements

## Batch Inventory List

Create an Admin screen for batch inventory.

Display:

- SKU
- Product Name
- Batch Number
- Expiry Date
- Available Quantity
- Status (Active / Expired)
- Last Updated

Support:

- Pagination
- Search
- Sorting
- Filters

Filters:

- SKU
- Batch Number
- Product
- Expiry Date
- Status

---

## Batch Inventory CRUD

Admin must be able to:

- View batches
- Add batch
- Edit batch
- Update quantity
- Update expiry date
- Delete batch (if business rules allow)

Validation:

- SKU + Batch Number must be unique.
- Quantity cannot be negative.
- Expiry date is mandatory.
- Batch Number is mandatory.

---

# Inventory Export

Provide batch-wise inventory export.

Supported formats:

- CSV
- Excel (XLSX)

Columns:

- SKU
- Product Name
- Batch Number
- Expiry Date
- Available Quantity
- Reserved Quantity (if applicable)
- Status
- Last Updated

Export should support current filters.

---

# Frontend Requirements

Customers continue purchasing products normally.

No batch selection UI is required.

However, Batch Number should be visible wherever it improves traceability.

Display Batch Number on:

- Order Details
- Invoice
- Shipment
- Admin Order View
- Dispensing History
- Customer Order History (if applicable)

This helps clinic staff identify the exact medicine batch that was dispensed.

---

# Inventory Transactions

Every inventory transaction must store:

- SKU
- Batch Number
- Quantity
- Transaction Type
- Order ID (if applicable)
- User
- Timestamp

This is required for auditing and traceability.

---

# Reports

Reports must support:

- SKU
- Product
- Batch Number
- Expiry Date
- Remaining Quantity
- Available Quantity
- Active Batches
- Expired Batches

Filters:

- SKU
- Batch
- Product
- Expiry Date
- Status

---

# Example

Current Inventory

| SKU | Batch | Expiry | Qty |
|-----|-------|---------|----:|
| TAB-001 | B001 | 2026-09-30 | 50 |
| TAB-001 | B002 | 2027-02-28 | 100 |

Customer purchases 20.

Result:

B001 → 30

Customer purchases another 30.

Result:

B001 → 0

Customer purchases 10.

Result:

B002 → 90

No manual batch selection.

---

# Acceptance Criteria

- Inventory is managed by SKU + Batch Number.
- FEFO allocation always uses the earliest valid expiry date.
- Expired batches are never allocated.
- Customers never choose batches.
- Automatic switch to the next batch when current batch is empty.
- Existing checkout behaviour remains unchanged.
- Admin can manage batches.
- Admin can export batch-wise inventory.
- Batch Number is stored with every inventory transaction.
- Batch Number is visible in frontend order-related pages for traceability.
- Reports support batch-level filtering and inventory visibility.

---

# Technical Notes

- Follow Magento 2 coding standards.
- Use Service Contracts where applicable.
- Avoid modifying Magento core.
- Use Dependency Injection.
- Follow repository pattern.
- Maintain backward compatibility.
- Ensure implementation supports future multi-source inventory (MSI) integration if required.
- Write unit and integration tests where applicable.
- Ensure inventory deduction is transactional to avoid race conditions during concurrent orders.
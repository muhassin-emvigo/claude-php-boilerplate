# Batch-Based Inventory Management (FEFO)

**Requested by:** Suraj UK

**Date:** 2026-07-08

**Status:** In Progress — implemented (docs/spec-batch-based-inventory-management.md), through Code Review + Approver gates (see docs/progress-batch-based-inventory-management.md); not yet committed/shipped

---

## What do you want?

The clinic needs inventory to be managed by both **SKU** and **Batch Number**.

A single medicine (SKU) can have multiple batches, and each batch has its own:
- Batch Number
- Expiry Date
- Available Quantity

When dispensing or selling medicine, the system should automatically select the batch with the earliest expiry date that has available stock.

---

## Why do you want it?

The same medicine is often received in multiple batches with different expiry dates.

To reduce expired stock and ensure medicines are dispensed correctly, inventory should follow the **First Expiry, First Out (FEFO)** method.

This helps:
- Reduce medicine wastage.
- Ensure older stock is used first.
- Improve inventory accuracy.
- Follow pharmacy inventory best practices.

---

## What does "done" look like?

When multiple batches exist for the same SKU:

- The system always deducts stock from the batch with the earliest expiry date.
- If that batch runs out of stock, the system automatically switches to the next available batch.
- Customers and clinic staff continue to purchase the same product without needing to select a batch.
- The product remains **In Stock** as long as at least one batch has available quantity.
- The product is shown as **Out of Stock** only when **all batches** for that SKU have zero available quantity.
- Each inventory transaction records the actual batch used for traceability.

### Example

| SKU | Batch | Expiry | Quantity |
|-----|-------|---------|---------:|
| TAB-001 | B001 | 2026-09-30 | 50 |
| TAB-001 | B002 | 2027-02-28 | 100 |

**Scenario 1**

Customer purchases 20 tablets.

- 20 tablets are deducted from **Batch B001**.
- Remaining quantity:
  - B001 = 30
  - B002 = 100

**Scenario 2**

Another customer purchases 30 tablets.

- Remaining 30 tablets are deducted from **Batch B001**.
- B001 is now empty.

**Scenario 3**

Next customer purchases 10 tablets.

- The system automatically deducts 10 tablets from **Batch B002**.
- The product is still available for purchase.

**Scenario 4**

The product becomes **Out of Stock** only after **both Batch B001 and Batch B002 have zero available quantity**.

---

## Anything else the developer should know?

- SKU is **not unique**.
- Inventory records are uniquely identified by **SKU + Batch Number**.
- Each batch has its own expiry date and available quantity.
- Batch selection should be automatic and based on the earliest expiry date with available stock.
- Expired batches must not be used for dispensing or sales.
- The batch used for every transaction must be stored for auditing and traceability.
- Reports should support filtering by SKU, Batch Number, Expiry Date, and remaining quantity.

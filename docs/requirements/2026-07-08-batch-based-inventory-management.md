# Batch-Based Inventory Management (FEFO) --- Business Requirements

## 1. Purpose

Implement a Batch-Based Inventory Management system using the First
Expiry, First Out (FEFO) inventory strategy. The system must manage
inventory at the Batch Number level while allowing customers to continue
purchasing products using only the SKU. Batch allocation must occur
automatically during inventory deduction.

## 2. Business Objective

-   **Primary Objective:** Manage inventory by SKU and Batch Number
    using FEFO.
-   **Expected Business Outcome:** Reduce medicine expiry loss and
    improve inventory traceability.
-   **Success Measure:**
    -   Earliest valid batch is always selected.
    -   Expired batches are never sold.
    -   Automatic allocation across batches.
-   **Target Users:**
    -   Store Administrators
    -   Warehouse Staff
    -   Pharmacy/Clinic Staff
-   **Business Owner:** Suraj UK

## 3. Background

The existing Magento inventory manages stock only at the SKU level.
Clinics require multiple batches for a single SKU, each with its own
expiry date and available quantity. Inventory must be deducted from the
batch with the earliest expiry date while maintaining complete batch
traceability for auditing.

## 4. Scope

### In Scope

-   Batch inventory management
-   FEFO allocation
-   Batch CRUD in Admin
-   Batch validation
-   Inventory transactions
-   GraphQL stock support
-   Batch traceability
-   Batch status management

### Out of Scope

-   Batch barcode printing
-   Supplier purchase workflow
-   Batch import automation
-   Multi-warehouse enhancements
-   Inventory forecasting

## 5. Users and Roles

  --------------------------------------------------------------------------
  Role            Description                 Main Actions
  --------------- --------------------------- ------------------------------
  Administrator   Manage inventory batches    Add, Edit, Delete, Update
                                              batches

  Store Manager   Monitor stock               View inventory, exports,
                                              reports

  Customer        Purchase products           Add products to cart without
                                              selecting batches
  --------------------------------------------------------------------------

## 6. Business Process

### Current Process

1.  Inventory maintained only by SKU.
2.  No batch tracking.
3.  No expiry-based allocation.
4.  No batch traceability.

### Proposed Process

1.  Admin creates batches.
2.  Each SKU can have multiple batches.
3.  FEFO selects the earliest valid batch.
4.  Inventory deducts automatically.
5.  Batch information is stored with the transaction.

## 7. User Journeys

### Journey 1 --- Admin Creates Batch

**Actor:** Administrator

**Trigger:** New inventory arrives.

**Preconditions:** - Product exists. - User has inventory permissions.

**Flow** 1. Open Batch Inventory. 2. Click Add Batch. 3. Enter SKU,
Batch Number, Expiry Date and Quantity. 4. Save.

**Expected Result:** Batch is created successfully.

### Journey 2 --- Customer Purchases Product

**Actor:** Customer

**Trigger:** Checkout

**Preconditions:** - SKU has available inventory. - At least one
non-expired batch exists.

**Flow** 1. Customer adds SKU. 2. Checkout begins. 3. FEFO selects
earliest valid batch. 4. Inventory deducted. 5. Batch saved on order.

**Expected Result:** Customer purchases successfully without choosing a
batch.

## 8. Business Rules

  -----------------------------------------------------------------------
  Rule ID           Rule              Priority          Notes
  ----------------- ----------------- ----------------- -----------------
  BR-001            Inventory         Must Have         Duplicate batches
                    uniqueness =                        not allowed
                    SKU + Batch                         
                    Number                              

  BR-002            FEFO selects      Must Have         Ignore expired
                    earliest valid                      batches
                    expiry                              

  BR-003            Customers cannot  Must Have         Backend only
                    choose batches                      

  BR-004            Expired batches   Must Have         Visible in Admin
                    cannot be sold                      

  BR-005            Auto switch to    Must Have         
                    next batch when                     
                    quantity reaches                    
                    zero                                
  -----------------------------------------------------------------------

## 9. Validation Rules

  Validation ID   Field          Validation           Expected Error Message
  --------------- -------------- -------------------- --------------------------
  VAL-001         Batch Number   Mandatory            Batch Number is required
  VAL-002         Expiry Date    Mandatory            Expiry Date is required
  VAL-003         Quantity       Cannot be negative   Invalid quantity
  VAL-004         SKU + Batch    Must be unique       Duplicate batch exists

## 10. Data Requirements

  Data Item              Required Source    Validation       Notes
  -------------------- ---------- --------- ---------------- -------------
  SKU                         Yes Product   Must exist       Product SKU
  Batch Number                Yes Admin     Unique per SKU   
  Expiry Date                 Yes Admin     Valid date       
  Available Quantity          Yes Admin     \>=0             
  Status                      Yes System    Active/Expired   

## 11. Statuses and State Changes

  Current Status   Action                  New Status   Conditions
  ---------------- ----------------------- ------------ -----------------------------
  Active           Expiry reached          Expired      Current date exceeds expiry
  Active           Quantity becomes zero   Empty        Quantity = 0

## 12. Notifications and Messages

  Trigger            Recipient   Channel   Message / Template
  ------------------ ----------- --------- -----------------------------
  Duplicate Batch    Admin       UI        Batch already exists
  Invalid Quantity   Admin       UI        Quantity cannot be negative

## 13. Error Scenarios

  Scenario          Expected Behaviour   User Message
  ----------------- -------------------- ---------------------------
  Duplicate batch   Prevent save         Batch already exists
  No valid batch    Block allocation     Product is unavailable
  Expired batch     Ignore batch         Next valid batch selected

## 14. Positive Scenarios

  Scenario                 Expected Result
  ------------------------ --------------------------
  Multiple valid batches   Earliest expiry selected
  Current batch empty      Next batch selected
  New batch created        Available for FEFO

## 15. Edge Cases

-   Multiple batches with same expiry.
-   Partial deduction across batches.
-   All batches expired.
-   Concurrent orders.

## 16. Reporting and Audit Requirements

-   Audit batch creation, updates and deductions.
-   Batch inventory reports.
-   CSV/XLSX export.

## 17. Security and Compliance Requirements

-   Only authorized administrators can manage batches.
-   Maintain audit history.
-   Company-level data isolation where applicable.

## 18. Business Acceptance Criteria

### AC-001 --- FEFO Allocation

**Given** a SKU has multiple valid batches.

**When** a customer purchases the product.

**Then** the earliest expiry batch is automatically selected.

## 19. Assumptions

-   Magento inventory is enabled.
-   Products already exist.

## 20. Dependencies

-   Magento Inventory
-   Product Catalog
-   Order Management
-   GraphQL

## 21. Open Questions

  --------------------------------------------------------------------------
  Question ID    Question       Owner          Status         Decision
  -------------- -------------- -------------- -------------- --------------
  Q-001          Should empty   Business       Open           Yes
                 batches remain                               
                 visible in                                   
                 reports?                                     

  --------------------------------------------------------------------------

## 22. Business Definition of Done

-   FEFO allocation implemented.
-   Batch management completed.
-   Admin CRUD available.
-   Acceptance criteria approved.

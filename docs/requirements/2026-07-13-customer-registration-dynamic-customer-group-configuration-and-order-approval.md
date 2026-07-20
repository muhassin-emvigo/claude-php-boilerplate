# Customer Registration, Dynamic Customer Group Configuration & Order Approval --- Business Requirements

**Status:** In Progress (running `/full-flow` — see `docs/progress-customer-registration-dynamic-customer-group-configuration-and-order-approval.md`)

## 1. Purpose

Implement a configurable customer registration and order approval system
in Magento where Administrators can create Customer Groups with dynamic
registration fields and document requirements. Customers can place
orders immediately, but orders require Admin approval before
fulfillment.

## 2. Business Objective

-   **Primary Objective:** Support configurable customer registration
    and compliance verification.
-   **Expected Business Outcome:** Ensure only verified customers
    receive regulated products.
-   **Success Measure:**
    -   Customer groups configurable from Admin.
    -   Dynamic registration forms.
    -   Successful document verification workflow.
    -   Order approval before fulfillment.
-   **Target Users:**
    -   Administrators
    -   Customers
-   **Business Owner:** Suraj UK

## 3. Background

Different customer types require different registration information. For
example, Pharmacists must upload licenses and ID cards, while General
Customers may only need identity proof. The solution should be fully
configurable without developer involvement.

## 4. Scope

### In Scope

-   Customer Group Management
-   Dynamic Registration Form Builder
-   Unlimited Custom Fields
-   Unlimited File Upload Fields
-   Customer Registration
-   Document Upload
-   Order Approval Workflow
-   Refund Workflow
-   Email Notifications
-   Audit History

### Out of Scope

-   Government API Verification
-   OCR/AI Document Validation

## 5. Users and Roles

  -----------------------------------------------------------------------
  Role                    Description             Main Actions
  ----------------------- ----------------------- -----------------------
  Administrator           Configure customer      Manage groups, fields,
                          groups and verify       approve/reject orders
                          orders                  

  Customer                Register and purchase   Upload documents,
                          products                checkout
  -----------------------------------------------------------------------

## 6. Business Process

### Current Process

1.  Customer registers.
2.  Customer places order.
3.  Order is processed immediately.

### Proposed Process

1.  Admin creates Customer Groups.
2.  Admin configures registration fields.
3.  Customer registers.
4.  Dynamic form displayed.
5.  Customer uploads documents.
6.  Customer places order.
7.  Order status = Pending Verification.
8.  Admin reviews documents.
9.  Admin approves or rejects order.
10. Customer notified.
11. Refund initiated if rejected.

## 7. User Journeys

### Journey 1 --- Configure Customer Group

**Actor:** Administrator

**Flow**

1.  Navigate to Customer Group Configuration.
2.  Create/Edit Customer Group.
3.  Add unlimited text and upload fields.
4.  Mark required fields.
5.  Save configuration.

**Expected Result**

Dynamic registration form is available for the selected group.

### Journey 2 --- Customer Registration

**Actor:** Customer

1.  Select Customer Group.
2.  Complete dynamic registration form.
3.  Upload required documents.
4.  Submit registration.

### Journey 3 --- Order Approval

**Actor:** Administrator

1.  Open Pending Verification orders.
2.  Review customer profile and uploaded documents.
3.  Approve or Reject order.
4.  Customer receives notification.
5.  Refund initiated if rejected.

## 8. Business Rules

  -----------------------------------------------------------------------
  Rule ID           Rule              Priority          Notes
  ----------------- ----------------- ----------------- -----------------
  BR-001            Admin can         Must Have         
                    Add/Edit/Delete                     
                    Customer Groups                     

  BR-002            Unlimited         Must Have         
                    registration                        
                    fields per group                    

  BR-003            Unlimited file    Must Have         
                    upload fields per                   
                    group                               

  BR-004            Supported field   Must Have         
                    types include                       
                    Text, Number,                       
                    Email, Dropdown,                    
                    Checkbox, Date,                     
                    File Upload and                     
                    Image Upload                        

  BR-005            Required fields   Must Have         
                    configurable                        

  BR-006            Checkout allowed  Must Have         
                    before approval                     

  BR-007            Orders remain     Must Have         
                    Pending                             
                    Verification                        
                    until approved                      

  BR-008            Only approved     Must Have         
                    orders proceed to                   
                    fulfillment                         

  BR-009            Rejected orders   Must Have         
                    initiate refund                     
                    process                             
  -----------------------------------------------------------------------

## 9. Validation Rules

  Validation ID   Field             Validation     Expected Error
  --------------- ----------------- -------------- --------------------
  VAL-001         Required Fields   Mandatory      Field is required
  VAL-002         File Upload       PDF/JPG/PNG    Invalid file type
  VAL-003         File Size         Configurable   File exceeds limit

## 10. Data Requirements

  Data Item              Required Source     Validation                  Notes
  -------------------- ---------- ---------- --------------------------- -------
  Customer Group              Yes Admin      Active                      
  Custom Fields               Yes Admin      Configurable                
  Uploaded Documents          Yes Customer   Per Group                   
  Approval Status             Yes System     Pending/Approved/Rejected   

## 11. Customer Group Configuration Matrix

  Group              Required Documents         Approval Required
  ------------------ -------------------------- -------------------
  Pharmacist         License, ID Card           Yes
  Doctor             Medical License, ID        Yes
  Clinic             Registration Certificate   Yes
  General Customer   Government ID (Optional)   Optional

### Supported Field Types

-   Text
-   Text Area
-   Number
-   Email
-   Phone
-   Date
-   Dropdown
-   Radio Button
-   Checkbox
-   File Upload
-   Image Upload

## 12. Notifications and Messages

  -----------------------------------------------------------------------
  Trigger           Recipient         Channel           Message
  ----------------- ----------------- ----------------- -----------------
  Registration      Customer          Email             Registration
                                                        successful

  Order Approved    Customer          Email             Order approved

  Order Rejected    Customer          Email             Order rejected
                                                        and refund
                                                        initiated
  -----------------------------------------------------------------------

## 13. Error Scenarios

  Scenario            Expected Behaviour     User Message
  ------------------- ---------------------- ---------------------------------
  Missing documents   Prevent registration   Upload required documents
  Invalid documents   Reject order           Documents could not be verified

## 14. Positive Scenarios

  Scenario                           Expected Result
  ---------------------------------- -----------------------------------------
  Customer uploads valid documents   Order approved
  Dynamic fields configured          Registration form updates automatically

## 15. Edge Cases

-   Customer changes group after registration.
-   Admin edits fields after customers exist.
-   Multiple document uploads.
-   Re-upload after rejection.

## 16. Reporting and Audit Requirements

-   Customer approval history
-   Order approval report
-   Refund report
-   Audit log

## 17. Security and Compliance Requirements

-   Documents accessible only by authorized Admin users.
-   Audit every approval action.
-   Secure document storage.

## 18. Business Acceptance Criteria

### AC-001

**Given** Admin configures a Customer Group.

**When** Customer registers.

**Then** Dynamic registration fields are displayed.

### AC-002

**Given** Customer places an order.

**When** Documents are approved.

**Then** Order proceeds to fulfillment.

### AC-003

**Given** Documents are rejected.

**When** Admin rejects the order.

**Then** Customer is notified and refund process starts.

## 19. Assumptions

-   Magento Customer Groups are available.
-   Payment gateway supports refunds.

## 20. Dependencies

-   Magento Customer Module
-   Magento Sales
-   Email Service
-   Payment Gateway

## 21. Open Questions

  ---------------------------------------------------------------------------
  Question ID    Question        Owner          Status         Decision
  -------------- --------------- -------------- -------------- --------------
  Q-001          Should field    Business       Open           Pending
                 configuration                                 
                 support                                       
                 conditional                                   
                 visibility?                                   

  Q-002          Can customers   Business       Open           Pending
                 re-upload                                     
                 documents after                               
                 rejection?                                    
  ---------------------------------------------------------------------------

## 22. Business Definition of Done

-   Customer Groups configurable from Admin.
-   Dynamic registration form implemented.
-   Unlimited fields supported.
-   Unlimited document upload supported.
-   Order approval workflow implemented.
-   Refund workflow supported.
-   Notifications implemented.
-   Audit reports available.

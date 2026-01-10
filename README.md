# Xero Bills Sync

This module automates the creation of Xero Accounts Payable (ACCPAY) Bills from Drupal ECK entities. It is designed to handle contractor payments and staff reimbursements.

## Prerequisites

*   **ECK (Entity Construction Kit)** module
*   **Hook Event Dispatcher** module (specifically `core_event_dispatcher`)
*   **Xero** module (optional; required only when you enable sync)

## Installation

1.  Enable the module:
    ```bash
    drush en xero_bills_sync
    ```
2.  The module will automatically add a `Xero Contact ID` field to the Drupal User entity.
3.  **Important:** You must manually add this field to the User form display and view display:
    *   Go to **Configuration > People > Account settings > Manage form display**. Drag "Xero Contact ID" to the visible area and save.
    *   Go to **Configuration > People > Account settings > Manage display**. Drag "Xero Contact ID" to the visible area and save.

## Configuration Guide

### 1. Create the ECK Entity Type

This module relies on a specific ECK entity structure. You must create this manually.

1.  Go to **Structure > ECK Entity Types** (`/admin/structure/eck`).
2.  Click **Add Entity Type**.
3.  **Label:** `Payment Request`
4.  **Machine Name:** `payment_request` (CRITICAL: Must match exactly).
5.  **Base Fields:** Ensure `Title`, `Author`, `Created`, and `Changed` are checked.
6.  Click **Save**.

### 2. Create Bundles

Create bundles for the different types of payments you handle.

1.  Go to the **Bundle List** for your new *Payment Request* entity.
2.  Add Bundle: **Reimbursement** (Machine name: `reimbursement`)
3.  Add Bundle: **Contractor Fee** (Machine name: `fee`)
    *   *Note: You can name these whatever you like, but you will map them to Xero Account Codes later.*

### 3. Add Required Fields

Add the following fields to your entity type (or specific bundles). The machine names must match exactly for the code to work.

| Label | Machine Name | Field Type | Description |
| :--- | :--- | :--- | :--- |
| **Amount** | `field_amount` | Decimal | The total amount to be paid. |
| **Status** | `field_status` | List (text) | **Allowed values:**<br>`draft\|Draft`<br>`submitted\|Submitted`<br>`paid\|Paid`<br><br>*The sync only triggers when saved as **Submitted**.* |
| **Attachment** | `field_attachment` | File | For uploading receipts or invoices. |
| **Description** | `field_description` | Text (long) | **Optional.** Provides more detail than the Title. Xero Line Item = "Title - Description". |
| **Xero Invoice ID** | `field_xero_invoice_id` | Text (plain) | **Internal use.** Stores the Xero GUID. <br>*(Note: Xero uses the `Invoice` endpoint for both Bills and Invoices. A Bill is simply an Invoice with type `ACCPAY`.)* |

### 4. Optional: Hourly Calculator (Contractors)

To enable the "Hours * Rate" calculator for contractor payments:

1.  **On the Payment Request entity:** Add `field_hours` (Decimal) and `field_hourly_rate` (Decimal) to the `fee` bundle.
2.  **On the User entity:** Add `field_default_hourly_rate` (Decimal).
    *   *Effect:* When a user creates a new request, their default rate is auto-filled. As they type hours, the `Amount` field updates automatically.

### 5. Optional: Advanced Fields (Payee & Account Codes)

To allow assigning requests to others or selecting specific Xero accounts:

1.  **Payee Override:** Add `field_payee` (Entity reference: User) to the Payment Request.
    *   *Effect:* If set, the bill is created for this user (Xero Contact) instead of the Author. Staff can use this to create requests on behalf of contractors.
2.  **Account Code Override:** Add `field_xero_account_id_reimburse` and/or `field_xero_account_id_payment` (Text/Select list) to the relevant bundles.
    *   *Effect:* If these fields have a value, that value is used as the Xero Account Code (e.g., `610`), overriding the module's global mapping.

### 6. Module Configuration

1.  Go to **Configuration > Web Services > Xero Bills Sync** (`/admin/config/services/xero-bills-sync`).
2.  **Attachment Field:** Confirm this matches the field name you created above (default: `field_attachment`).
3.  **Bundle Mappings:** Enter the Xero General Ledger Account Code (e.g., `600`, `610`) for each bundle.
4.  **Enable Xero synchronization:** Leave this off until OAuth is configured.
5.  **Backfill submitted requests:** Enable this if you want cron to sync older submitted requests once integration is live.
6.  **Run backfill now:** Use the button to trigger an immediate one-time sync of submitted requests (up to 50 at a time).

## How it Works

1.  **User Workflow:**
    *   A user (Staff or Contractor) logs in and goes to their "My Payment Requests" tab.
    *   They create a new request, uploading a receipt and entering the amount.
    *   They save the request with the status **Submitted**.

2.  **Synchronization:**
    *   The module detects the `submitted` status.
    *   It identifies the Drupal User owner of the request.
    *   **Xero Contact Lookup:**
        *   First, it checks the user's profile for a `Xero Contact ID`.
        *   If empty, it searches Xero for a Contact with the user's email address.
        *   If a match is found, it saves the ID to the user's profile for next time.
    *   **Bill Creation:**
        *   It creates a "Draft" or "Submitted" Bill (Invoice type ACCPAY) in Xero.
        *   It uploads the file attachment to the bill.
        *   It saves the returned Xero Invoice ID to the ECK entity to prevent duplicates.

## Views

The module provides two Views for management:

*   **Staff Overview:** `/admin/content/payment-requests`
    *   Lists all requests system-wide.
    *   Filterable by status and type.
    *   Status can be edited inline if the role has **Use editable fields** and **Manage payment request status** permissions.
*   **User Dashboard:** `/user/me/payment-requests` (Tab on User Profile)
    *   Shows the logged-in user's history of requests.

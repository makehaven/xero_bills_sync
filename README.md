# Xero Bills Sync

This module automates the creation of Xero Accounts Payable (ACCPAY) Bills from Drupal ECK entities. It is designed to handle contractor payments and staff reimbursements.

## Prerequisites

*   **ECK (Entity Construction Kit)** module
*   **Xero** module (configured with OAuth 2.0 credentials)
*   **Hook Event Dispatcher** module (specifically `core_event_dispatcher`)

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
| **Xero Invoice ID** | `field_xero_invoice_id` | Text (plain) | **Internal use.** Stores the Xero GUID. <br>*(Note: Xero uses the `Invoice` endpoint for both Bills and Invoices. A Bill is simply an Invoice with type `ACCPAY`.)* |

### 4. Module Configuration

1.  Go to **Configuration > Web Services > Xero Bills Sync** (`/admin/config/services/xero-bills-sync`).
2.  **Attachment Field:** Confirm this matches the field name you created above (default: `field_attachment`).
3.  **Bundle Mappings:** Enter the Xero General Ledger Account Code (e.g., `600`, `610`) for each bundle.

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
*   **User Dashboard:** `/user/me/payment-requests` (Tab on User Profile)
    *   Shows the logged-in user's history of requests.

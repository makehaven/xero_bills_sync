# Xero Bills Sync Module

## Project Overview
**Machine Name:** `xero_bills_sync`
**Type:** Drupal Custom Module
**Dependencies:** `eck`, `xero` (contrib), `hook_event_dispatcher`

This module automates the Accounts Payable process by synchronizing Drupal ECK entities (Payment Requests) to Xero as Bills (ACCPAY Invoices). It handles contractor payments and staff reimbursements, including file attachment uploads.

## Key Features
*   **Automatic Bill Creation:** Listens for `payment_request` entities saved with status `submitted`.
*   **Smart Contact Resolution:** Maps Drupal users to Xero Contacts via a local field (`field_xero_contact_id`) or falls back to an email lookup.
*   **Duplicate Prevention:** Uses Xero's `InvoiceNumber` field (prefixed with `PAYREQ-`) and a local tracking field.
*   **Attachment Sync:** Uploads receipt images/PDFs from Drupal to the created Xero Bill.

## Architecture & Key Files

### 1. Service Layer
*   **`src/Service/SyncManager.php`**
    *   **Role:** Core business logic.
    *   **Dependencies:** `xero.client` (Radcliffe\Xero\XeroClient), `entity_type.manager`, `file_system`.
    *   **Methods:**
        *   `syncPaymentRequest(EckEntityInterface $entity)`: Main entry point. Constructs the payload.
        *   `getXeroContactId(UserInterface $user)`: Checks user field -> Queries Xero by Email -> Updates user field.
        *   `uploadAttachments(...)`: Streams file content to Xero's `/Invoices/{id}/Attachments` endpoint.

### 2. Event Subscription
*   **`src/EventSubscriber/EckEntitySubscriber.php`**
    *   **Role:** Trigger mechanism.
    *   **Events:** Listens to `hook_event_dispatcher` events (`ENTITY_INSERT`, `ENTITY_UPDATE`).
    *   **Logic:** Filters for `payment_request` entity type and delegates to `SyncManager`.

### 3. Configuration
*   **`src/Form/SettingsForm.php`** (`/admin/config/services/xero-bills-sync`)
    *   Maps ECK bundles (e.g., `reimbursement`, `fee`) to Xero General Ledger Account Codes (e.g., `610`, `600`).
    *   Configures the attachment field name.

## Installation & Setup

### Prerequisite: ECK Structure
You **must** manually create the ECK Entity Type `payment_request` with:
*   **Bundles:** `reimbursement`, `fee` (or similar).
*   **Fields:**
    *   `field_amount` (Decimal)
    *   `field_status` (List: draft, submitted, paid)
    *   `field_attachment` (File)
    *   `field_xero_invoice_id` (Text)

### Prerequisite: User Field
The module automatically installs config for `field_xero_contact_id` on the User entity.
*   **Action Required:** You must manually add this field to the **User Form Display** and **User View Display**.

### Prerequisite: Xero Connection
The `xero` contrib module must be installed and authenticated via OAuth 2.0.

## Usage
1.  **Staff/User:** Creates a `payment_request` and sets status to `Submitted`.
2.  **System:**
    *   Finds/Creates Xero Contact.
    *   Posts Bill to Xero (Status: SUBMITTED).
    *   Uploads receipt.
    *   Updates Drupal entity with Xero GUID.

## Troubleshooting
*   **Logs:** Check **Reports > Recent log messages** (channel: `xero_bills_sync`).
*   **Permissions:** Ensure the authenticated Xero user has `Standard` or `Adviser` role (needed for approving bills).
*   **Missing Dependencies:** Ensure `doctrine/annotations` is present if you see plugin discovery errors.

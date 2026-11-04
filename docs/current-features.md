# Pyesio — Current Features

As of **November 4, 2026 · Asia/Manila**.

Pyesio is an online hardware-store management system for independent businesses. It has a **Platform Admin Portal** for managing stores and a **Store Portal** for daily operations. Each store currently has one active operational owner account.

This inventory describes the implemented production release, including hardware product templates, customer credit, and quotations with partial pickup/delivery. Features still being discussed are listed separately under **Not yet included**.


Deployed scanner and receipt enhancements are documented in [Scanner and receipt printing](scanner-receipts.md). Offline POS remains a separate, undeployed experimental branch.

Live application: [Pyesio](https://wgalas.lzrk.io/login).

## 1. Platform Admin Portal

### Store registration and account management

- Register a store with its unique code, business name, physical address, contact information, and initial owner's details.
- Send an owner activation invitation. The owner chooses their own password when accepting.
- Invite an existing user through an explicit existing-account flow.
- Resend invitations and replace a store owner through invitation acceptance.
- Manage store states: pending activation, active, suspended, and archived.
- Assist owner password recovery through email or a privately shared reset link.
- Preserve historical records and their original users when ownership changes.

### Features and access controls

- Configure modules separately for each store.
- Choose **Enabled**, **Restricted**, or **Disabled**.
- Validate dependencies between modules before saving settings and posting transactions.
- Keep supported corrections and unfinished work available through restricted mode.
- Separate administrator permissions for store management, module management, announcements, viewing, reports, exports, and audit access.

### Centralized reporting and store inspection

- View a searchable, paginated store directory.
- Filter reports by store and date range.
- View recorded sales, customer payments, outstanding balances, costs, expenses, and profit estimates.
- Export platform report results to CSV.
- Open **View store** to inspect a store's workspace and records using the administrator's own identity.
- Keep administrator inspection read-only, with a visible banner and audited access.
- Require separate export permission for administrator downloads of report data.

### Announcements, email, and system status

- Create announcement drafts for all stores or selected stores.
- Publish immediately, schedule publication, set expiration, and withdraw announcements.
- Optionally email active store owners when an announcement is published.
- Review email delivery history and retry eligible failures.
- Review scheduler heartbeat, backup freshness, mail readiness, failed jobs, and production error-display status.
- Browse the platform audit trail.

## 2. Store setup and everyday interface

- Sign in and open an authorized store workspace.
- Use an automatic onboarding checklist with progress and a suggested next step.
- Track setup milestones from actual saved records: contact details, accepted payments, products, starting stock, cash drawer, and first sale.
- Update store contact details, physical address, and accepted checkout payment methods.
- Read announcements addressed to the store.
- Use responsive screens, light/dark appearance settings, and branded Pyesio layouts.
- Search and filter supported lists; select 10, 25, or 50 rows per page.
- Navigate with previous/next controls, page jumping, and links to history sections.
- Open product details/edit dialogs without a long list of expanded forms.
- Print supported records and reports. List printing covers the current page; available CSV exports cover their full selected scope.

## 3. Products and hardware templates

- Register general products one at a time with a name, selling unit, and selling price.
- Generate an SKU automatically or accept a custom SKU and barcode.
- Maintain store-specific categories, brands, and low-stock thresholds.
- Suggest existing category, brand, and specification values to reduce repeated typing.
- Prevent duplicate SKUs, barcodes, and matching product identities, including archived products.
- Search existing and archived products before adding another item.
- Edit supported product details and prices; archive or reactivate products.
- Use **Save and add another** to retain common setup values between entries.
- Support whole-number and measured selling units, including pieces, bags, boxes, meters, feet, kilograms, and liters.
- Define fixed pack/unit conversions, such as one roll containing 100 meters.
- Preserve established base units, conversions, and historical transaction snapshots.

Guided product types:

| Template | Structured information |
| --- | --- |
| Wire | Type, size, measurement system, and optional color |
| Pipe / fitting | Type, material, size, measurement system, and optional class/length |
| Fastener | Type, diameter, sizing system, and optional length/material |
| Steel / lumber | Material, type, dimensions, length, and optional grade |
| Paint | Type, finish, color, container size, and container unit |

Guided entries generate names from specifications, offer compatible selling units, and check duplicate specifications. A pack conversion can be saved together with the new product. Opening stock and acquisition costs are recorded separately in Inventory.

## 4. Inventory

- Record reviewed opening quantities and acquisition costs.
- View current stock and low-stock items.
- Post physical counts, positive adjustments, and damage/loss adjustments.
- Maintain a permanent inventory movement history.
- Increase stock through purchase receiving and eligible sellable returns.
- Deduct stock through sales, order releases, and recorded losses.
- Calculate outgoing stock cost using moving weighted average costing.
- Prevent negative stock and reject stale physical counts when stock has changed.
- Report inventory quantity and value as of the selected end date.

## 5. Suppliers and purchasing

- Create, edit, search, and archive suppliers.
- Create purchase-order drafts with products, units, quantities, costs, and notes.
- Edit drafts before placement, with protection against stale edits.
- Place orders and receive deliveries in full or in parts.
- Track received and remaining quantities.
- Cancel or close unfulfilled quantities when appropriate.
- Reverse eligible receipts when downstream stock movements do not prevent the correction.
- Retain original supplier and item information on historical records.

Purchase receiving updates inventory and acquisition cost. Supplier bills, accounts payable, and supplier payment settlement are not included.

## 6. Point of sale and returns

- Find products by name, SKU, or barcode.
- Add an exact barcode/SKU match by scanning and pressing Enter. Repeated scans queue independently; unknown and ambiguous codes show feedback. Manual search remains available.
- Select selling units, quantities, prices, and fixed or percentage line discounts.
- Accept recorded cash, e-wallet, bank-transfer, and card payments according to the store's settings.
- Split a payment across supported methods.
- Calculate the unpaid amount, applied payment, and cash change.
- Require references and confirmation for positive noncash payments.
- Post fully paid sales, partially paid credit sales, or wholly unpaid credit sales for eligible customers.
- Record customer references and sale notes.
- Print internal sales records and browse sales/return history.
- Process full or partial returns against the original sale, using original pricing, discounts, and cost.
- Distinguish sellable returned goods from damaged goods.
- Reduce the original sale's unpaid debt before calculating an actual refund.
- Guard against duplicate submissions, insufficient stock, changed cash sessions, and stale catalog prices.

Checkout requires an open cash session, including credit sales. The app records externally confirmed noncash payments; it does not charge or verify a payment provider.

## 7. Customers, credit, and collections

- Create customer accounts with name, contact information, address, and default payment terms.
- Search active customers from checkout and quotations.
- Explicitly allow or disallow new credit for each customer.
- Set a reviewed credit limit and check remaining capacity when posting.
- Show current outstanding balances and overdue amounts.
- Warn about overdue debt; enforce the credit limit without automatic interest or penalties.
- Charge the unpaid part of a sale to an eligible customer's account with a reviewed due date.
- Record later payments and allocate them to selected outstanding sales or opening debts.
- Suggest oldest-due allocations on the current outstanding-records page.
- Prevent collection beyond the remaining debt.
- Record reviewed pre-Pyesio opening receivables with a reference, original date, due date, and reason.
- Reverse eligible collections or opening receivables through linked corrections.
- View dated customer statements with opening/closing balances and account activity.
- Print statements and export all entries within the selected date interval to CSV.
- Archive customers while retaining their history and collection capability.

**Store access and customer approval are separate.** Enabling the Credit module makes the workflow available. A customer also needs **Allow new credit = Yes** and sufficient credit limit. A positive limit alone does not enable credit.

Later collections reduce debt and increase recorded money received; they do not create another sale. Opening receivables do not create stock, sales, profit, or cash movements.

## 8. Quotations, sales orders, pickup, and delivery

- Create quotation drafts for a customer with products, agreed prices, discounts, validity date, and optional project/site information.
- Edit drafts before issue and print quotation/order records.
- Issue a quotation and lock its version.
- Record customer acceptance; expired quotations require revision before acceptance.
- Preserve issuer, customer, item, unit, and agreed-price snapshots.
- Revise an issued quote or an order's unfulfilled remainder using a linked replacement quotation.
- Record multiple partial pickups or deliveries against one accepted order.
- Display ordered, released, cancelled, and remaining quantities per item.
- Collect payment or use approved customer credit for each release.
- Link each release to its posted sale, inventory movement, payments, receivable, and returns.
- Record recipient, delivery address/reference, and dispatch information.
- Confirm delivery receipt separately from dispatch.
- Close or cancel the unfulfilled remainder with a reason.
- Prevent releases beyond available stock or the remaining order quantity.
- Preserve exact agreed totals across partial-release rounding.

Quotes and acceptance do not reserve stock or post a sale. A sale is recorded when goods are physically released. **Completed** means all goods were released; delivery receipt and customer payment can still be outstanding. Returns retain the original release history and do not automatically reopen an order.

## 9. Cash drawer and expenses

- Open one cash session per store with a counted starting float.
- Record manual cash-in/cash-out movements with reasons.
- Include cash sales, collections, refunds, expenses, and eligible reversals in expected cash.
- Count and close a drawer, recording its variance and an explanation when needed.
- View, print, and export complete cash-session movement histories.
- Record paid operating expenses by cash or confirmed noncash method.
- Reverse eligible expenses with a linked reason and corresponding money movement.
- Preserve closed-session counts and historical records.

## 10. Reports and supporting records

Store and platform reports use shared definitions for:

- Gross sales, discounts, returns, and recorded net sales.
- Actual customer payments received, refunds, and net payments.
- Outstanding and overdue receivables as of a selected date.
- Cost of goods sold and estimated gross profit.
- Paid operating expenses, inventory losses/gains, and estimated operating profit.
- Inventory value and sale count.

Additional reporting includes product performance, end-date stock quantities/value, customer statements, and cash reconciliation. Relevant reports support date filters, pagination, printing, and CSV exports. Pagination does not reduce report totals or the scope of complete exports.

Posted records can hold private JPG, PNG, or PDF supporting attachments, up to 5 MB each and ten files per record. Access is checked against the selected store. Store activity and platform audit records retain the actor and action history.

Profit figures are management estimates based on recorded transactions. Printed sales documents are internal records; tax calculation and registered fiscal invoicing are not implemented.

## 11. Account security and email

- Invitation-based access with public registration disabled.
- Login, email verification, password reset, and account profile/security settings.
- TOTP two-factor authentication and recovery codes; platform administrator access requires MFA.
- Store-scoped access checks and administrator permission enforcement.
- Read-only administrator store inspection without changing to the owner's identity.
- Transactional posting and retry protection for financial and stock operations.
- Branded HTML and plain-text SMTP messages for invitations, recovery, verification, security notices, and opted-in announcements.
- Background delivery with automatic retries, delivery statuses, and administrator retry controls.
- A delivery-test action limited to the signed-in administrator's own email address.

Email “Sent” means the SMTP server accepted the message. Inbox placement, read receipts, inbound mailbox access, and bounce/complaint processing are not provided by this feature.

## 12. Operations and feature availability

- Laravel application deployed on Hostinger, using the MySQL connection with the host's compatible database service.
- Online operation with a private application directory and protected environment/storage files.
- Scheduled background processing and daily on-server backups at **02:00 Asia/Manila**.
- Backup checksums and archive-readability verification, plus an administrator health screen.
- Additive upgrade approach that preserves existing accounts, product units, posted records, and historical balances.

The ten configurable store modules are:

| Module | Key | Main dependencies for new work |
| --- | --- | --- |
| Products | `catalog` | Store access |
| Inventory | `inventory` | Catalog |
| Cash drawer | `cash` | Store access |
| Sales | `sales` | Catalog, Inventory, Cash |
| Purchasing | `purchasing` | Catalog, Inventory |
| Expenses | `expenses` | Cash |
| Reports | `reports` | Store access and recorded data |
| Customers | `customers` | Store access |
| Credit | `credit` | Customers, Sales, Cash, and their dependencies |
| Quotes & orders | `orders` | Customers and Catalog; releases also require Sales, Inventory, and Cash, plus Credit when applicable |

For an existing store, change settings through **Admin Portal → Manage → Module access → Save module access**. The checked modules in **Register a store** apply only to the new store being registered.

Customers, Credit, and Orders were initially disabled for existing stores during the compatible upgrade. They were subsequently enabled and verified for both current pilot stores on November 4. Each customer's individual credit approval remains a separate setting.

The recorded release verification includes **150 passing PHP tests / 1,312 assertions**, **8 JavaScript tests**, code checks, and the production verification described in [Scanner and receipt printing](scanner-receipts.md). Earlier releases used rolled-back synthetic transactions; the scanner/printing deployment used read-only checks and existing records. Backup restore targets, large-scale performance, and off-site recovery are separate operational follow-ups.

## 13. Not yet included

| Area | Current boundary |
| --- | --- |
| Store acquisition and subscriptions | Public signup/intent submissions, approval/trial flows, subscription plans, recurring billing, and automated subscription enforcement are not implemented. |
| Additional staff | Separate cashier, manager, inventory-staff roles, and general staff-user administration are not implemented. |
| Branches | Independent stores are supported; a business with multiple branches, shared stock, and inter-branch transfers is not implemented. |
| Offline use | Offline POS, local transaction queues, and later synchronization are not implemented. |
| Fiscal processing | VAT/tax calculation, registered invoice numbering, and fiscal certification are not implemented. |
| Payment providers | Payment gateways, automatic charging, and provider transaction verification are not implemented. |
| Customer advances | Deposits, prepayments, unallocated overpayments, and customer advance balances are not implemented. |
| Credit extensions | Interest, late fees, write-offs, cross-customer transfers, and automatic customer reminders are not implemented. |
| Order reservations | Quotation/order stock reservations are not implemented; availability is checked at release. |
| Advanced delivery | Driver accounts, route planning, delivery-fee calculation, public tracking, digital signatures, and dedicated proof-of-delivery uploads are not implemented. General posted-record attachments are available. |
| Accounting and payables | Full general ledger, statutory accounts, supplier payables/settlements, payroll, and depreciation processing are not implemented. |
| Catalog imports | Bulk supplier-spreadsheet import and automatic supplier-catalog synchronization are not implemented. |
| Recovery and scale | Independent off-site backups, finalized retention/recovery targets, production-size timed restore, per-store extraction drills, and the original 12-month load benchmark remain follow-ups. |

## Reference documents

- [User guide](user-guide.md) — step-by-step operation and setup.
- [Release notes](release-notes.md) — deployed changes and verification evidence.
- [SMTP email](smtp-email.md) — email workflows and delivery behavior.
- [Reporting](reporting.md) — report definitions and calculations.
- [Hardware growth plan](hardware-growth-plan.md) — approved hardware-specific flows and scope decisions.

This is a dated feature inventory, not a promise of future delivery. Planning documents may retain historical or proposed requirements; use this inventory and the latest release notes to identify shipped capabilities.

# Using Hardware Store Management

This release supports online daily operations for independent stores. Open [the live app](https://wgalas.lzrk.io/login) and use your existing account. Store activation and recovery emails are queued for SMTP delivery. Allow a few minutes and check spam; the Platform Admin can also share links privately.

## Start as the Platform Admin

1. Sign in. Complete **Settings → Security → Two-factor authentication** if prompted. Keep your recovery codes privately.
2. Open **Admin Portal → Register a store**. Enter the store's name, unique code, physical address, and owner's login email. Select the required modules and their dependencies from the ten available modules. These selections apply to the new store only; use **Manage → Module access → Save module access** to change an existing store.
3. Copy the activation link shown after registration and share it privately with the owner. It expires after 72 hours. The admin never sets or sees the owner's password.
4. The owner opens the link, chooses a password, then signs in. An existing account must sign in as that identity before accepting. If you open an owner's link while signed in as admin, sign out first.
5. Return to **Admin Portal** for each store's recorded sales and profit estimates. Filters and CSV use Asia/Manila time. Suspended and archived history remains included and each currency has separate totals.

**Manage** opens store details, status, modules, invitations, and owner recovery. Resending creates a new invitation and invalidates earlier pending links. Owner replacement takes effect on acceptance; the former membership is revoked while historical actors remain unchanged.

Enabled modules permit new work. Restricted modules preserve history and linked corrections. Sales require Catalog, Inventory, and Cash; Purchasing requires Catalog and Inventory; Expenses require Cash. Backend validation rejects incompatible combinations and disabled operations even from old open tabs.

For announcements, choose all stores or selected stores. A blank publication time creates a draft. **Publish now**, scheduled publication, expiration, and withdrawal control visibility. Date/time inputs use Asia/Manila.

## Setup checklist for new stores

Open **Store Portal → Overview → Set up your store**. The checklist shows completed steps, a progress bar, and a next-step button. Use **Setup checklist** at the top of other store screens to return after saving.

With the core modules enabled, it covers reviewing and saving contact details, saving accepted payment methods, adding the first product, recording opening stock or a received purchase, opening the first cash drawer, and posting the first real sale. Merely visiting a screen does not complete a step. The checklist follows the store’s enabled modules, and existing records receive credit automatically.

Progress belongs to the store and is calculated from its saved records and profile/payment audit events. It does not reset at sign-out, when stock sells out, or when a drawer closes. The checklist is guidance; it never blocks work or asks you to create a sample sale. It starts expanded for stores that have not made a sale and still have setup steps, then becomes a compact summary after the first sale or completion. Click its heading to expand or collapse it.

An administrator can inspect the same progress through **View store**. All checklist links stay in that read-only store view; the administrator cannot complete owner actions there.

## Prepare a store for its first sale

1. Open **Store Portal**, then select the store.
2. **Products → Add a product:** enter the name and size/variant, choose **Sell by**, and enter the selling price for one of that unit. The app generates a unique SKU automatically (for example, `P-000001`). Measured units such as meters and kilograms allow decimals automatically; pieces, boxes, sets, and other countable units use whole quantities. Prices start blank so zero must be intentional.
3. For boxes or bundles, open the product details and add a fixed conversion. Example: `box100` equals `100 piece`. Conversions and base units cannot be rewritten; add a new conversion label or SKU when packaging changes.
4. **Inventory:** expand each product and post opening quantity with its reviewed acquisition cost **per base unit**. Example: 200 pieces at PHP 10 each creates PHP 2,000 of stock value. Zero-cost inventory must be entered explicitly as zero.
5. **Cash drawer:** count the starting cash already in the drawer and open a session (for example, PHP 500 for giving change; enter zero only if the drawer is empty). One session is allowed per store.
6. **Point of sale:** search by name, SKU, or barcode. Select products, units, and quantities. Review prices and any fixed or percentage line discounts.
7. Choose a payment method and enter the amount received. Use **Exact remaining** to fill the balance or **+ Split payment** for a second method. Review **Still to collect** and **Cash change** before submitting. Configure accepted checkout methods in **Store profile**. Enter only completed card, bank, or e-wallet amounts. Noncash payments require a reference and confirmation. The app records payments; it does not charge or verify a payment provider.
8. Choose **Post paid sale**. Only the server's posted record confirms completion. The print view shows applied payments, cash tendered, and change.

Scanning an exact barcode or SKU and pressing Enter adds the product. If its catalog price changes before posting, review the current price on the restored cart. The app prevents negative stock, underpayment, duplicate submissions, and posting to a cash session that changed after the form opened. If your connection fails after submission, check sales history before starting a fresh sale. Repeating the same submission key returns its original outcome; changing its details is rejected.

### Adding products one at a time

Expand **Optional details** to choose a category or brand from your store’s lists. Common hardware categories are provided. Choose **Add new** only when the label is missing; it becomes available for the next product. Extra spaces and differences in capitalization reuse the existing label. You can also scan/type a barcode, supply your own SKU, or set a low-stock alert. A blank custom SKU is generated on save; a zero low-stock level alerts at out of stock.

Use **Save and add another** for a run of products. It keeps the selling unit, category, and brand while clearing the name, price, barcode, custom SKU, and stock alert. The next name field receives focus. Review the unit and price for every variant. Opening stock is still recorded separately in Inventory, with its reviewed cost.

Duplicate SKUs and barcodes are blocked. The same product name, brand, and base unit cannot be registered again, including archived products; find and edit/reactivate the existing product or include the different size/variant in its name. Repeated submit clicks are guarded, and validation keeps entries in the form you were editing. Existing products retain their original units and stock history.

## During the day

- **Purchasing:** add a supplier, create a draft order, then place it. Record deliveries in the order's selected units. Partial receipts increase only received stock. Close/cancel outstanding quantities when appropriate. Open **Edit draft order** to correct supplier, quantities, units, costs, or notes before placement. Changes from an older tab are rejected. Draft edits have no stock effect; acquisition costs do not automatically create cash expenses or supplier settlements.
- **Suppliers:** correct supplier details or archive a supplier in Purchasing. Archived suppliers cannot be selected for new drafts or placements; existing receipts and supplier-name snapshots remain available.
- **Inventory:** use physical count for the total actually counted, or damage for the quantity removed. If stock changes while the form is open, the submitted count remains visible but cannot be posted: reload, recount, and enter the new count. Positive additions require a cost. Losses use the current moving weighted average cost.
- **Returns:** open the original sale, expand the return form, enter quantities in **base units**, choose sellable or damaged, and confirm the refund. Partial returns use the original pricing, discount, and cost. Damaged returns record a stock loss. An open cash session is required even for a noncash refund.
- **Expenses:** record only paid operating costs. Cash expenses reduce the drawer once; noncash expenses do not. Do not enter inventory purchases, owner drawings, or opening float as operating expenses.
- **Corrections:** posted records cannot be edited. Full expense reversals credit the current drawer or record a confirmed recovered noncash payment. Receipt reversals require the receipt to remain the latest stock movement for every affected product; downstream stock movements block reversal.
- **Find a record:** Sales and Expenses history can be filtered by record number, customer/payee/notes, type, and local posting-date range. Open the matching record to print, attach supporting files, or make an allowed correction.
- **Supporting documents:** open a posted record to attach a receipt or delivery document. JPG, PNG, and PDF are supported, up to 5 MB each and ten files per record. Files remain private to the store and download through authorized routes.

## Close and review

1. Pause checkout and count actual cash.
2. In **Cash drawer**, enter the count and an explanation if it differs from expected cash. Close the session. Closing counts are permanent; a cash difference does not become an invented sale or expense.
3. Open **Details / export** beside any cash session to review its movements, expected/count/variance, print the session, or download the complete CSV.
4. Use **Reports** to select posting dates, inspect sales, payments, cost, expenses, inventory losses, estimated profit, product performance, and end-date inventory.
5. Export summary, product, or inventory CSV. Quantity columns retain their base unit; unlike units are not added together.

The operating estimate equals recorded sales after returns minus recorded cost of goods sold, paid operating expenses, and inventory losses. It excludes unpaid liabilities, depreciation, financing costs, and income tax. Stock gains are shown separately. Tax has not been separated from recorded amounts, so use these as management estimates.

## Invoice and tax boundary

The printed sale is an **internal sales record**, not a BIR-registered invoice. No VAT calculation or tax-exempt status is inferred. Continue using the store's existing registered invoicing process. Tax setup, compliant document numbering, and the relevant fiscal requirements require each store's actual registration details before this app replaces that process. [BIR invoicing clarification](https://bir-cdn.bir.gov.ph/BIR/pdf/RMC%20No.%2077-2024.pdf).

## System status

Platform administrators with store-management access can open **System status** to check the scheduler heartbeat, latest verified backup, mail configuration, production debug mode, and failed jobs. A missing heartbeat or stale backup needs operator attention. Backups run daily at 02:00 Asia/Manila; verification checks archive readability and checksums and is separate from a full restore rehearsal.

## Account access and help

Store owners can ask the Platform Admin for a new activation link or a 60-minute owner recovery link after identity verification. Existing account passwords remain unchanged during store invitations. Password recovery invalidates database sessions. Platform administrator recovery is intentionally not exposed through merchant recovery controls; retain TOTP recovery codes and use configured email or the operator's verified administrative procedure.

The platform administrator can view merchant reports and inspect store workspaces read-only. Customer credit, quotations, and basic pickup/delivery are available when the relevant modules are enabled; see the hardware workflows below. Additional staff roles, offline selling, multi-location businesses, provider payment integrations, and statutory accounting remain outside this release. The [current feature inventory](current-features.md) lists shipped capabilities and limitations.


## Email delivery

Registering a store or creating an owner invitation queues an activation email. **Forgot password** queues a recovery email without revealing account existence. **Manage store → Owner password recovery** offers email delivery or a private-link fallback after identity verification. Changing your login email requires verification of the new address and notifies the previous address.

Admins can open **Email delivery** to review pending, sending, sent, failed, or cancelled messages. Sent means accepted by the mail server. Automatic retries run in the background; an eligible failed delivery can be retried without creating a new account link. Expired links require a new invitation or recovery request. **Send test to my email** sends only to your signed-in account address.

Announcements stay in the portal unless **Also email active store owners when published** is selected. Email recipients are current active owners when the scheduler first processes publication. Withdrawing prevents unsent messages. See [SMTP email](smtp-email.md) for timing and recovery details.


## View inside a store as administrator

Open **Admin Portal → Stores & reports → View store**, or use **View store** from the store's Manage page. The banner says **Viewing as administrator · Read-only** and shows your own signed-in identity. Browse the store's products, inventory, purchases, sales history, cash, expenses, reports, activity, and profile. Open records and supporting attachments from their history pages. Use **Back to Admin Portal** or **Choose another store** to leave the view.

Your visits are recorded in the audit trail. Posting, editing, stock adjustments, refunds, receiving, drawer changes, and attachment uploads are unavailable in this view. CSV buttons appear only with export permission. Full administrators receive viewing access automatically; a limited admin needs the separate store-view permission. Suspended and archived stores remain available for historical inspection.

## Finding records without long scrolling

Lists start with 10 records. Use **Rows → Apply** for 25 or 50, **Previous / Next** at either end, or **Go to** to jump to a page. Filters stay selected while paging. Changing row count resets the lists to their first page. Desktop tables scroll inside their panel and keep column headings visible.

Use the search field for products, stores, suppliers, orders, or transaction history. **Jump to** links take you directly to history and report sections. Open **Details / edit** in Products to work on one record; **Close** or Escape takes you back to the same row. Validation errors reopen that product’s dialog.

Page sizes affect only the displayed rows. Report totals and CSV exports still cover the full selected scope. On the Admin Portal, store search filters the directory; report dates and store scope control totals and exports. Printing a list prints the current page; export CSV for a complete report.


## Hardware workflows — November 4 extension

Existing products and fully paid checkout continue to work. No owner needs a replacement account or re-entered stock. The platform admin enables **Customers**, **Credit**, and **Orders** for a store under **Manage → Module access**. New modules start disabled for existing stores. Credit depends on Customers/Sales/Cash; Orders depends on Customers/Catalog, and physical releases additionally require Sales/Inventory/Cash.

### Guided product entry

Under **Products → Add a product**, choose General product or a guided Wire, Pipe/Fitting, Fastener, Steel/Lumber, or Paint type. Enter the relevant specifications and select the actual measurement system. The name is generated from the specifications; the selling unit and price stay visible. **Check existing products** searches current and archived names so a legacy item is not overlooked. Exact duplicate name/brand/unit and guided specifications are also checked at save.

Optional **Pack conversion** records actual contents, for example 1 roll = 100 meters. It is saved atomically with the product and cannot be rewritten later. Opening quantities/costs still belong in Inventory. Save-and-add-another keeps product type, unit, category, and brand while clearing price, codes, and item specifications. Recent store specification values are suggested; another store's values are never used.

### Customers and credit

Create a customer under **Customers**. Leave credit off for paid-only buyers. To allow debt, set **Allow new credit → Yes**, a positive credit limit, and a default term, then save the customer. Enabling the store's Credit module or entering a positive limit does not automatically approve that customer for credit. A credit limit cannot be lowered below the outstanding balance while credit stays enabled. Archiving a customer prevents new credit/orders but retains collection and history.

At **Point of sale**, choose the customer and **Charge balance to customer**. Review the due date (selecting a customer applies their default term), amount received, and remaining amount. Enter zero received for a wholly unpaid sale. The current open cash session is required at checkout. Credit limits are checked again when posting; overdue accounts show a warning and do not incur automatic fees.

A customer's account shows current outstanding and overdue balances, unpaid records, and a dated statement. **Record collection** allocates a confirmed receipt to one or more unpaid records on the current page. **Suggest oldest due first on this page** helps fill those allocations; the total must equal money actually received. Cash collections require the current drawer; confirmed noncash receipts need a reference. Collections never create a second sale.

Use **Record reviewed opening debt** only for verified balances from before Pyesio. Include the original reference/date, due date, and reason. This creates a receivable but no new sales, profit, stock, or cash. The original date does not backdate Pyesio reports.

A sale return reduces its unpaid debt first and refunds only the excess attributable to money already paid. The posted return separates debt reduction from money refunded. Correct an eligible collection/opening debt with its linked reversal and reason; later active settlement or return activity can block reversal. Closed cash-session history is never rewritten.

Statement filters apply to account entries; the current-balance cards remain current. Printing shows the current page, while **Export complete statement** includes every entry in the selected interval. Account statements and customer data are scoped to the store; administrator inspection is read-only.

### Quotations, pickup, and delivery

1. In **Quotes & orders**, create a draft for a customer with products, agreed prices/discounts, validity date, and optional project/site. Edit drafts freely; they have no financial or stock effects.
2. **Issue quotation & lock this version**, then record the customer's acceptance reference. Expired quotations require a new revision. Issuing/accepting does not reserve stock.
3. **Record pickup or delivery** and enter quantities physically leaving the store in the displayed base units. Review the release total, recipient/address, and actual payment or approved customer credit. Confirming posts the sale and stock/cost/payment/receivable effects together.
4. Each release has a linked sale with its own payments, balance, and returns. The order displays ordered/released/cancelled/remaining quantities. **Completed** means all ordered goods have been released, not that every delivery is received or all debt is paid.
5. Pickup records receipt immediately. For a delivery, use **Confirm received now** only when receipt is confirmed. It does not post another sale or stock deduction. For a failed delivery, record a return against its linked sale when goods physically return. Fully returned dispatches cannot subsequently be marked received.
6. Cancel only the unfulfilled remainder with a reason. To change agreed remaining items/prices, **Create revised quotation** closes the old remainder and creates a linked replacement draft. Review, issue, and obtain acceptance again. Existing releases retain their original record and prices.

Stock reservations, pre-release deposits/prepayment, delivery fees, driver accounts, automatic customer messages, and public tracking are not supported in this increment. Do not use manual cash-in or a fabricated sale to simulate a customer deposit. These remain internal management records with no configured fiscal invoice/tax processing.

## Scanner and receipt printing

Use **Ready to scan / type**, then scan an exact registered barcode/SKU followed by Enter. Repeated scans add one base unit each; measured quantities and pack lines need review. Manual product search remains available.

Use **Printer setup & test print** from POS to check a sample without creating a sale. Posted sales and returns offer Document/A4, 58 mm, 80 mm previews and **Reprint a COPY**. See [setup and verification](scanner-receipts.md).

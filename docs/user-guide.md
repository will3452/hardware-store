# Using Hardware Store Management

This release supports online daily operations for independent stores. Open [the live app](https://wgalas.lzrk.io/login) and use your existing account. Store activation and recovery links are shared privately by the Platform Admin until an email transport is configured.

## Start as the Platform Admin

1. Sign in. Complete **Settings → Security → Two-factor authentication** if prompted. Keep your recovery codes privately.
2. Open **Admin Portal → Register a store**. Enter the store's name, unique code, physical address, and owner's login email. Leave all seven modules enabled for a normal hardware store.
3. Copy the activation link shown after registration and share it privately with the owner. It expires after 72 hours. The admin never sets or sees the owner's password.
4. The owner opens the link, chooses a password, then signs in. An existing account must sign in as that identity before accepting. If you open an owner's link while signed in as admin, sign out first.
5. Return to **Admin Portal** for each store's recorded sales and profit estimates. Filters and CSV use Asia/Manila time. Suspended and archived history remains included and each currency has separate totals.

**Manage** opens store details, status, modules, invitations, and owner recovery. Resending creates a new invitation and invalidates earlier pending links. Owner replacement takes effect on acceptance; the former membership is revoked while historical actors remain unchanged.

Enabled modules permit new work. Restricted modules preserve history and linked corrections. Sales require Catalog, Inventory, and Cash; Purchasing requires Catalog and Inventory; Expenses require Cash. Backend validation rejects incompatible combinations and disabled operations even from old open tabs.

For announcements, choose all stores or selected stores. A blank publication time creates a draft. **Publish now**, scheduled publication, expiration, and withdrawal control visibility. Date/time inputs use Asia/Manila.

## Prepare a store for its first sale

1. Open **Store Portal**, then select the store.
2. **Products:** add each variant with its own SKU. Choose a base unit: piece, meter, kg, etc. Enable fractional quantities only when appropriate. The selling price is per base unit.
3. For boxes or bundles, open the product details and add a fixed conversion. Example: `box100` equals `100 piece`. Conversions and base units cannot be rewritten; add a new conversion label or SKU when packaging changes.
4. **Inventory:** expand each product and post opening quantity with its reviewed acquisition cost **per base unit**. Example: 200 pieces at PHP 10 each creates PHP 2,000 of stock value. Zero-cost inventory must be entered explicitly as zero.
5. **Cash drawer:** count your opening float and open a session. One session is allowed per store.
6. **Point of sale:** search by name, SKU, or barcode. Select products, units, and quantities. Review prices and any fixed or percentage line discounts.
7. Enter cash tendered and any completed card, bank, or e-wallet amounts. Noncash payments require a reference and confirmation. The app records payments; it does not charge or verify a payment provider.
8. Choose **Post paid sale**. Only the server's posted record confirms completion. The print view shows applied payments, cash tendered, and change.

The app prevents negative stock, underpayment, duplicate submissions, and posting to a cash session that changed after the form opened. If your connection fails after submission, check sales history before starting a fresh sale. Repeating the same submission key returns its original outcome; changing its details is rejected.

## During the day

- **Purchasing:** add a supplier, create a draft order, then place it. Record deliveries in the order's selected units. Partial receipts increase only received stock. Close/cancel outstanding quantities when appropriate. Correct a draft by cancelling and replacing it; acquisition costs do not automatically create cash expenses or supplier settlements.
- **Inventory:** use physical count for the total actually counted, or damage for the quantity removed. A changed stock version forces a fresh count. Positive additions require a cost. Losses use the current moving weighted average cost.
- **Returns:** open the original sale, expand the return form, enter quantities in **base units**, choose sellable or damaged, and confirm the refund. Partial returns use the original pricing, discount, and cost. Damaged returns record a stock loss. An open cash session is required even for a noncash refund.
- **Expenses:** record only paid operating costs. Cash expenses reduce the drawer once; noncash expenses do not. Do not enter inventory purchases, owner drawings, or opening float as operating expenses.
- **Corrections:** posted records cannot be edited. Full expense reversals credit the current drawer or record a confirmed recovered noncash payment. Receipt reversals require the receipt to remain the latest stock movement for every affected product; downstream stock movements block reversal.
- **Supporting documents:** open a posted record to attach a receipt or delivery document. JPG, PNG, and PDF are supported, up to 5 MB each and ten files per record. Files remain private to the store and download through authorized routes.

## Close and review

1. Pause checkout and count actual cash.
2. In **Cash drawer**, enter the count and an explanation if it differs from expected cash. Close the session. Closing counts are permanent; a cash difference does not become an invented sale or expense.
3. Use **Reports** to select posting dates, inspect sales, payments, cost, expenses, inventory losses, estimated profit, product performance, and end-date inventory.
4. Export summary, product, or inventory CSV. Quantity columns retain their base unit; unlike units are not added together.

The operating estimate equals recorded sales after returns minus recorded cost of goods sold, paid operating expenses, and inventory losses. It excludes unpaid liabilities, depreciation, financing costs, and income tax. Stock gains are shown separately. Tax has not been separated from recorded amounts, so use these as management estimates.

## Invoice and tax boundary

The printed sale is an **internal sales record**, not a BIR-registered invoice. No VAT calculation or tax-exempt status is inferred. Continue using the store's existing registered invoicing process. Tax setup, compliant document numbering, and the relevant fiscal requirements require each store's actual registration details before this app replaces that process. [BIR invoicing clarification](https://bir-cdn.bir.gov.ph/BIR/pdf/RMC%20No.%2077-2024.pdf).

## Account access and help

Store owners can ask the Platform Admin for a new activation link or a 60-minute owner recovery link after identity verification. Existing account passwords remain unchanged during store invitations. Password recovery invalidates database sessions. Platform administrator recovery is intentionally not exposed through merchant recovery controls; retain TOTP recovery codes and use configured email or the operator's verified administrative procedure.

The platform administrator can view merchant reports. Additional staff roles, customer credit, offline selling, quotations, deliveries, multi-location businesses, provider payment integrations, and statutory accounting are outside this release.

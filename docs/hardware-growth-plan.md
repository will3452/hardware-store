# Pyesio hardware store growth plan

Planning date: November 3, 2026, Asia/Manila.

Status: the product owner approved the detailed flow and required compatibility with existing production users. H1–H3 were deployed and verified on November 4. Production evidence is recorded in the release notes. Existing stores must explicitly enable the new customer, credit, and order modules.

## Outcome and delivery order

Help a small hardware store register the right item, keep track of what customers owe, and fulfill an order across several pickups or deliveries without repeatedly entering the same information.

| Order | Priority | Owner benefit | First complete workflow |
| --- | --- | --- | --- |
| H1 | Hardware product templates | Less typing and fewer ambiguous sizes or units | Choose type → fill relevant specifications → review product and conversion → save |
| H2 | Customer credit and collections | Know who owes what and which payments are overdue | Select customer → post paid/part-paid/credit sale → collect payment → review statement |
| H3 | Quotations and partial fulfillment | Track an agreed order and exactly what still needs releasing | Quote → accepted order → partial pickup/delivery → remaining quantities → complete |

H1 builds on the existing catalog. H2 establishes customer identity, receivables, and independent payment reporting. H3 reuses those records and the posting engine. Ship and verify each complete workflow before enabling the next one for pilot stores. These priorities do not imply that public signup, subscription billing, pricing, or trial policies have been approved.

The intended differentiation is convenience across a hardware store's daily workflow. Faster setup, fewer corrections, and willingness to pay must be demonstrated with owners; feature exclusivity and market success are not established claims.

## H1 — Hardware product templates

### Owner flow

1. Open **Products → Add product**. Choose a guided product type or keep the existing **General product** form.
2. Show only the specifications relevant to that type. Reuse the store's brands and previously entered specification values; allow an explicit custom value when the actual item differs.
3. Preview a readable product name containing the distinguishing specifications. Show the selling unit beside the price, and keep SKU generation automatic.
4. Optionally add a purchase/sales pack and its actual contents, such as **1 roll = 100 meters**. The owner must supply and review the contents; the system must not guess a supplier's packaging.
5. Save the product and any conversion together. **Save and add another** retains reusable choices, but clears the new item's price, barcode, SKU, and distinguishing specifications.
6. Record opening stock separately through the existing reviewed inventory workflow. Choosing a template does not create stock or invent a cost.

### Initial template families

| Family | Relevant specifications | Unit decisions |
| --- | --- | --- |
| Electrical wire | Wire type, size value and its system, color when relevant | Meter/foot versus whole roll; owner confirms roll contents |
| Pipe and fittings | Item type, material, nominal size and unit, class/schedule where relevant; length for pipe | Piece or length, with an explicit conversion only when applicable |
| Fasteners | Type, diameter, length, material/finish | Piece, pack, box, or weight; never infer piece count from weight |
| Steel and lumber | Material/profile, section dimensions, length, grade where used | Piece or length; different stocked lengths remain distinct SKUs |
| Paint | Product type, finish, color/code, container size and unit | Whole container or measured volume, according to how the store sells it |

Start with wire, pipe/fittings, and fasteners for the first pilot demonstration; use the same template structure for steel/lumber and paint. Suggested values are convenience choices, not a technical specification catalog. AWG and square millimeters, nominal and measured pipe sizes, and different gauge systems must remain explicitly labeled rather than being treated as interchangeable.

### Requirements

- Persist optional template key, template version, and validated structured specifications alongside the existing product. Existing products remain usable without conversion or mandatory re-entry.
- Normalize harmless whitespace/case differences for duplicate checks. Compare structured identity within the store, including brand and base unit; also show likely existing legacy products. Include archived products in duplicate detection.
- Require the distinguishing specifications for the selected template. Preserve labels and measurement units in the generated name; do not silently convert unfamiliar values.
- Validate allowed template fields and bounded values on the server. A modified browser request cannot inject unsupported specifications or incompatible unit conversions.
- Keep existing base-unit and fractional-quantity invariants. A template update must never rewrite saved specifications, conversions, stock, or posted document snapshots.
- Apply the existing catalog entitlement, tenant isolation, audit trail, pagination, responsive layout, and administrator read-only behavior.
- Do not turn the creation form into a large grid of fields. Optional technical detail stays collapsed, while the name, distinguishing specs, selling unit, and price remain clear.

### Acceptance evidence

- Create two otherwise identical wires with different sizes as separate products; reject a duplicate of the same structured item within one store, including an archived match.
- A store cannot see another store's custom brands/specification suggestions or duplicate matches.
- Save a wire with a confirmed roll conversion, receive 2 rolls of 100 meters, and sell 3.5 meters: 196.5 meters remain.
- An invalid pack conversion rolls back both the product and conversion; retry does not create duplicate records.
- Existing general products, custom legacy units, catalog search, editing, paid checkout, and historical documents continue to work.
- Check phone and desktop forms, keyboard navigation, validation recovery, and **Save and add another** with real rendered screens.

## H2 — Customer credit and collections

### Owner flow

1. Add a **Customer** with a display name, optional company/contact details and address. A customer is not a user account. A walk-in fully paid sale still needs no customer account.
2. Explicitly enable credit for the customer, with a reviewed credit limit and default term. Proposed default: credit is off for new customers, no interest or penalties, and no override above the approved limit.
3. At checkout choose **Pay in full** or **Charge to customer**. Show sale total, amount received now, remaining balance, and due date before posting.
4. A credit sale requires a customer and due date. Zero or partial payment is allowed only through this explicit flow and within the current credit limit.
5. Open the customer's account to see outstanding sales, due dates, overdue amounts, collections, and returns. Search and pagination are required from the first release.
6. **Record collection** selects one or more outstanding sales for that customer and allocates the confirmed payment. Offer oldest-due-first allocation as a visible, editable suggestion. Show the remaining balance before saving.
7. Print or export a dated customer statement with opening balance, charges, collections, return adjustments, closing balance, and overdue items. Statement dates use the store's timezone.

### Posting and settlement rules

- A receivable is an append-only ledger with linked source documents and payment allocations. A mutable customer balance field is not the source of truth.
- Sale, immediate payments, receivable charge/settlement, stock, cost, cash movement, and audit must commit atomically. Subsequent collection changes receivables and money received, not sales or inventory.
- Collections cannot exceed the selected outstanding balances. Customer deposits, unallocated overpayments, interest, fees, write-offs, and cross-customer transfers are deferred.
- Support an explicit **Opening receivable** for a reviewed pre-Pyesio debt: amount, reference, original date, due date, and reason. It changes receivables only, with no invented sale, stock movement, profit, or drawer cash. Historical as-of reports begin at its actual entry timestamp; the original date is reference information.
- Cash receipts and refunds require the current open session. Keep the existing open-session requirement for checkout, including credit checkout, in this increment. Confirm noncash receipt externally using the existing payment-method rules.
- Enforce limits against current unpaid balances inside the posting transaction. Concurrent sales cannot both consume the same remaining credit capacity.
- Proposed overdue policy: warn before new credit; keep the credit limit as the enforced ceiling. A stricter overdue block is a separate owner policy decision.
- A return first reduces the original sale's unpaid balance. Refund only the excess attributable to money already received. Show the debt reduction and actual refund separately; never refund the entire return amount when part is unpaid.
- Preserve original pricing, discounts, quantities, and cost allocation on returns. Reversals are linked corrections with reasons; block a correction if later dependent settlement/refund activity makes the simple reversal inconsistent.
- Customers with history can be archived but not deleted. Archived customers remain collectible and reportable, but cannot receive new credit.

### Reporting changes required before enabling credit

The current `Reports::summarize()` derives collections from sale totals because all current sales are fully paid. H2 must replace that assumption in both portals, exports, cash reports, and document displays.

| Measure | Required source after H2 |
| --- | --- |
| Recorded sales and profit estimate | Posted sales/returns and original cost snapshots |
| Collections | Actual confirmed customer receipts posted in the selected period, including later collections |
| Refunds paid | Actual money refunded in the period; exclude debt-only reductions |
| Receivables as of a date | Opening receivables + charges − applied receipts − debt-reducing returns, including linked reversals, through the cutoff |
| Overdue receivables | Remaining amounts with due date before the selected local as-of date |
| Expected drawer cash | Existing cash formula plus cash collections and their eligible reversals |

Use scoped payment kinds/source links so supplier receipts, expenses, opening float, and manual owner cash additions cannot become customer collections. Legacy paid sales remain fully settled with no fabricated customer accounts or debts. Existing historical totals must reconcile before and after migration.

Example: goods totaling PHP 5,000 are released today, with PHP 2,000 paid now and PHP 3,000 due. Today's sales are PHP 5,000, collections PHP 2,000, and outstanding receivables PHP 3,000. Collecting PHP 1,000 tomorrow records tomorrow's collections and reduces the debt to PHP 2,000 without recording another sale.

### Acceptance evidence

- Verify zero-payment, partial-payment, fully paid, late collection, multiple-sale allocation, and opening-receivable cases.
- Verify a partially paid PHP 1,000 sale with PHP 300 received and a PHP 900 return: PHP 700 debt reduction, PHP 200 refund, no remaining debt.
- Duplicate collection submission posts once; simultaneous collections cannot over-settle a sale; concurrent sales cannot exceed a customer's limit.
- Statements and platform/store totals reconcile across different posting dates, timezones, returns, and eligible corrections.
- Changing a payment method, module entitlement, customer credit limit, or store state invalidates stale new-work submissions.
- Customer searches, documents, exports, collections, and admin viewing pass cross-store isolation checks. Admin inspection exposes no mutation forms or routes.

## H3 — Quotations, orders, and partial pickup/delivery

### Owner flow

1. Choose a customer and create a quotation with products, quantities, units, prices, discounts, validity date, and optional project/site reference. Show a printable **Quotation** and save revisions.
2. Record the customer's acceptance and convert that exact revision into one sales order. Preserve the agreed item and price snapshots. An expired quotation requires renewal; a duplicate conversion returns the existing order.
3. Show ordered, already released, cancelled, and remaining quantities per line. Payment status and fulfillment status are independent.
4. Choose **Record pickup** or **Record delivery**. Enter only the quantities physically leaving the store, the recipient, and the pickup/delivery details. Preview the linked sale and any amount to be collected or charged to the customer's credit account.
5. Confirm once. Post the sale, its stock/cost/payment/receivable effects, and the order's released quantities in one transaction. Produce a linked release record. For delivery, distinguish dispatch from the later recorded customer receipt.
6. Keep the remaining quantities open for another release. Close only the unfulfilled remainder with a reason; already released goods use the normal return workflow.

### Proposed first-version boundaries

- Quotations and accepted orders do not post revenue, cash, receivables, cost, or stock movements. Stock is rechecked when goods are released.
- There is no automatic stock reservation in this version. Show **Stock is not reserved** and the current availability on the order so an accepted quote is not mistaken for a guaranteed allocation.
- Take payment when goods are released, or charge a reviewed customer account through H2. Pre-release deposits and full prepayment require a separate customer-advance ledger and are deferred; do not record them as sales or manual cash-in workarounds.
- Record sales and stock issue at physical release/dispatch for these internal management records. A later **Received** confirmation does not post a second sale or stock movement. Formal ownership-transfer accounting is outside this flow.
- Quotations use immutable issued revisions. Changes to an accepted order require an explicit revision/re-acceptance of the unfulfilled remainder; already posted release records retain their snapshots.
- Allocate fixed discounts cumulatively across partial releases so the final release receives the exact rounding remainder. Preserve agreed selling prices while computing cost at each actual release.
- Do not allow release beyond the remaining quantity or available stock. Do not treat a returned item as automatically reopening the original order; a replacement needs an explicit new order or approved amendment.
- A failed delivery leaves the dispatch record intact. Record a linked return for goods physically brought back; never restore stock solely by changing a delivery status.
- Delivery fees, route planning, driver logins, automatic customer messages, public tracking links, digital signatures, and proof-of-delivery uploads are later enhancements. Basic recipient/date/reference recording is included.

### Acceptance evidence

- Quote 100 bags at PHP 250 each; accept once; release 40, then 60. The first release posts PHP 10,000 and 40 bags, the second PHP 15,000 and 60 bags. The quote and acceptance post nothing financial.
- Closing an order after 40 bags records 60 cancelled/unfulfilled bags with a reason, with no additional sale or stock deduction.
- For each line, ordered quantity equals released quantity plus cancelled quantity plus remaining quantity. Return quantities stay linked separately and do not erase release history.
- Duplicate or concurrent release requests cannot issue the same remaining units twice. Insufficient stock, insufficient payment, stale order revision, or exhausted credit rolls back all effects.
- A saved quotation remains readable after product/customer edits. Expired acceptance, amended price review, decimal units, rounding residuals, and archived products have explicit validation.
- Verify pickup, dispatch, received confirmation, failed-delivery return, partial cancellation, and admin read-only detail screens on phone and desktop.

## Architecture, permissions, and rollout

Use the existing Laravel/MySQL application and private Hostinger deployment. Retain integer money/quantity arithmetic, server-controlled posting dates, submission keys/fingerprints, transaction locking, tenant scopes, composite ownership constraints, and permanent audit events.

| Increment | Proposed persistence and services | Existing integration points |
| --- | --- | --- |
| H1 | Versioned template definitions; optional product specifications; catalog normalization and preview | `ProductCatalog`, product form/controller, `ProductUnit`, product registration tests |
| H2 | Customers, receivable entries, collections and allocations, customer snapshots on documents | `Operations`, `Reports`, checkout/returns, cash, customer screens, admin summaries and exports |
| H3 | Quote revisions/lines, sales orders/lines, releases/lines and receipt confirmations | H2 customers, existing sale posting, inventory, printing, store/admin document navigation |

Design exact migrations and route contracts with each increment. All additions must be compatible with retained business records; do not reinterpret old free-text customer names as verified account identities.

Proposed access design: H1 stays under `catalog`; H2 adds `credit`; H3 adds `orders`. Customer maintenance is a shared dependency of credit/orders. New credit depends on sales/cash and their existing dependencies. Quotes require catalog access; actual releases also require sales/inventory/cash, plus credit when underpaid. Administrators retain inspection-only access to merchant operations.

Extend the module dependency and resolution matrix before introducing new toggles. An account with debt must retain authorized collection/return resolution while new credit is restricted. Orders with remaining quantities must retain authorized cancellation/history access. Prevent full disable while unresolved obligations require that module; never bypass store suspension or grant administrative write access to resolve them.

For each increment:

1. Implement the complete owner workflow with validation recovery, useful empty states, pagination, and matching admin read-only views.
2. Add meaningful integration and concurrency checks for money, stock, retry safety, entitlement changes, and tenant isolation. Run existing regression checks and inspect rendered UI.
3. Reconcile existing records and verify additive migration/rollback behavior on local synthetic data. Back up production before an authorized release; do not use merchant data as disposable test fixtures.
4. Update the user guide, data model, reporting/access specifications, and release notes to describe what actually shipped.
5. Enable the completed workflow for pilot stores with reviewed real setup; do not migrate customers into credit automatically.

The original first-release requirements remain the contract for existing behavior. This extension supersedes their fully-paid-only assumption only when H2 is implemented, tested, and enabled. Internal records, online-only operation, one operational owner, and existing fiscal boundaries continue to apply.

## Pilot validation and decisions

Use 3–5 hardware owners to test their actual product types, customer terms, and pickup/delivery practices. Measure setup time and corrections against their current process; verify that they can explain the customer balance and remaining order quantity without assistance. Ask whether the demonstrated benefit supports renewing at the proposed monthly price. Do not equate successful demos with proven willingness to pay.

Approved defaults: enforce credit limits, warn on overdue debt, and defer deposits and reservations. Implementation uses a replacement quotation for revisions: the old unfulfilled remainder is explicitly closed, its released history stays intact, and the replacement needs issue/acceptance again. See the user guide and release notes for shipped behavior and verification.

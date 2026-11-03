# Hardware Store Reporting and Financial Rules

Implementation status: the October 31 operational pilot is described in [release notes](release-notes.md) and [the user guide](user-guide.md). This specification retains the broader target contract; tax/fiscal processing remains outside the current pilot. SMTP account and optional announcement email are implemented as described in [SMTP email](smtp-email.md).

Both portals use the definitions in this specification. Store reports select one authorized Store. Platform reports select an explicitly authorized set of stores. The same interval, currency, filters, and as-of time must produce the same result.

The first release reports fully paid sales and paid operating expenses. The operating profit estimate is a management measure based on recorded transactions, not a complete statutory income statement. Customer credit examples below explain a later increment and are not available in release one.

## Posting time and reporting periods

Persist UTC timestamps and convert user-selected calendar dates using the Store timezone. A date interval includes its starting local midnight and excludes the midnight after its final selected date. Do not use an inclusive `23:59:59` cutoff.

Sales, returns, receipts, losses, collections, cash movements, expenses, and reversals belong to the period of their server-controlled posting timestamp. Supplier document dates and expense receipt dates remain searchable reference fields. They do not backdate operating results or alter earlier cash reconciliations.

Return and reversal activity is recognized in its own posting period. A return this month against a sale last month reduces this month's net sales and cost of goods sold; it does not rewrite last month's saved result. A period with only returns may have negative net sales.

For combined reports, the user selects a reporting timezone, initially Asia/Manila, and the service derives one shared UTC interval. Each included store's comparison uses that same interval, rather than silently mixing local-day boundaries. Display the interval and timezone prominently.

## Metric contract

All sales values below exclude tax unless explicitly labeled otherwise. Refunds and return amounts are stored as positive amounts and subtracted in the formulas.

| Metric | Formula or definition | Source |
| --- | --- | --- |
| Gross sales | Posted sale line values before discounts, returns, and tax | Sale snapshots |
| Discounts | Original sale discounts minus original discounts allocated to returns posted in the period | Sale and return snapshots |
| Returns before discount | Original gross line value allocated to posted returns | Return snapshots |
| Net sales | Gross sales minus discounts minus returns before discount | Sale and return snapshots |
| Tax activity | Posted sale tax minus original tax allocated to posted returns | Tax snapshots |
| Customer charges after returns | Net sales plus tax activity | Sale and return snapshots |
| Collections | Positive confirmed customer payments received in the period | Collection payment records |
| Refunds paid | Positive confirmed customer refunds paid in the period | Refund payment records |
| Net customer payments | Collections minus refunds paid | Payment records |
| Cost of goods sold | Sale cost snapshots minus original cost allocated to posted returns | Sale and return cost snapshots |
| Gross profit | Net sales minus cost of goods sold | Shared metric service |
| Gross margin | Gross profit divided by net sales times 100, only when net sales is positive | Shared metric service |
| Paid operating expenses | Posted expense amounts minus expense reversals posted in the period | Expense records |
| Recorded inventory losses | Cost of damage and physical count shortages, including damaged returns | Loss movements |
| Estimated operating profit | Gross profit minus paid operating expenses minus recorded inventory losses | Shared metric service |
| Inventory adjustment gains | Value of physical count surplus movements; shown separately, excluded from operating profit estimate | Inventory movements |
| Inventory value | Sum of inventory value deltas through the selected as-of time | Inventory ledger |
| Stock on hand | Sum of quantity deltas through the selected as-of time, per product base unit | Inventory ledger |
| Low stock products | Active products with on-hand quantity at or below their configured reorder level | Inventory balances |

Net sales can also be computed as posted discounted sale values minus posted discounted return values. Implement this equivalent calculation as a reconciliation check to catch discount double subtraction.

Collections include cash and confirmed noncash customer payments. Opening float, owner cash additions, and inventory receipts are not collections. Tax collected is not sales revenue or profit. Customer payment amounts may include tax, so net customer payments are compared to tax-inclusive customer charges, not tax-exclusive net sales.

Operating expenses use the recorded paid amount. The estimate excludes unpaid liabilities, depreciation, finance costs, income tax, and accounting adjustments not represented by these records. Stock surplus gains are shown separately until an accounting policy determines their treatment. Label these boundaries next to the operating estimate help text.

No receivable balance is exposed as an active release-one feature. A migration or future credit module must supply a proper receivable ledger rather than derive all balances from a single mutable customer field.

## Inventory costing

Use moving weighted average cost as proposed default P-05. Let current base quantity be Q and current inventory value be V.

1. On receiving q units with acquisition value v, set new Q to Q + q and new V to V + v.
2. Derive the new average cost from new V divided by new Q.
3. On a sale of q units, record cost of goods sold using the average cost at that posting and remove that quantity and value.
4. When the last quantity is removed, remove the exact remaining inventory value so a zero balance has zero value.
5. On a sellable customer return, add the returned quantity and its original sale cost. Recalculate the current average from the updated balance.
6. On damage or count shortage, remove quantity at the current average and record inventory loss. A damaged customer return uses its original returned cost for the paired restock and damage movements.

Opening stock requires reviewed quantity and value. Positive count adjustments use known current average cost or an explicit documented cost when no valid basis exists. Products with no valid cost basis cannot silently report a complete profit figure.

Purchase orders do not affect stock or profit. Receipts increase stock and inventory value; they are not automatically operating expenses. The cost of merchandise is deducted from gross profit when sold. Supplier cash settlement is outside release one and cannot be inferred from a receipt.

Example: receive 100 pieces at 10 each, then 100 pieces at 14 each. Quantity becomes 200, value 2,400, average cost 12. Selling 10 pieces records cost of goods sold of 120 and leaves quantity 190 and value 2,280. Changing the product's default cost later does not change that sale's 120 cost.

## Discounts taxes and rounding

The proposed first release supports line-level fixed or percentage discounts, capped at the line value. Basket-level discounts and promotion engines are deferred. Snapshots preserve the computed line discount and applicable tax treatment.

Calculate with decimal arithmetic. Normalize entered quantities through the snapshotted factor to six decimal places; reject input that exceeds permitted precision. Round currency line amounts half up to the currency precision, then sum stored line amounts into document totals. Keep cost and inventory value effects at six decimal places and round their report aggregate only for display.

For partial returns, calculate cumulative original net amount, discount, tax, and cost attributable to total returned quantity, then subtract amounts already reversed. The final return receives the remaining rounding residual. This ensures all returns together exactly reverse the original line, without over-refunding or double-reversing cost.

Do not choose tax rates or fiscal rounding exceptions from this generic rule. O-02 must specify the pilot's applicable rules, and the tax acceptance fixtures must be updated before fiscal use. Every portal uses the same tax and amount engine.

## Cash reconciliation

Expected closing cash equals:

```text
Opening float
+ Cash applied to posted sales
+ Authorized manual cash in
+ Cash expense reversals
- Cash customer refunds
- Cash paid operating expenses
- Authorized manual cash out
= Expected closing cash

Counted closing cash - Expected closing cash = Cash variance
```

Cash applied to a sale is tendered cash minus change. The opening float is stored on the session and is not inserted again as a cash-in movement. Noncash payments, noncash expenses, and noncash refunds do not alter the drawer.

A manual cash out is not automatically an expense. Owner drawings reduce cash but do not reduce the operating profit estimate. A cash expense creates its own linked movement; users must not record an additional manual withdrawal for that same payment.

Manual withdrawals and cash refunds cannot make expected drawer cash negative. A session can span midnight; it retains one opening and closing count. Day reports use posting timestamps, while session reports include all movements in that session. Closing does not manufacture an expense or sale to eliminate a variance.

## Worked reconciliation examples

### Paid sale with an expense

Amounts are PHP and tax-exclusive in this example. Open with 500 cash. Sell goods for 1,000 with a recorded cost of 650, paid in cash. Record a 100 cash operating expense.

| Measure | Expected |
| --- | --- |
| Net sales | 1,000 |
| Collections | 1,000 |
| Cost of goods sold | 650 |
| Gross profit | 350 |
| Paid operating expenses | 100 |
| Estimated operating profit | 250 |
| Expected closing cash | 1,400 |

If counted cash is 1,390, variance is minus 10. Gross profit and operating profit remain 350 and 250; cash variance is shown separately.

### Split payment and change

A sale totals 1,000. The customer pays 600 by confirmed e-wallet and tenders 500 cash. Change is 100. Collections are 1,000, with 400 applied cash and 600 e-wallet. The cash drawer increases by 400.

### Partial return in a later period

Sell two units at 100 gross each with a total line discount of 20 and original cost of 60 per unit. The paid sale totals 180 with no tax. Return one sellable unit in a later period.

| Measure | Original sale period | Return period | Combined |
| --- | --- | --- | --- |
| Gross sales | 200 | 0 | 200 |
| Discounts | 20 | -10 | 10 |
| Returns before discount | 0 | 100 | 100 |
| Net sales | 180 | -90 | 90 |
| Cost of goods sold | 120 | -60 | 60 |
| Gross profit | 60 | -30 | 30 |

The refund is 90 and sellable stock increases by one unit with value 60. If the returned unit is damaged, the same return reverses cost of goods sold, followed by a 60 inventory loss; sellable stock does not increase.

### Future credit sale

After the credit increment is implemented, a 1,000 sale with 650 cost and a 400 initial payment would show sales 1,000, gross profit 350, collections 400, and receivables 600. Collecting the remaining 600 increases collections and reduces receivables without recording another sale. Release one rejects this underpaid sale.

## Report presentation and reconciliation

Show the currency, timezone, interval, included store set, generated time, and applied filters. Never add mixed currencies. Do not total unlike product quantity units such as meters and pieces into one unlabeled stock quantity.

Compare admin and store results with identical scope and as-of time. For a stable comparison, query both within the same database snapshot or use an agreed source posting watermark. Store lifecycle filters must state whether suspended and archived stores are included; history is included by default for platform totals.

Export includes the same metadata and filters as the screen report. Protect customer details and neutralize spreadsheet formulas in text cells. Large exports reauthorize scope at generation and download, and cannot silently exceed the requested store set.

Empty activity returns zero sales and collections, not a fabricated growth percentage. Margin is unavailable when net sales is zero or negative. A missing cost snapshot on activity included in the report produces an incomplete profit indicator rather than a confident zero cost result. An unsold product with zero stock and no cost basis does not by itself make the period's profit incomplete.


## Active credit/reporting extension — November 4, 2026

The credit extension supersedes the fully-paid-only assumption above when enabled. `Reports::summarize()` now derives collections/refunds from payments joined to their document kind and actual posting date. Sale and collection receipts contribute to collections; return refunds and collection reversals contribute to money paid back. Expenses, float, manual cash movements, and opening debt are excluded from customer collections.

Both portals and summary CSVs include **Receivables** and **Overdue receivables** as end-date balances. Sum signed ledger entries through the end-date cutoff; overdue means the original obligation's due date is earlier than that local as-of date. Customer statements show full sale charges, receipts, returns/refunds and corrections with opening/closing balances. Statement exports use one consistent database snapshot and explicit store scope during streaming.

Cash reconciliation additionally includes cash debt collections and subtracts cash collection reversals. Each creates a linked movement in the current session. A credit-only sale adds no drawer cash. A debt-only return pays no refund. Collections posted on another day change that day's payments and receivables, never its sales or cost of goods.

Quotes, acceptance, cancellation of unfulfilled quantities, and delivery receipt confirmations have no financial effect. Sales/cost/stock effects are posted at each actual release. Accepted order prices are retained while inventory cost uses the actual release-time weighted average. Issued quotations carry issuer/customer snapshots and are internal management records.

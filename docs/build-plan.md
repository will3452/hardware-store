# Hardware Store Build Plan and Verification

Build in dependency order and deliver complete business flows. Milestones below have exit evidence rather than invented calendar dates. Estimate dates after resolving the stack, team capacity, and pilot requirements.

## Delivery milestones

| Milestone | Scope and dependencies | Exit evidence |
| --- | --- | --- |
| M0 Product and engineering setup | Confirm O-01 through O-03 where they affect the scaffold; select stack; define API conventions and migration workflow | Recorded stack decision, initial application skeleton, automated local checks, two-store test fixtures |
| M1 Platform foundation | Identity, store provisioning, memberships, lifecycle, module access, announcements, audit; depends on M0 | Admin registers a store; owner activates; cross-store access fails; stale permissions cannot post |
| M2 Catalog and inventory | Products, units, opening stock, ledger, balances, counts, private attachments; depends on M1 | Box-to-piece and fractional stock flows reconcile; ledger and balances match under concurrency |
| M3 Purchasing | Suppliers, orders, partial receipts, permitted full receipt reversal; depends on M2 | Partial delivery affects only received quantity; duplicate receipt submission has one effect |
| M4 Sales and cash | Payment method configuration, cash sessions, fully paid checkout, returns, cash movements; depends on M2 and approved tax amount rules for fiscal operation | Paid sale and refund are atomic and idempotent; no oversell; session closes correctly |
| M5 Expenses and reports | Paid expenses, reversals, shared metrics, dashboards, platform comparison, CSV; depends on M3 and M4 | Worked examples pass; both portals reconcile; disabled store modules do not erase history |
| M6 Pilot readiness | Security review, performance check, backup restore, operational training, production policy gates; depends on M1 through M5 | Completed release checklist and pilot sign-off with no critical open defects |

For development before O-02 is finalized, use clearly labeled nonfiscal test documents and synthetic tax fixtures. Do not present those as compliant documents or begin real fiscal operation without the policy gate.

## Work items by milestone

### M0 Product and engineering setup

- Record framework, runtime, database, authentication, and deployment decisions with operating cost implications.
- Create the application structure, migration tooling, formatter, linter, and baseline CI.
- Define the endpoint contract for each upcoming milestone, including errors and idempotency behavior.
- Build seeded Store A and Store B with distinct owners and deliberately overlapping product codes.
- Decide tax and fiscal test scenarios, document naming, currency precision, and supported devices before dependent UI is finalized.

### M1 Platform foundation

- Implement provider identity integration, owner invitations, platform MFA, recovery, and session invalidation.
- Build store registration, pending activation, lifecycle changes, and controlled owner replacement.
- Implement verified scope, database isolation, composite ownership constraints, and scoped file authorization primitives.
- Build module modes, dependency validation, invalidation, and historical resolution permissions.
- Build announcements with audience and timing checks.
- Record audit and notification outbox events in source transactions.

### M2 Catalog and inventory

- Build categories, products, archived product behavior, unit versions, and base quantity normalization.
- Implement opening quantity and cost review, ledger posting, locked balances, and weighted average valuation.
- Build stock view, low-stock indicators, movement detail, counts, and damage adjustments.
- Implement private attachment upload validation and download authorization.
- Enforce no negative inventory and known cost for inbound value.

### M3 Purchasing

- Build suppliers and purchase order draft, placement, amendment, cancellation, and short closure.
- Implement partial receiving with conversion and acquisition cost snapshots.
- Implement full receipt reversal only when every affected product passes the latest-movement rule.
- Provide receiving history and a clear explanation when unsupported downstream correction is requested.

### M4 Sales and cash

- Build payment methods, cash opening, manual in and out, and session locking.
- Build checkout with SKU search or scanning, permitted quantities, line discounts, split payments, and change.
- Implement one atomic sale posting and submission-outcome recovery.
- Build original-sale returns with cumulative return limits, original amount and cost allocation, and sellable or damaged disposition.
- Implement confirmed refunds and nonnegative expected cash rules.
- Build closing reconciliation, variance notes, and immutable session history.
- Build the provisional print layout and apply the final document specification once O-02 is resolved.

### M5 Expenses and reports

- Build paid operating categories, expense records, attachment association, and full reversals.
- Implement the metric contract once and use it from both portals.
- Build store KPI cards, product and stock reports, cash reports, platform scope selection, and same-scope comparisons.
- Implement scoped CSV export with formula-injection protection and request limits.
- Include incompleteness, timezone, currency, interval, and freshness metadata.

### M6 Pilot readiness

- Run the critical scenarios below, then verify the targeted latency and sizing profile.
- Rehearse database and private file recovery, including isolated extraction of one store.
- Review permissions, secret management, sensitive logging, rate limits, and platform reporting access.
- Prepare operator instructions for onboarding, failed notifications, uncertain sales, suspension, backups, and reconciliation discrepancies.
- Train pilot owners on opening stock, payment confirmation, return disposition, expense classification, and cash close.

## Requirement coverage

| Requirement | Main milestone | Verification scenarios |
| --- | --- | --- |
| FR-01 Provisioning | M1 | T01, T02, T03 |
| FR-02 Authentication | M1 | T03, T04, T05 |
| FR-03 Lifecycle | M1 | T05, T06 |
| FR-04 Module access | M1 and M4 | T07, T08 |
| FR-05 Announcements | M1 | T09 |
| FR-06 Products and units | M2 | T10, T11 |
| FR-07 Inventory | M2 | T12, T13, T14 |
| FR-08 Purchasing | M3 | T15, T16, T17 |
| FR-09 Sales | M4 | T18, T19, T20, T21 |
| FR-10 Returns | M4 | T22, T23, T24 |
| FR-11 Cash | M4 | T25, T26, T27 |
| FR-12 Expenses | M5 | T28, T29 |
| FR-13 Store reports | M5 | T30, T31, T32 |
| FR-14 Central reports | M5 | T30, T33, T34 |
| FR-15 Audit | M1 through M5 | T35 |
| FR-16 Protection and recovery | M1 and M6 | T36, T37 |
| FR-17 Future users | M1 | T06 |
| NFR-01 Integrity | M2 through M5 | T13, T16, T19, T20, T23, T26 |
| NFR-02 and NFR-03 Performance | M6 | T38 |
| NFR-04 Usability | M6 | T39 |
| NFR-05 Security | M1 and M6 | T04, T05, T36, T40 |
| NFR-06 Availability and recovery | M6 | T37, operational monitoring review |

## Critical verification scenarios

Use integration tests for persistence, permissions, and concurrency; focused calculation tests for amounts and valuation; and end-to-end tests for complete user flows. Avoid tests that only repeat implementation details.

| ID | Scenario | Required outcome |
| --- | --- | --- |
| T01 | Submit store registration twice with one command key | One store, initial membership, valid invitation, and source operation |
| T02 | Fail after partial onboarding writes or fail email delivery | Database work rolls back on transaction failure; email failure leaves one recoverable pending store |
| T03 | Accept expired, revoked, or consumed invitation; invite an existing identity | Invalid invitations fail; existing identity must authenticate and accept explicitly |
| T04 | Probe recovery, brute-force login, use platform account without MFA | No account enumeration; configured limits and MFA enforced |
| T05 | Suspend a store or revoke membership while a page or posting is active | Next request denied; in-flight posting rechecks current authorization |
| T06 | Replace the owner after successor acceptance | One active owner; former scope revoked; original actor history retained |
| T07 | Call disabled modules directly or use a stale enabled page | New operations denied at the server |
| T08 | Restrict sales with historical returns and then disable required dependencies | Valid linked resolution still works; inconsistent configuration rejected |
| T09 | View scheduled, expired, withdrawn, all-store, and selected-store announcements | Only current authorized audience sees content; new stores join only all-store audience |
| T10 | Receive two boxes of 100 and sell three pieces | On-hand quantity 197; snapshots and value conversion correct |
| T11 | Sell fractional wire, change conversion version, archive a product | Valid fractional quantity supported; old documents unchanged and readable |
| T12 | Reconcile opening, receipt, sale, return, count, and damage movements | Movement sums equal balances; every effect has one source |
| T13 | Two concurrent sales consume the final stock | At most the available quantity posts; no negative balance |
| T14 | Count stock while another movement occurs; attempt positive stock with missing cost | Stale count blocked or reconciled explicitly; missing cost rejected |
| T15 | Order 100, receive 60, then 40; try over-receiving | Stock increases by actual receipt quantities only; excess rejected |
| T16 | Retry a receipt after response loss; fail midway through posting | One complete receipt or no receipt effects |
| T17 | Reverse the latest receipt, then try one with downstream movements | Eligible full reversal restores prior balances; unsupported reversal denied |
| T18 | Sell for 1,000 using 600 e-wallet plus 500 cash tendered | Cash applied 400, change 100, total collections 1,000 |
| T19 | Timeout after sale commit and retry with the same key | Original sale recovered; no duplicate stock, payment, cash, or audit posting |
| T20 | Inject a failure between sale, stock, payment, and cash writes | Every effect rolls back together |
| T21 | Confirm a stale price or submit an underpaid sale | Owner reviews repricing; underpayment rejected in release one |
| T22 | Partially return a discounted line several times, including rounding residuals | Total reversed quantity, money, tax, and cost never exceed the original |
| T23 | Submit simultaneous returns for the same remaining quantity | At most the refundable remainder posts |
| T24 | Return a sellable item and a damaged item | Original cost reversed; sellable stock restored only where appropriate; damage loss recorded |
| T25 | Open two sessions, post noncash sale, request excessive cash withdrawal | One session; noncash leaves drawer unchanged; negative expected cash prevented |
| T26 | Race a sale posting with session closure | Sale belongs to the open session or fails safely; no late posting after close |
| T27 | Open at 500, collect 1,000 cash, spend 100, count 1,390 | Expected cash 1,400; variance minus 10 with note |
| T28 | Record one cash expense and one noncash expense; attempt stock purchase category | One cash movement for cash expense; no drawer effect for noncash; invalid category blocked |
| T29 | Reverse yesterday's cash expense using today's session | Today's cash increases; yesterday's stored closing count remains unchanged |
| T30 | Compare store and admin reports at matching scope and as-of time | Matching totals using the same definitions |
| T31 | Run report examples, missing cost, no activity, and negative net sales | Worked totals match; incomplete cost flagged; margin unavailable at nonpositive sales |
| T32 | Export text beginning with spreadsheet formula characters | Formula-safe text; same authorized rows and filters as screen |
| T33 | Add mixed-currency stores and stores in different timezones | No mixed-currency sum; one explicit comparison interval and timezone |
| T34 | Suspend or archive a store after posting transactions | Platform history remains included unless explicitly filtered out |
| T35 | Inspect provisioning, entitlement, posting, adjustment, reversal, and export events | Required actor and scope present; secrets absent; no user edit or deletion |
| T36 | Access Store B using Store A through IDs, lists, files, exports, jobs, cache, and pooled database connections | Every unauthorized path denied without leaking Store B data |
| T37 | Restore backup and selectively recover one store in isolation | Recovery meets accepted targets and preserves links without exposing another store |
| T38 | Run PRD load profile with 12-month history | Measure p95 server latency and resource use against accepted targets |
| T39 | Complete primary flow at 360 px and desktop using keyboard | No blocked controls, hidden amount labels, or lost input on validation |
| T40 | Use report-only admin privileges to post sales or manage stores; probe protected mutations | No unauthorized operational write; CSRF and session protection enforced |

The full database isolation inventory must cover every store-owned table, including any table added after this baseline. Do not treat one successful two-store example as coverage for all entity types.

## Pilot acceptance script

1. Register Store A and Store B; activate their separate owner accounts.
2. Publish one all-store announcement and one announcement targeted only to Store A.
3. In Store A, create a piece-based product and a fractional meter-based product.
4. Receive stock through a partially received purchase order and verify normalized quantities and costs.
5. Open cash, post a fully paid split-payment sale, and recover the same outcome after a simulated lost response.
6. Process a partial sellable return and a separate damaged return using original snapshots.
7. Record cash and noncash operating expenses, then close with an explained variance.
8. Match Store A's sales, collections, cost, losses, and operating estimate with the admin report for the same interval.
9. Confirm Store B cannot see or affect Store A's products, documents, files, announcements, or report rows.
10. Restrict a used module, verify that new work stops, and complete only an allowed historical resolution.

## Production release checklist

- [ ] PRD first-release scope and proposed business rules accepted for the pilot.
- [ ] O-01 through O-07 and O-09 resolved where required for production; O-10 resolved before cash sign-off.
- [ ] Fiscal document and tax scenarios reviewed before real fiscal operation.
- [ ] All critical functional, reconciliation, isolation, and concurrency scenarios pass.
- [ ] Admin and store reports reconcile against the same source records.
- [ ] Opening stock and product costs reviewed by each pilot owner.
- [ ] Platform MFA, recovery, rate limits, scoped files, protected sessions, and secret handling verified.
- [ ] Backup restoration and isolated store recovery rehearsed within accepted recovery targets.
- [ ] Monitoring, alert ownership, notification retries, and incident recovery instructions configured.
- [ ] Representative performance targets measured; any accepted limits documented.
- [ ] Pilot owner training completed and supported devices checked.
- [ ] No unresolved critical security, duplicate posting, stock, refund, or cash issue remains.

## Next increment

Prioritize after pilot feedback. Customer credit requires customer identity, credit limits, receivable postings, partial payment allocation, aging, overpayment and refund rules, and collection reports. Quotations and delivery require reservations, deposits, fulfillment states, and proof of delivery decisions. Additional users require an explicit operational permission matrix. Offline operation and multiple locations require separate architecture work.

Do not promote these areas to first-release scope merely because an entity or menu name is convenient to add.

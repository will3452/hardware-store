# Hardware Store Management System Product Requirements

Implementation status: the October 31 operational pilot is described in [release notes](release-notes.md) and [the user guide](user-guide.md). This specification retains the broader target contract; tax/fiscal processing remains outside the current pilot. SMTP account and optional announcement email are implemented as described in [SMTP email](smtp-email.md).

Version 0.2. Planning baseline updated October 31, 2026. Product decision owner: the system owner. Intended readers: product, design, engineering, and QA.

November 3 extension: hardware product templates, customer credit/collections, and quotations with partial pickup/delivery are the selected next priorities. The approved extension requirements and acceptance criteria are in the [hardware growth plan](hardware-growth-plan.md). H1–H3 were deployed on November 4; consult [release notes](release-notes.md) for deployment status. The original first-release scope below is retained as the legacy baseline. Customer credit, independent collections, quotations, and releases extend it only for stores with the corresponding modules enabled.

Independent hardware stores need to record their daily operations and understand their performance. The platform operator needs to onboard those stores, control their available modules, communicate announcements, and see centralized reports. The first release will provide two portals over a common platform, with one initial owner account per store and online operation.

The application will use Laravel and target Hostinger Web or Cloud hosting through hPanel. The implementation baseline is Laravel 13, MySQL with InnoDB, Laravel session authentication, and short cron-driven background processing. The proposed interface uses the official Livewire starter kit. These hosting constraints do not change the required store isolation, atomic posting, or reconciliation behavior.

Confirmed requirements and proposed implementation defaults are distinguished in the [decision register](decisions-and-research.md). Requirement IDs below define the proposed release contract. Open policy decisions have explicit gates rather than hidden assumptions.

## Goals

1. Let the Platform Admin register a store and provision its initial owner account in one controlled workflow.
2. Give a store a consistent record of products, inventory, purchasing, sales, returns, cash, and paid operating expenses.
3. Show store performance using the same calculations in the Store Portal and Admin Portal.
4. Enforce separation between independent businesses across every access path.
5. Allow per-store module configuration and future additional staff accounts without restructuring the account model.

## Users and terminology

| Term | Meaning |
| --- | --- |
| Platform Admin | Person authorized to manage the platform and review cross-store reports |
| Store | An independent business workspace and data boundary; one physical location in the first release |
| Store Owner | Initial operational user of a store |
| Staff | Future users with limited operational permissions |
| Customer | A buyer from a store; a customer record is not a login account |
| Supplier | A vendor from which a store buys merchandise |
| Module | A business capability that can be enabled for a store |
| Shared Platform | Common backend services, data storage, authorization, and reporting |

A Store and a User are separate records. A membership connects them. One owner is provisioned at onboarding; the first release exposes no general staff invitation interface. Future branches will be locations within a business and require a later scope decision.

## Scope

| First release | Later increments | Outside the present build |
| --- | --- | --- |
| Admin onboarding and lifecycle | Customer credit and collections | Payroll and HR |
| Authentication and store isolation | Quotations and sales orders | Marketplace or ecommerce storefront |
| Module access and announcements | Delivery and special order management | Equipment rental and repair services |
| Products and units of measure | Multiple operational staff roles | Full general ledger and statutory accounts |
| Stock ledger and physical counts | Multiple locations within a business | Automatic demand forecasting |
| Suppliers and purchase receiving | Offline selling with reconciliation | Subscription billing without a separate brief |
| Fully paid POS sales and returns | Accounting and payment integrations | Automatic supplier ordering |
| Cash sessions and paid expenses | Supplier payables and bill settlement | Claims of fiscal certification |
| Store and centralized reporting | Product import beyond initial manual setup | Third-party access to merchant data |

Recording a card, bank, or e-wallet payment in the first release records a payment the store confirms it received. It does not charge a customer or verify provider settlement. Procurement records receiving and inventory cost; supplier debt and supplier payments are not implemented in the first release.

## Functional requirements

### FR-01 Store registration and owner provisioning

The Platform Admin creates a store with a unique store code, business display name, physical address, owner name, owner login email, contact details, currency, timezone, and initial module selection. The system creates the store, initial owner identity or authorized identity association, membership, and activation invitation as one recoverable operation.

Acceptance criteria:

- Duplicate submission creates one store and one initial membership. Failure leaves no usable partial store.
- The owner sets their own password through an expiring, single-use activation process; the admin never sees their password.
- If the owner email already belongs to an identity, the system requires the explicit existing-identity flow in [access control](access-control.md), with authentication before activation.

### FR-02 Authentication and account recovery

Users can activate an account, sign in, sign out, and request recovery. Platform accounts require multifactor authentication. Sessions bind to server-verified permissions and store membership.

Acceptance criteria:

- An expired or used invitation cannot activate an account; resending revokes the earlier invitation.
- Recovery responses do not reveal whether an email is registered. Recovery and login are rate limited.
- Store suspension, disabled identities, and revoked membership prevent access on the next request and invalidate applicable sessions.
- Public self-registration is disabled. Laravel Fortify authentication, password recovery, and TOTP features are adapted to invitation-based owner onboarding; platform access requires confirmed TOTP enrollment.

### FR-03 Store lifecycle and profile

Stores progress through pending activation, active, suspended, and archived states. The Platform Admin manages lifecycle and identity fields. Owners can update operational contact details and physical address; store code, currency after posting, and module entitlements remain restricted.

Acceptance criteria:

- Suspended and archived stores cannot run store operations. Their historical records remain available to explicitly authorized platform reporting.
- Suspension, reactivation, archive, owner replacement, and profile changes are audited. Owners cannot delete their business records.

### FR-04 Module access

The Platform Admin configures modules per store. Access is enforced in the backend as well as in navigation. Module dependencies prevent an invalid configuration.

Acceptance criteria:

- A direct API request cannot use a module unavailable to that store.
- A module with historical records can be restricted for new work while allowing the defined resolution actions. Disabling does not delete history.
- A stale page rechecks module access when submitting a mutation. Detailed dependencies are defined in [access control](access-control.md).

### FR-05 Announcements

The Platform Admin creates draft announcements, selects all stores or named recipients, schedules publication and optional expiration, and withdraws announcements. Store owners see only announcements currently addressed to their store.

Acceptance criteria:

- Draft, future, expired, withdrawn, and other-store announcements are inaccessible through store endpoints.
- Targeted audiences are explicit. Newly registered stores receive active all-store announcements but do not join a fixed selected-store audience automatically.

### FR-06 Products and units

Owners manage products, categories, SKU codes, optional barcodes, brands, descriptive variants, prices, and units. Each sellable variant has its own SKU. Quantities are normalized to a base inventory unit through product-specific conversion factors.

Acceptance criteria:

- Receiving two boxes of 100 pieces increases inventory by 200 pieces. Selling three pieces leaves 197.
- Fractional quantities are allowed only for products whose base unit permits them.
- Posted documents retain name, SKU, conversion, price, discount, cost, and tax snapshots after a product changes or is archived.
- Conversion versions already used by transactions cannot be silently rewritten. Product records referenced by history cannot be hard deleted.

### FR-07 Inventory ledger and counts

The platform records opening stock, receipts, sales, returns, supplier receipt reversals, physical count differences, and damage adjustments in an immutable inventory movement ledger. A balance is maintained per store and product.

Acceptance criteria:

- Every quantity change has a source document, actor, timestamp, reason where applicable, and signed quantity in base units.
- Concurrent sales cannot reduce available quantity below zero. No negative-stock override exists in the first release.
- A stock count detects movements since its count snapshot and requires a recount or explicit reconciled resolution.
- Opening stock and positive adjustments require a cost. A missing cost is not treated as a valid zero cost.

### FR-08 Suppliers and purchasing

Owners manage suppliers, draft purchase orders, place orders, and receive merchandise in one or more receipts. Purchase quantities and costs can use configured purchase units.

Acceptance criteria:

- Ordering 100 units and receiving 60 adds only 60 units to stock; 40 remain open.
- Cumulative receipts cannot exceed the order without an audited order amendment. Changing a draft PO never changes on-hand inventory.
- Receipt posting updates stock and inventory value once. A correction uses a linked reversal subject to quantity availability and the valuation rules.
- Receipt cost represents the merchandise acquisition cost in the agreed tax treatment. Freight allocation and supplier settlement are outside the first release.

### FR-09 Paid sales and payment recording

Owners search or scan products, enter quantities, apply permitted discounts, confirm payment methods, and post a fully paid sale. One sale may have multiple payment methods. A store must have an open cash session before posting sales or refunds.

Acceptance criteria:

- Posting atomically creates the sale, payment records, inventory movements, cost snapshots, and any cash movements.
- Noncash payments require store confirmation and a reference when the configured method requires it. Tendered cash and change produce the correct applied cash amount.
- Applied payments equal the full tax-inclusive sale total; underpayment and customer credit are rejected in the first release.
- Retrying the same submission returns the original outcome. A lost response does not produce a second sale.
- Network failure never displays a completed sale without server confirmation.

### FR-10 Returns and refunds

Owners create full or partial returns against an original posted sale. Each returned item is identified as sellable or damaged. The refund is recorded with its payment method and any required reference.

Acceptance criteria:

- Cumulative returned quantity cannot exceed the original sold quantity, including concurrent requests.
- Original line pricing, allocated discounts, tax, and cost determine the reversal; current product price does not.
- Sellable items return to on-hand inventory. Damaged items are recorded as returned and written off with a linked loss movement.
- Sales, payments, stock, cost, and cash effects commit together. An unavailable noncash refund is not falsely marked completed.
- A cash refund cannot exceed recorded expected cash in the drawer. The owner may record a legitimate cash addition or use another confirmed refund method.

### FR-11 Cash sessions

Owners open a cash session with counted float, record cash in and cash out with reasons, and close the session with an actual count. There is one open session per store in the first release.

Acceptance criteria:

- Expected closing cash includes the opening float and applicable posted cash movements, without counting the opening twice.
- Closing stores expected cash, counted cash, variance, actor, and notes. A variance requires an explanation.
- Closed sessions cannot accept new transactions. Pending submissions are resolved before close, and racing close and sale requests serialize safely.
- Positive manual cash out cannot make expected cash negative.

### FR-12 Paid operating expenses

Owners record paid operating expenses by category, date, amount, payment method, reference, and optional attachment. Cash expenses create one cash-out movement in the open session. Noncash expenses do not change drawer cash.

Acceptance criteria:

- Inventory purchases, owner drawings, and cash float are not classified as operating expenses.
- Expense corrections use linked reversals and replacements. Reversing a cash expense credits a current open session; it does not rewrite a closed count.
- Only paid expenses are supported. The report labels the operating profit estimate according to this limitation.

### FR-13 Store dashboards and reports

Owners see their own sales, collections, gross profit, paid operating expenses, recorded inventory losses, operating profit estimate, product performance, inventory, and low-stock indicators. They can filter dates and export scoped CSV reports. The operating estimate deducts paid operating expenses and recorded inventory losses from gross profit; stock count gains are shown separately.

Acceptance criteria:

- Definitions and sample calculations match [reporting](reporting.md).
- Reports show currency, timezone, reporting interval, and generated or updated time. Missing cost data is shown as incomplete.
- CSV output applies the same store scope as screen reports and neutralizes spreadsheet formula injection.

### FR-14 Centralized platform reporting

The Platform Admin can view per-store and combined performance for authorized stores using the same metric service as the Store Portal.

Acceptance criteria:

- Matching store, time interval, filters, and as-of time produce matching totals in both portals.
- Combined reports show included stores and retain history for suspended or archived stores unless explicitly filtered out.
- Amounts in different currencies are never added without an exchange-rate policy. Platform totals are labeled merchant activity, not platform revenue.
- Platform reporting does not grant operational mutation rights in a store.

### FR-15 Audit history

The platform records security events, store lifecycle changes, module changes, financial postings, inventory adjustments, reversals, and relevant exports or access to cross-store reports.

Acceptance criteria:

- Events identify actor, store scope where relevant, action, entity, request, timestamp, and sanitized change details.
- Ordinary users cannot alter or delete audit events. Credentials, invitation secrets, full payment card details, and sensitive attachment contents are excluded.

### FR-16 Data protection and recovery

All store access paths enforce the store boundary, including documents, downloads, caches, background jobs, exports, and metrics. Backup restoration is rehearsed before production use.

Acceptance criteria:

- Store A cannot list, retrieve, modify, or infer Store B data by changing an identifier or URL.
- A restore drill proves recovery of transaction, attachment, and audit relationships and verifies store isolation.
- Retention and deletion policy is resolved before production, as recorded in the decision register.

### FR-17 Future users without account redesign

Identity, store membership, and permissions are separate. The first release provisions one owner and supports a controlled owner replacement process.

Acceptance criteria:

- Owner replacement invalidates the former membership and access without changing historical actor IDs.
- The schema does not use a single user foreign key on Store as its only authorization relationship.
- Future role names do not grant permissions until that feature is implemented and enabled.

## Quality requirements

These are proposed engineering targets for pilot planning, not measured capabilities.

| ID | Requirement | Verification |
| --- | --- | --- |
| NFR-01 | Committed business commands are atomic and safe to retry | Fault injection, concurrency, and idempotency scenarios |
| NFR-02 | Ordinary authenticated reads have p95 server latency at most 1 second; sale posting at most 2 seconds; default 31-day reports at most 5 seconds | Representative pilot load test |
| NFR-03 | Pilot sizing: 10 stores, 5,000 products per store, 1,000 sales per store per day, 100 lines per sale limit, 20 concurrent active sessions platform-wide | Seeded dataset and documented load profile |
| NFR-04 | Operational UI works at 360 px width and on desktop; checkout supports keyboard entry and visible validation | Manual device and keyboard checks |
| NFR-05 | TLS, protected sessions, managed secrets, least privilege, scoped file access, and platform MFA | Security integration checks |
| NFR-06 | Proposed production availability target 99.5 percent monthly, recovery point at most 24 hours, recovery time at most 8 hours | Hosting design review and restore drill |
| NFR-07 | Run within verified Hostinger hPanel capabilities using MySQL and bounded scheduled work; serve only public assets and the Laravel entry point | Deployment preflight, migration test, cron smoke test, and private-path exposure checks |

The performance dataset uses a 12-month transaction history for the default report test. Hosting and recovery targets must be accepted with cost implications before provisioning production infrastructure.

## Product success and release criteria

The pilot succeeds when an owner can receive merchandise, sell it, process a return, record an expense, close the cash session, and reconcile the resulting report with the admin view. Onboarding should be completable without manual database changes. Pilot timing and satisfaction targets will be set after identifying the pilot stores.

Release requires all first-release functional acceptance criteria, the critical checks in the [build plan](build-plan.md), a successful restore drill, and resolution of production policy gates. No unresolved critical isolation, duplication, inventory, refund, or cash reconciliation issue may remain.

## Dependencies and risks

| Risk or dependency | Required response |
| --- | --- |
| Incomplete product costs | Require valid cost on inbound movements; surface imported or legacy gaps |
| Internet interruption | Show unconfirmed state; resolve by submission ID before resubmission |
| Browser or concurrent request duplication | Use idempotency keys, database constraints, and transaction locks |
| Incorrect opening stock | Require owner-reviewed opening quantities and values before selling |
| Confusing sales with cash or profit | Use separate metric names and shared definitions |
| Cross-store data leakage | Enforce scoped access in application and database boundaries |
| Fiscal documents and tax setup | Resolve jurisdiction and review requirements before real fiscal operation |
| Optional module retirement with open work | Use restricted resolution access and dependency validation |

## Related specifications

Detailed entity constraints belong in the [data model](data-model.md). Screen and state behavior belongs in [workflows](workflows.md). Authentication and entitlement enforcement belongs in [access control](access-control.md). The [architecture](architecture.md) defines the Laravel implementation baseline; remaining service and hosting details are recorded in the decision register.

Laravel and the Hostinger hPanel deployment family are user-confirmed choices. The [Hostinger deployment guide](hostinger-deployment.md) governs environment verification, public directory placement, assets, queues, and release operations. Actual account capacity must be measured against the quality targets; the plan name alone does not establish production readiness.


## Administrator inspection increment

The November 3 release adds **View store** to the platform store list and management screen. Authorized administrators inspect the store workspace with a persistent read-only banner, unchanged admin identity, per-request audit events, store-scoped records, and server-side write protection. This includes historical inspection of suspended/archived stores. Export remains a separate permission. See [access control](access-control.md) for the implemented boundary.

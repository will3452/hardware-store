# Hardware templates, customer credit, and orders — November 4, 2026

Deployment status: **deployed and verified** on November 4, 2026 (Asia/Manila), application commit `acdd929`. The owner approved these flows with an explicit requirement to preserve existing users and update affected workflows.

- Guided wire, pipe/fitting, fastener, steel/lumber and paint registration with structured specifications, generated names/SKUs, store-local suggestions, existing/archived product lookup, duplicate checks and atomic pack conversion. General products and existing custom units remain supported.
- Customer accounts with reviewed credit limits/terms, explicit partially/unpaid checkout, opening receivables, payment allocations, statements/CSV and eligible reversals. Returns reduce original unpaid debt before refunding money actually received.
- Quotation drafts, issue/acceptance, immutable issuer/customer/product/price snapshots, linked revisions of unfulfilled remainders, partial pickup/dispatch, cancellation, and separate receipt confirmation. Each physical release posts one sale/stock/payment/receivable transaction. Full-return dispatches cannot be marked received later.
- Updated checkout, returns, cash movements, shared store/admin reports, platform totals, document details, exports, module dependencies, onboarding wording and administrator read-only views. Collections now use actual payment records; debt collection cannot create new sales. Receivables/overdue balances use as-of dates.
- Three additive migrations retain legacy accounts, memberships, products, unit conversions, stock, payments, documents and retry fingerprints. No synthetic data, customer accounts or debts are inferred from historical records. Customers/Credit/Orders start disabled for existing stores; admin enables them explicitly when a store is ready.
- Validation: **144 tests / 1,232 assertions**, Pint and PHPStan pass. Includes separate-process credit-limit/collection/release races, legacy-schema upgrade with unchanged protected values and report totals, original posting retries, cross-store/admin isolation, actual roll-to-meter receiving/sales, and exact partial-release discount rounding.
- Browser walkthrough with a local synthetic store: guided wire/pack save; PHP 5,000 sale with PHP 2,000 received and subsequent PHP 1,000 allocation; quote/accept/40-of-100-bag dispatch with PHP 10,000 payment and 60 bags remaining. Desktop and 390px mobile layout checked, no page overflow or browser errors; the existing-product check finds the previously created item. No production records were used for UI tests.
- Boundaries: online-only, one operational owner, internal management records; no deposits/prepayment, stock reservations, automatic customer messages, interest/penalties, provider charging or fiscal certification. Existing operational backup/recovery follow-ups remain separate.

- Production backup `20261103-162712-13ac20` was created and checksum-verified on the server before the three additive migrations. The snapshot ID uses UTC. Existing values in 19 protected tables, prior report totals, and the complete `.env` matched their pre-migration baseline exactly. No production database or credentials were downloaded. New customer, receivable, and order tables remained empty after verification; all existing stores retained disabled new module flags.
- On-server runtime checks passed for guided products/conversions, credit sale/retry, collection, debt-first return/refund, accepted quotation, partial dispatch/retry, receipt confirmation, stock/report reconciliation, and 11 rendered owner/admin screens. Synthetic rows were contained in one transaction and rolled back; no email was sent.
- Live HTTPS checks passed: health/login 200, unauthenticated admin redirect, environment 403 and private source 404. Published CSS/JavaScript matched the local build byte-for-byte; the live login references the new manifest and renders without browser console errors. Server-only `bun.lock` is retained.
- Deployment recovery: the first attempt stopped before migrations because the host had a stale cached development provider. Prior code was restored, generated package caches rebuilt, and login/health verified before retrying. Public compiled asset permissions were normalized to directories 755/files 644 after upload. These runtime repairs did not change application dependencies, existing records, or environment settings.

See [the user guide](user-guide.md#hardware-workflows--november-4-extension) and [approved growth plan](hardware-growth-plan.md).

# Store onboarding checklist — November 3, 2026

- Replaced the static Overview setup links with a collapsible checklist, progress count/bar, automatic completed states, and a next-step action. With core modules enabled it covers contact details, accepted payments, first product, starting stock, first cash drawer, and first real sale.
- Progress uses existing store-scoped operational records and profile/payment audit events. Only enabled features count; opening stock or purchase receiving satisfies the stock milestone. Archived products, closed cash sessions, and sold-out stock retain historical milestone credit.
- New stores see expanded guidance. Completed stores and stores with a previous sale see a compact summary. Other store screens link back to the checklist; contact and payment links target their exact form sections. No manual tick-off, forced sequence, sample sale, account reset, or new schema is needed.
- Admin View store displays the same progress with read-only links and no owner mutation controls.
- Validation: **125 tests / 963 assertions**, Pint, PHPStan, Blade compilation, and frontend build. New checks cover the full owner workflow, rejected input, existing receiving, tenant isolation, module changes, historical milestones, and admin permissions. Desktop/mobile previews cover new, partial, completed, and dark appearance states.

# Easier record browsing — November 3, 2026

- Record lists default to 10 rows, with 25/50-row choices, counts, previous/next controls at both ends, and direct page jumping. Query filters survive navigation; links return to the relevant section. Desktop tables have a bounded scroll area with sticky headings; mobile keeps native horizontal table scrolling. Printing removes the height limit for the current page.
- Added pagination for the store directory, suppliers, and current cash movements. Added directory, supplier, and purchase-order searches. Independent lists retain their own page/filter parameters. Purchase-order supplier choices still include every active supplier.
- Product detail/edit dialogs replace the repeated cards below the catalog. Closing or pressing Escape restores focus to the triggering row; failed edits reopen with errors and retained input. Read-only admin views continue to omit write forms.
- Shortcuts reach sales/expense history, inventory movements, purchasing sections, cash-session history, and detailed reports without scrolling through entry forms.
- Report totals, cash reconciliation, and CSV exports cover the full selected scope across pages. The directory search changes only displayed stores; platform reporting retains its explicit store/date scope.
- Validation: **120 tests / 899 assertions**, Pint, PHPStan, Blade compilation, and frontend build. New regression checks cover page boundaries, tenant scope, preserved filters, supplier choices, product validation, complete cash CSVs, and platform totals across pages. Browser verification used 52 synthetic products at desktop and 390px mobile widths.
- No database migration, production environment change, or account reset is required.

# Read-only administrator store viewing — November 3, 2026

- Added **View store** on the platform store list and management page. Administrators keep their own identity and see a prominent read-only banner with a return link.
- Reused store screens for products, stock/movements, suppliers/orders/receipts, sales/returns, expenses, cash sessions, reports, activity, profile, and private attachments. Navigation, filters, and record links retain the explicit administrator view.
- Added `platform.stores.view`; the permission migration grants existing full administrators only. Limited roles and owner memberships are unchanged. Every view requires platform MFA and current authorization. CSV exports additionally require reporting-export permission.
- Omitted mutation forms and enforced read-only access at the route, middleware, service, and tenant-model boundaries. Suspended/archived stores and disabled modules remain inspectable without enabling owner operations.
- Recorded `platform.store_viewed` for each successful access under the administrator's real identity. Cross-store resource mismatches are rejected and context is cleared after success/failure.
- Validation: **115 tests / 853 assertions**, including 11 administrator-view integration tests; PHPStan, Pint, Blade compilation, and production build pass. The synthetic-data browser preview confirms the banner, read-only product details, and administrator navigation.
- Production release `bc713b9` was deployed to the private Hostinger checkout. The permission migration enabled the existing full administrator. Thirteen production screens, access audit attribution, hidden mutation forms, the write guard, and context cleanup passed using synthetic rows inside a rolled-back transaction. SMTP and all other environment settings were byte-for-byte preserved; the public asset manifest matched the verified build.
- Pre-deployment on-server snapshot: `20261103-133635-b1c4ee`. Previous source/environment remain in the private deployment backup directory. No production data or credentials were downloaded. Live health/assets and unauthenticated route/source protection checks passed.


# SMTP account and announcement email — November 3, 2026

- Added a durable encrypted email outbox for owner invitations, password recovery, email verification, profile email changes, and password/two-factor security notices. Branded HTML and plain-text messages share the Pyesio style.
- Added optional owner email on announcement publication, with a current-audience snapshot, explicit opt-in, and eligibility checks before sending. Existing announcements and historical invitations are not emailed automatically.
- Added **Admin → Email delivery** with status filters, safe failure details, retry controls, and a test button limited to the signed-in administrator. Store management shows invitation delivery state and offers email or private-link recovery.
- Delivery runs through the existing Hostinger scheduler with bounded SMTP timeouts, automatic retries, lease recovery, encrypted payload cleanup, and tenant-context cleanup. Logging transports are never reported as successful SMTP delivery.
- Added an additive migration for delivery records and announcement opt-in. No merchant data seeding or existing account resets are needed.
- Validation: **104 tests / 624 assertions** against MySQL, PHPStan, Pint, production build, and Blade compilation. SMTP TLS authentication succeeded on Hostinger before deployment. Production release `ec4d14f` was installed in the private checkout, with the additive migration and private SMTP settings. The unchanged public asset manifest matched the verified local build. Rollback-only production checks passed for invitation creation, encryption, eligibility, mail rendering, and the admin delivery screen; all synthetic rows were rolled back.
- Live delivery: the one explicitly approved test was accepted by SMTP on its first attempt at **2026-11-03 13:42:07 Asia/Manila / 05:42:07 UTC**, following the automatic scheduler heartbeat at 13:42:01. No manual drain was used; the test payload was then removed from the outbox. Inbox placement was not independently inspected.
- HTTPS home/login/recovery/health/assets checks passed; the admin email page requires sign-in, public registration is absent, and environment/Git/source paths remain blocked. SMTP uses implicit TLS on port 465 with certificate validation and required TLS. All non-mail environment settings were preserved. Temporary credential-transfer files were removed after verification.
- Before deployment, private on-server snapshot `20261103-053733-220378` was created. The previous source and environment remain in the private deployment backup directory. No production database or environment was downloaded.


# Pyesio branding — November 2, 2026

- Applied the supplied Pyesio icon, logo, and tagline to application navigation, sign-in/recovery/activation screens, page titles, favicon, and touch icon.
- Reworked the shared visual style around navy, orange, light surfaces, clearer fields and tables, dashboard cards, and active navigation with icons. Both light and dark appearances remain supported.
- Branded workspace selection and account screens; removed unused starter branding and consolidated alternate layouts onto the supported shells.
- Brand images are original supplied PNGs served through Vite's hashed manifest under the existing protected public layout. No database migration or production environment change is required.
- Verification: existing 88 tests / 545 assertions, PHPStan, Pint, build and Blade compilation; desktop light/dark dashboard, desktop sign-in, and mobile product/authentication inspection.

See [branding notes](branding.md) for assets, palette, and deployment details.

# First-release workflow completion — November 2, 2026

- Editable draft purchase orders with revision checks, atomic line replacement, and retry protection. Placed and received records keep their original snapshots. Receipt reversal preserves a deliberately closed or cancelled order.
- Editable/archivable suppliers with normalized duplicate-name protection; archived suppliers remain available in historical receipts.
- Simpler checkout with one payment row initially, split-payment controls, exact-remaining shortcut, due/change totals, configurable accepted methods, reliable Enter-to-scan, and stale catalog-price review.
- Searchable, date/type-filtered Sales and Expenses history; local dates and tenant scope apply on the server.
- Detailed current/closed cash-session reconciliation, printing, and complete formula-safe CSV export with explicit tenant scope during streaming.
- Plain-language starting-cash and inventory guidance, compact quantity displays, and form-specific validation that preserves a stale stock version until the owner reloads and recounts.
- Consistent report snapshots and audited report exports. Administrator System status shows scheduler, backup verification, mail transport, debug mode, and failed jobs.
- Additive migration adds checkout methods, supplier active state, draft revisions/retry metadata, and original purchase unit cost. Existing data is retained; no seeding is required.
- Validation: 88 tests / 545 assertions against MySQL, PHPStan, Pint, production asset build, local browser sale/draft-edit walkthrough, and 360px cash/checkout checks. A local synthetic-data backup restored to a separate database with zero stock/cash reconciliation or relationship errors. Production backups are verified on the server; production data was not downloaded for the rehearsal.
- hPanel now runs the every-minute Laravel scheduler through a private shell wrapper; automatic heartbeat and queue execution were confirmed at 11:17 Asia/Manila. Automatic Git deployment into `public_html` is disabled; releases use the private checkout and explicit public assets.

See [completion status](completion-status.md) for operational boundaries and deployment evidence.

# Product entry update — November 2, 2026

- Three required fields for a new product: name/variant, selling unit, and price. Store-scoped SKUs are generated under the store lock, with an optional custom code.
- Standard selling-unit choices set fractional rules on the server. Existing custom units and historical stock remain unchanged.
- Optional category/brand selectors reuse store labels, offer deliberate new-label entry, normalize whitespace/case, and include common hardware categories.
- Save-and-add-another carries only unit/category/brand; product-specific fields reset. Price starts blank; zero remains an explicit choice.
- Duplicate name/brand/unit combinations (including archived products), SKUs, and barcodes are rejected. Whole-unit reorder thresholds cannot contain fractions.
- Validation restores only the submitted form and opens it for correction. Other product forms retain their own values. No schema migration or seed is required for this update.

# Operational pilot release — October 31, 2026

Version 0.3 replaces the portal placeholders with usable online store workflows. The original PRD remains the product target; this document identifies the shipped implementation and remaining validation boundaries.

## Implemented

- Admin store registration, physical address, independent owner identities, explicit existing-identity invitations, single-use 72-hour activation, audited owner replacement and recovery links.
- Mandatory administrator TOTP and named platform permissions; active store membership is rechecked under the store lock for mutations.
- Store lifecycle, module dependencies and enabled/restricted/disabled modes, scheduled/targeted announcements, store and platform audit views.
- Products, SKU/barcode search, category/brand labels, immutable unit conversions, archiving, fractional base units, reorder levels.
- Opening stock, physical counts with version checks, damage, append-only movements, six-decimal quantity/value precision, moving weighted average cost.
- Suppliers, draft/placed purchase orders, partial receiving, closing/cancelling outstanding quantities, and latest-movement full receipt reversals.
- Paid multi-line checkout, line fixed/percentage discounts, split payments, payment references, cash tender/change, immutable posted snapshots, printable internal records.
- Partial/full returns with cumulative quantity/refund limits, original rounding allocation, sellable/damaged disposition, and inventory loss recording.
- One open cash session per store, float, cash additions/withdrawals, close/count/variance, immutable session history, stale session rejection.
- Paid operating expenses and linked full reversals; private supporting documents with authenticated downloads.
- Shared financial summary service, product performance, end-date stock, store CSV exports, per-store and currency-separated admin reporting/export.
- Atomic postings, per-store serialization, submission fingerprints and idempotency, composite tenant foreign keys, server-side module enforcement, CSRF and no-store responses.
- Expiring admin-assisted owner recovery for the current host without SMTP, nonenumerating recovery responses, recovery rate limits, and session invalidation on password reset.
- Private backup command and daily scheduler entry; protected Hostinger entry template and deployment runbook.

## Concrete implementation choices

PHP and Asia/Manila are fixed onboarding defaults for this pilot. Monetary documents use integer centavos; stock quantities and cost/value use integer millionths, computed with Brick decimal/integer arithmetic. The database schema contains products/units, purchase orders/lines, generalized immutable business documents/lines, payments, stock movements, cash sessions/movements, private attachment metadata, invitations, announcements, and audit events. The actual migrations are the implementation reference; the conceptual data-model document describes the broader target domain.

Operational forms use Blade and Alpine with ordinary server POST/redirect responses. Livewire/Fortify provide account settings and authentication. There is no production Node process. Checkout confirmation only appears after the database transaction commits.

Invitations are manually shared by the administrator rather than emailed by an outbox in this release because production has no configured mail delivery. Draft purchase corrections initially used cancel-and-recreate; the November 2 completion update adds draft editing with revision checks. Existing posted records are never rewritten. Used transaction modules remain restricted rather than fully disabled when corrections could still be needed.

Tax remains unconfigured: sales preserve entered amounts and are labeled internal records, while profit is explicitly an estimate without tax separation. The owner's existing fiscal invoicing remains necessary. This is not a certification or a determination that a merchant is non-VAT.

## Verification and operational boundaries

The release test suite includes existing authentication/MFA scenarios plus store onboarding, existing identities, module restrictions, tenant isolation, all portal page rendering, posting rollback, split payments, partial/damaged returns and rounding, expense reversal, cash variance, stale counts and sessions, purchase receiving/reversal, private attachment scope, report exports, local-midnight boundaries, and announcement audience rules. Separate PHP processes exercise simultaneous duplicate sales, last-item competition, and over-refunding against real MySQL transactions.

Local fixture records are only development data and are never seeded into production. The live deployment smoke check uses a database transaction that rolls back synthetic records. Production upgrades preserve the existing admin account, application key, and database configuration, apply additive migrations, and retain a private pre-release snapshot.

The large 12-month performance target, independent off-site disaster recovery, per-store recovery rehearsal, legal retention policy, automated notification outbox, and fiscal certification have not been established by the integration suite. They remain deployment/operational follow-ups rather than implied guarantees. Backups on the same Hostinger account do not protect against loss of the entire account. The operator must monitor backup success and disk usage and maintain an independent protected copy.

See [the user guide](user-guide.md) for same-day setup and [Hostinger deployment](hostinger-deployment.md) for release, backup, and rollback commands.

## Scanner and receipt printing — selective release prepared November 4, 2026

Queued exact scans, manual-entry fallback, 58/80 mm and Document/A4 receipt previews, test printing and COPY reprints are ready on `codex/scanner-receipts-release`. Offline POS is excluded. Local checks passed: 150 PHP tests / 1,312 assertions, 8 JavaScript tests, Pint, PHPStan and the production build. Deployment status and operator instructions are in [Scanner and receipt printing](scanner-receipts.md).

# SMTP account and announcement email — November 3, 2026

- Added a durable encrypted email outbox for owner invitations, password recovery, email verification, profile email changes, and password/two-factor security notices. Branded HTML and plain-text messages share the Pyesio style.
- Added optional owner email on announcement publication, with a current-audience snapshot, explicit opt-in, and eligibility checks before sending. Existing announcements and historical invitations are not emailed automatically.
- Added **Admin → Email delivery** with status filters, safe failure details, retry controls, and a test button limited to the signed-in administrator. Store management shows invitation delivery state and offers email or private-link recovery.
- Delivery runs through the existing Hostinger scheduler with bounded SMTP timeouts, automatic retries, lease recovery, encrypted payload cleanup, and tenant-context cleanup. Logging transports are never reported as successful SMTP delivery.
- Added an additive migration for delivery records and announcement opt-in. No merchant data seeding or existing account resets are needed.
- Validation: **104 tests / 624 assertions** against MySQL, PHPStan, Pint, production build, and Blade compilation. SMTP TLS authentication succeeded on Hostinger before deployment. Live message acceptance is verified separately; see [SMTP email](smtp-email.md).

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

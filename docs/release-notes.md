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

Invitations are manually shared by the administrator rather than emailed by an outbox in this release because production has no configured mail delivery. Draft purchase corrections use cancel-and-recreate rather than inline amendments. Existing posted records are never rewritten. Used transaction modules remain restricted rather than fully disabled when corrections could still be needed.

Tax remains unconfigured: sales preserve entered amounts and are labeled internal records, while profit is explicitly an estimate without tax separation. The owner's existing fiscal invoicing remains necessary. This is not a certification or a determination that a merchant is non-VAT.

## Verification and operational boundaries

The release test suite includes existing authentication/MFA scenarios plus store onboarding, existing identities, module restrictions, tenant isolation, all portal page rendering, posting rollback, split payments, partial/damaged returns and rounding, expense reversal, cash variance, stale counts and sessions, purchase receiving/reversal, private attachment scope, report exports, local-midnight boundaries, and announcement audience rules. Separate PHP processes exercise simultaneous duplicate sales, last-item competition, and over-refunding against real MySQL transactions.

Local fixture records are only development data and are never seeded into production. The live deployment smoke check uses a database transaction that rolls back synthetic records. Production upgrades preserve the existing admin account, application key, and database configuration, apply additive migrations, and retain a private pre-release snapshot.

The large 12-month performance target, independent off-site disaster recovery, per-store recovery rehearsal, legal retention policy, automated notification outbox, and fiscal certification have not been established by the integration suite. They remain deployment/operational follow-ups rather than implied guarantees. Backups on the same Hostinger account do not protect against loss of the entire account. The operator must monitor backup success and disk usage and maintain an independent protected copy.

See [the user guide](user-guide.md) for same-day setup and [Hostinger deployment](hostinger-deployment.md) for release, backup, and rollback commands.

# First-release completion status — November 3, 2026

## Agreed scope

Independent hardware businesses, one owner per store, online operation, Laravel, and a MySQL-compatible database on Hostinger Web/Cloud hosting. The app supports internal management records; registered fiscal invoicing stays with each store's existing process.

| Workflow | Delivered |
| --- | --- |
| Platform | Store/address registration, owner activation/recovery, lifecycle, module controls/dependencies, targeted/scheduled announcements, audit, centralized store/currency reports |
| Products | Short registration form, generated SKUs, reusable category/brand labels, duplicate prevention, fixed unit conversions, archive/reactivate |
| Inventory | Reviewed opening balances/costs, counts, damage/loss, low-stock visibility, permanent movements, stale-count protection |
| Purchasing | Supplier maintenance/archive, editable drafts with revision checks, place, partial/full receiving, short close/cancel, eligible receipt reversal |
| Sales and returns | Barcode/SKU search, discounts, split payments, due/change, configured methods, stale-price review, print, partial/full sellable/damaged returns |
| Cash and expenses | One open drawer, starting cash, cash in/out, count/variance/close, session detail/CSV, paid expenses/reversals, private attachments |
| Reporting | Shared financial definitions, product performance, end-date inventory, filtered record history, scoped/audited CSV, retained historical snapshots |
| Operations | Protected public directory, private application/environment/storage, administrator health page, every-minute scheduler and daily verified snapshots |

## Validation evidence

- `composer test`: **104 tests, 624 assertions**, including PHPStan and Pint; MySQL integration and separate-process concurrency tests pass.
- `npm run build`: production assets built successfully without a production Node process.
- Browser walkthrough with local synthetic data: scan item, cash/e-wallet split totaling PHP 300 against PHP 285, PHP 15 change, committed record; create a purchase draft and edit quantity without changing stock; cash-session reconciliation and 360px cash and checkout layouts.
- Local restore rehearsal: compressed development database imported into a separate recovery database. Restored 1 synthetic store, 4 products, 6 documents, and 11 audit events. Stock/cash discrepancies and orphaned document/attachment/membership relationships: **zero**. Private archive extracted; matching local environment retained. This small fixture is not a production-size or per-store selective recovery drill.
- hPanel: auto-deployment to the public document root switched off; `* * * * *` Custom scheduler saved. PHP CLI resolves to 8.3.36.
- Production application release `ec72092` was installed in the private Laravel checkout with its matching built assets and additive migration. Rollback-only synthetic checks passed for product entry, payment configuration, draft editing/retry, inventory posting, and eight authenticated screens; synthetic rows were rolled back.
- HTTPS checks: home/login/health/assets return 200; protected portals redirect to login; public registration is absent; environment/Git/source/log paths are blocked. Production environment and existing records were preserved.
- Fresh production snapshot `20261102-031234-c877d0` passed on-server database/private-file/environment checksums and archive-readability verification. Production debug is off and failed jobs count is zero.
- Automatic scheduler execution confirmed: hPanel invoked the private `scheduler.sh` wrapper at **2026-11-02 03:17:01 UTC / 11:17:01 Asia/Manila**. The heartbeat and bounded queue task completed successfully; no manual scheduler execution was used as this evidence. Daily backup is scheduled for 02:00 Asia/Manila.

- SMTP extension deployed November 3: invitations, account recovery/verification/security notices, optional announcement emails, and administrator delivery tracking. An explicitly approved test was automatically accepted by Hostinger SMTP at 13:42:07 Asia/Manila on its first attempt. Inbox placement was not independently inspected. See [SMTP email](smtp-email.md).

## Boundaries for daily use

- Noncash payments are recorded only after the owner confirms them externally; the app does not charge a payment provider.
- Printed documents are internal records. Tax separation, registered invoice numbering, and fiscal certification are not implemented. Profit is a management estimate.
- SMTP account email, optional announcement email, delivery history, and retries are implemented. Private links remain a fallback; [SMTP email](smtp-email.md) describes delivery guarantees and limits.
- Backups are private on the hosting account. An independent off-site destination, retention policy, production-size timed restore, per-store extraction, and 12-month load benchmark remain operational follow-ups. On-server checksum verification does not prove a full disaster recovery target.
- Owners must review their real opening quantities/costs and starting cash. No synthetic seed data is deployed.
- Staff roles, customer credit, quotations, delivery management, offline selling, multiple branches, and provider payment integration are later increments.

Use [the user guide](user-guide.md) for daily work and [the deployment runbook](hostinger-deployment.md) for release/recovery operations.

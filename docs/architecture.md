# Hardware Store System Architecture

Implementation status: the October 31 operational pilot is described in [release notes](release-notes.md) and [the user guide](user-guide.md). This specification retains the broader target contract; tax/fiscal processing remains outside the current pilot. SMTP account and optional announcement email are implemented as described in [SMTP email](smtp-email.md).

Build one Laravel application with two portal experiences, a shared business layer, and a transactional MySQL database. Laravel and Hostinger Web or Cloud hosting through hPanel are user-confirmed choices. Exact hosting capacity, services, and the deployment layout remain in [open decision O-04](decisions-and-research.md).

## Laravel implementation baseline

Use Laravel 13 on PHP 8.3 or newer, with PHP 8.4 as the production target once both the web and CLI runtimes are verified. Pin dependencies through `composer.lock` and the npm lockfile. The proposed UI is the official Livewire starter kit using Blade, Tailwind CSS, and Vite; compile assets locally or in CI.

Use Eloquent models and migrations, Form Requests or equivalent validated Livewire inputs, policies and gates, service-container scoped store context, and explicit business action classes. Keep posting logic independent of Livewire component state. Livewire properties, route parameters, and hidden fields are untrusted input and require resource ownership checks on every action.

Use Laravel session authentication and Fortify for login, recovery, email verification, and TOTP. Disable public registration. Implement invitations and controlled owner replacement as product workflows; do not expose a starter kit's public team creation or membership controls as store administration.

Suggested application layout:

```text
app/Actions/                 Business commands grouped by module
app/Models/                  Eloquent models and store-scoping behavior
app/Policies/                Action and resource authorization
app/Http/Middleware/         Store context, lifecycle, entitlement, platform MFA
app/Livewire/Admin/          Platform portal components
app/Livewire/Store/          Store portal components
app/Services/Reporting/      Shared metric queries
app/Support/Tenancy/         Verified context and scoped query primitives
app/Jobs/                    Bounded jobs with explicit scope setup and cleanup
database/migrations/         MySQL-compatible schema and constraints
tests/Feature/               HTTP, Livewire, isolation, and posting integration tests
tests/Unit/                  Pure decimal, allocation, and valuation tests
```

Use the database drivers for sessions, queue, and shared cache locks. The baseline requires neither Redis nor Horizon, Reverb, Octane, a Node server, or a persistent process supervisor. Staging must verify the actual MySQL version, SQL modes, generated columns, foreign keys, and InnoDB locking before release.

## Application boundaries

```mermaid
flowchart TD
    A[Admin browser]
    S[Store browser]
    E[Authenticated application endpoints]
    Z[Authorization and verified scope]
    B[Business modules and shared reporting]
    D[(Relational database)]
    F[Private attachment storage]
    W[Worker and notification delivery]
    A --> E
    S --> E
    E --> Z
    Z --> B
    B --> D
    B --> F
    D --> W
```

Portal paths are `/admin/*` and `/store/*`. They share authentication infrastructure but use separate authorization policies. An admin role grants platform actions; a Store Owner membership grants operations in its store. A person with both roles still uses the explicit scope of each request.

Use a modular monolith initially. A business transaction must not depend on distributed commits between sales, inventory, payments, and cash. A notification failure cannot roll back a committed sale or create a partial store.

## Business modules

| Module | Owns | Main dependencies |
| --- | --- | --- |
| Identity and membership | Activation, Laravel authentication, store membership, session invalidation | Fortify, session guard, policies |
| Store administration | Store profile, lifecycle, owner provisioning | Identity, audit, notifications |
| Module access | Per-store entitlements and dependency validation | Store lifecycle, audit |
| Announcements | Audiences, publication windows, acknowledgements | Identity and verified store scope |
| Catalog | Products, categories, units, prices | Store scope |
| Inventory | Quantity ledger, balances, counts, weighted cost | Catalog, audit |
| Purchasing | Suppliers, purchase orders, receipts and reversals | Catalog, inventory |
| Sales and returns | Paid sales, payment recording, linked returns | Catalog, inventory, cash |
| Cash and expenses | Sessions, cash movements, paid expenses | Payment methods, audit |
| Reporting | Store and platform metric queries and CSV export | Posted records across modules |
| Platform operations | Outbox, jobs, private files, monitoring, backups | Infrastructure |

Business services enforce invariants regardless of the calling portal. Request handlers must not directly update stock balances, calculate their own profit formula, or bypass the module service to post a financial record.

## Store isolation

Every operational request obtains a verified store context from the authenticated identity and current membership. A client-supplied store ID selects a candidate scope; it is never proof of permission. Scope checks apply to list, search, detail, create, update, export, download, and background processing.

Use Laravel's `mysql` connection with MySQL 8.4 for local development and CI. Hostinger Web and Cloud hosting expose a MySQL-compatible service whose underlying engine is MariaDB; verify schema and transaction compatibility on that actual host engine before production. This design does not claim database row-level security. Enforce the boundary through verified Laravel store context, fail-closed model scopes, scoped route binding, policies, and store-scoped composite foreign keys. A model scope must throw or deny when no store context exists, rather than fall back to an unrestricted query.

Global Eloquent scopes alone are insufficient: raw queries, relationship lookups, validation queries, joins, aggregates, exports, and bulk updates must carry explicit verified store scope. Review all scope bypasses through a narrow allowlist. Never call an unrestricted query or `withoutGlobalScopes` from an ordinary store controller or component.

Central reporting uses a separately authorized reporting service with an explicit platform scope object. Platform profile and entitlement changes use their own policies and action classes; report access never grants operational posting. Use a read-only reporting database account if the account supports appropriate grants, but do not assume hPanel provides custom roles or database-enforced tenant filtering. Application authorization remains mandatory with or without a second connection.

Background jobs include verified ownership metadata and reauthorize when running. Set context for each job and clear it in `finally`, including exceptions and retries, so the next job cannot inherit another store. Cache keys, file paths, search indexes, notification payloads, and generated downloads carry store scope. Store owners never receive shared database backups or unrestricted object storage credentials.

## Command processing

A business command follows this sequence:

1. Authenticate; determine platform or store scope; verify membership and store status.
2. Verify action permission, module mode, and related resource ownership.
3. Validate request shape, bounded line counts, allowed units, totals, and reason fields.
4. Start a database transaction, reserve or retrieve the scoped idempotency key, and lock affected records.
5. Recheck mutable conditions inside the transaction, including module mode, store state, cash session, quantities, return limits, and document version.
6. Write the source document, snapshots, inventory and cash effects, audit event, and any outbox work.
7. Commit and return a durable document ID and posting status.

The command must either commit every required effect or none. Acquire product locks in a stable product-ID order. Sales and session closure take compatible session locks so a closed session cannot receive a late sale. Receipt corrections, concurrent returns, and stock counts use the same stock concurrency boundary.

Implement posting with Laravel `DB::transaction` and InnoDB row locks through `lockForUpdate` on persistent balance, source, and session rows. Create required balance rows before they can be sold; locking a missing row is not a substitute for a unique constraint. Retry bounded deadlocks only through the same idempotent command. Verify these behaviors against MySQL in CI; SQLite does not establish the required concurrency guarantees.

Events are posted with server timestamps. User-entered supplier document dates and expense receipt dates are informational source dates. They cannot move a posting into an earlier cost or cash period. Expense reporting follows its explicit timestamp rule in [reporting](reporting.md).

## Idempotency and network interruption

Require an idempotency key for onboarding, posting a sale, return, receipt, adjustment, expense, reversal, or cash action. Bind it to the authenticated actor, scope, command name, and normalized payload hash.

- The same key and payload returns the existing outcome.
- The same key with a different payload returns a conflict.
- Concurrent use has one winner, protected by a unique database constraint.
- A rolled-back command has no successful outcome; a retry safely re-evaluates it.
- The client retains the submission ID until it knows the outcome and queries a status endpoint after an uncertain response.

Keep committed financial command keys with their source records. The client may retain an unposted draft locally, but it cannot queue completed sales offline or claim payment recording succeeded while disconnected.

## API conventions

The first browser interface uses session-authenticated Laravel web routes and Livewire actions. Route examples below define service boundaries for JSON endpoints where useful; they do not require a separate SPA or a public API. Define the executable request and authorization contract before implementing each milestone, and apply the same checks to Livewire action requests.

| Route family | Examples | Scope |
| --- | --- | --- |
| Platform | `/api/admin/stores`, `/api/admin/stores/{id}/modules`, `/api/admin/announcements` | Explicit platform permission |
| Store | `/api/store/products`, `/api/store/purchase-orders`, `/api/store/sales` | Verified selected store |
| Posting commands | `/api/store/sales/{id}/post`, `/api/store/returns/{id}/post` | Store, action, module, idempotency |
| Reports | `/api/store/reports/sales`, `/api/admin/reports/sales` | Different scope policies; one metric implementation |
| Recovery | `/api/commands/{submissionId}` | Only original actor and authorized scope |

Use stable machine-readable errors with `code`, safe `message`, field details where useful, and `request_id`. Use `401` for missing authentication, `403` for unavailable permissions or modules, `404` for resources outside the accessible scope, `409` for conflicting state or idempotency misuse, and `422` for invalid business input. Do not expose other-store names or record existence in errors.

List endpoints use bounded pagination and an allowlist of sort and filter fields. Draft updates use a version or equivalent optimistic concurrency check. Server totals override client totals; return computed totals for confirmation if a stale draft changes.

## Numbers and timestamps

Use exact decimal arithmetic throughout. Store money totals at currency precision, unit costs at six decimal places, and base quantities and conversion factors at six decimal places. The proposed pilot currency uses two decimal places. Do not represent business amounts with binary floating-point arithmetic.

Use MySQL `DECIMAL` columns, Eloquent decimal casts as strings, and a decimal arithmetic library such as `brick/math` for calculations and explicit rounding. Do not cast those strings to PHP floats or rely on JavaScript totals for posting. Lock and test the arithmetic package with the application dependencies.

Store UTC timestamps and an explicit IANA timezone for each store. Calendar date filters become half-open timestamp intervals. Central reports compare the same interval and as-of time; see [reporting](reporting.md).

## Notifications and private files

Create invitation delivery work in a transactional outbox. Retry delivery independently and expose status to the admin. Provider timeouts do not recreate a store or create multiple valid activation tokens. Generate a new invitation only through the explicit resend command.

Drain the database queue and outbox through short, non-overlapping scheduled work launched by hPanel cron. Keep jobs bounded and idempotent; set retry timing longer than the permitted job execution window. Do not assume an always-running worker. Invitation delivery latency therefore includes the measured cron interval and queue delay. Leave expensive report exports synchronous only within verified limits; otherwise partition them into short queued work.

Attachments are private, validated for size and permitted content type, and served after authorization through short-lived access. File upload finalization verifies store scope and record ownership. Orphan cleanup never deletes referenced files. Do not accept payment card data as an attachment or store payment credentials.

## Reporting architecture

Start with indexed queries over posted source records and ledgers. Both portals call the same reporting service with different authorized scopes. Every response includes interval, currency, included stores, and as-of or generated time.

If aggregates are introduced later, use them only after reconciliation against source records. Expose aggregation freshness, handle reversals, and include a deterministic rebuild path. Do not maintain unrelated admin and store calculations.

## Deployment and operations

Use separate development, staging, and production environments, each with distinct credentials and data. Version database migrations in the application repository. Test migrations against representative data and use compatible changes during deployment.

Follow the [Hostinger deployment guide](hostinger-deployment.md). Deploy the locked PHP dependencies and built Vite assets, serve only Laravel's public directory, retain a stable application encryption key, keep attachments private, and verify web, CLI, Composer, and cron use compatible PHP runtimes. Disable full-page caching for authenticated portals, login, Livewire requests, and private JSON responses to prevent cached user data crossing store boundaries.

Log request IDs, verified scope, command IDs, errors, and posting duration without logging secrets. Monitor failed onboarding, notification retries, denied cross-store requests, stock invariant violations, reconciliation failures, and backup failures. Alerts go to a configured operational channel after an explicit delivery setup.

Automate database and private file backups, encrypt them, restrict restoration privileges, and rehearse recovery. Restoring one store from a shared backup must use an isolated recovery environment and scoped extraction, not overwrite live records for other stores. Final retention and recovery settings depend on O-06 and O-09.

## Deferred architecture

Offline operation requires a separate conflict, local security, payment, and fiscal numbering design. Multi-location operation requires location-scoped inventory and cash. Credit requires receivable and allocation ledgers. These are not solved by exposing dormant UI or adding unscoped columns during release one.

## Framework references

The implementation baseline uses the official [Laravel 13 release policy](https://laravel.com/docs/13.x/releases), [starter kit and Fortify integration](https://laravel.com/docs/13.x/starter-kits), [database transactions](https://laravel.com/docs/13.x/database), and [queue controls](https://laravel.com/docs/13.x/queues). Store scope, financial posting rules, hosting defaults, and module policies above are project design decisions.

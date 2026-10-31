# Hardware Store Access Control

Implementation status: the October 31 operational pilot is described in [release notes](release-notes.md) and [the user guide](user-guide.md). This specification retains the broader target contract; tax/fiscal processing and automated email delivery are not part of the current pilot.

Access requires a valid identity, an authorized scope, an allowed action, an acceptable store state, and a compatible module mode. Navigation visibility is a convenience; the backend is the enforcement boundary.

## Identity and store membership

User identities are separate from Stores. A store membership connects a user to a store and role. The first release has one current operational owner per store, with historical memberships retained for audit. A user can be associated with more than one store only through an explicit authorized onboarding flow and must select a verified store context.

An admin registering an already-known owner email must explicitly choose the existing identity. Acceptance requires that person to authenticate as that identity and accept the store invitation. Creating a store never resets an existing password or silently changes an existing membership.

Use Laravel's session guard and Fortify as the baseline identity implementation. Disable public registration and any starter-kit team creation flow. Route all owner creation through the admin invitation action. A TOTP feature being available does not enforce platform MFA by itself: admin middleware must require confirmed enrollment and a completed challenge before portal access.

To replace an owner, create a pending invitation for the successor. On authenticated acceptance, atomically activate the new owner membership and revoke the previous owner membership. Historical records retain their original actor IDs. Revoke the old store sessions without disrupting unrelated memberships. This is a controlled administrative action, not a general staff invitation feature.

## Permission matrix

`Own store` always means a current verified membership and accessible store state. `Platform` actions require a named permission; they do not arise from a store membership.

| Capability | Platform Admin | Store Owner in release one |
| --- | --- | --- |
| Register, suspend, reactivate, archive stores | Platform permission | No |
| View store identity and physical address | Authorized platform scope | Own store |
| Edit contact and physical address | Authorized platform scope | Own store |
| Change store code or currency policy | Platform permission with invariants | No |
| Provision or replace owner | Platform permission | No |
| Configure module access | Platform permission | No |
| Publish announcements and choose audience | Platform permission | No |
| Read addressed announcements | Platform preview permission | Own store |
| Manage products, stock, purchasing | No implicit store write access | Own store and module rules |
| Post sales, returns, cash, expenses | No implicit store write access | Own store and module rules |
| Read store financial reports | Platform reporting permission | Own store and reports module |
| Read combined merchant reports | Platform reporting permission | No |
| Export reports | Separate scoped export permission | Own store and reports module |
| Read audit events | Platform audit permission | Own store business events; no platform secrets |
| Modify posted records or audit events | No | No |
| Delete a store and its financial history | No ordinary UI action | No |
| Access another store by editing a URL | No unscoped route | No |

Use explicit permissions such as `platform.portal.access`, `platform.stores.manage`, `platform.modules.manage`, `platform.announcements.manage`, `platform.reports.read`, `platform.reports.export`, and `platform.audit.read`. The foundation bootstrap grants only `platform.portal.access`; module-specific permissions are added with their reviewed workflows. Entering the portal does not grant permission to operate every module.

Future Cashier, Manager, and Inventory Staff roles are design candidates, not active release-one roles. Add them with a separate permission specification and tests when multiple staff is built.

## Store states

| State | Store user behavior | Platform behavior |
| --- | --- | --- |
| Pending activation | Invitation acceptance only; no operational access | Edit setup, resend invitation, cancel incomplete setup |
| Active | Allowed operations within membership and modules | Manage setup and authorized reports |
| Suspended | Deny store reads and writes except safe sign-out and recovery entry points | Manage status and read authorized retained history |
| Archived | No operational access; no new owner activation | Read retained history; exceptional reactivation requires an audited administrative process |

Suspension and archive are rechecked during transaction posting. A request started before suspension cannot post afterward without passing the in-transaction lifecycle check.

## Module catalog and dependencies

Foundation capabilities are always present: identity, membership verification, store context, basic profile, announcement reading, and audit recording. They cannot be disabled with a feature toggle.

| Module key | Purpose | Dependencies for new operations |
| --- | --- | --- |
| `catalog` | Products, units, prices | Foundation |
| `inventory` | Stock ledger and counts | `catalog` |
| `cash` | Sessions and manual cash movements | Foundation |
| `sales` | Paid sales and linked returns | `catalog`, `inventory`, `cash` |
| `purchasing` | Suppliers, orders, receiving | `catalog`, `inventory` |
| `expenses` | Paid operating expenses | `cash` |
| `reports` | Store KPI cards, report queries, report export | Foundation and retained posted data |

Reports may read retained history from a restricted or disabled source module. Platform reports use platform permissions and do not depend on the store's report entitlement. Foundation dashboard content remains available when store report cards are hidden.

## Module modes

| Mode | New business work | Historical operational documents | Resolution actions |
| --- | --- | --- | --- |
| Enabled | Allowed with permissions | Readable | Allowed |
| Restricted | Blocked | Readable | Only listed resolution commands |
| Disabled | Blocked | Retained and readable through scoped history | Blocked until restored to restricted or enabled |

Turning off a used module first moves it to restricted mode if unresolved work exists. The admin sees why, what remains permitted, and which dependencies must stay available. Full disable requires no open obligations or unfinished resolution work. A disabled `reports` module denies report queries and new exports while preserving source documents and platform reportability.

| Restricted module | Allowed resolution actions |
| --- | --- |
| Catalog | Read product snapshots; no new SKU, price, or unit edits |
| Inventory | Movements caused by an authorized linked return or receipt reversal; no free-form new adjustment |
| Cash | Close an existing session; open a session explicitly limited to resolution; required additions, confirmed refunds, or reversals tied to that resolution |
| Sales | Return and refund an existing posted sale; no new sale |
| Purchasing | Cancel unreceived quantities and reverse an eligible posted receipt; no new order or receipt |
| Expenses | Reverse an existing expense; no replacement expense until enabled |
| Reports | Read existing metrics; no new scheduled or expanded capability beyond the first-release read and export policy |

The first release has no report scheduler. Restricted report access retains the same scoped read and CSV export capabilities; this mode simply prevents later expansion from inheriting access automatically.

A resolution command records its source obligation and reason. A resolution-only cash session cannot accept a new sale or unrelated cash movement. Required dependencies must be enabled or restricted in a mode that permits the same resolution. The module configuration service rejects inconsistent changes.

## Session and request security

- Require multifactor authentication for platform users and protect recovery from account enumeration.
- Use Laravel database sessions with secure, HTTP-only cookies and CSRF protection for browser and Livewire mutations.
- Recheck membership, store state, and entitlement server-side. Cached authorization must invalidate immediately on relevant changes or consult a current authorization version.
- Rate limit sign-in, activation, recovery, exports, and expensive report requests.
- Bind activation and recovery tokens to purpose, identity, expiry, and one-time use. Store token hashes, not plaintext tokens.
- Do not include another store's identity, counts, files, or resource existence in denial responses.

## Files and exports

Authorize access to the underlying record before issuing a private download. Keep store scope in object metadata and generated export metadata. Expired or revoked access cannot mint a new download URL. Short-lived URLs already issued have a bounded expiry determined by the security configuration.

For cross-store reports, verify `platform.reports.read` or `platform.reports.export` before executing the query, audit the selected scope, and return only the report fields required by that permission. Customer personal details are not included in platform performance summaries by default.

## Required isolation checks

Use two stores with overlapping SKU codes and distinct users. Test every store-owned entity through list, search, detail, mutation, report, export, and file paths. Test scoped jobs, cache reuse, connection reuse, guessed document numbers, and stale sessions after membership removal. Test that an administrator with report permission but without store management permission cannot change a store, and cannot post merchant transactions.

The enforceable boundary must survive client modification. Hidden buttons, random IDs, and a `store_id` field on its own are insufficient authorization controls. See [OWASP multi tenant security](https://cheatsheetseries.owasp.org/cheatsheets/Multi_Tenant_Security_Cheat_Sheet.html).

## Laravel and MySQL enforcement

Resolve store context through authenticated middleware and current membership; use scoped route bindings, policies, a fail-closed store model scope, explicit query scoping, and composite foreign keys. MySQL on the selected hosting baseline does not provide the previously proposed PostgreSQL row-security boundary. Database credentials are never given to store users.

Keep any global scope bypass inside named platform services that demand explicit platform permission. Reauthorize every Livewire action and related resource ID, including hydrated component properties. Validate unique values within the current store. Job middleware must establish scope and clear it on success or failure before the next queued job.

Disable Hostinger or LiteSpeed full-page caching on authentication, admin, store, Livewire, and private response paths. Store-dependent application cache keys include verified store identity. Add integration cases for cache leakage, unscoped validation, raw query paths, Livewire ID substitution, and a queue worker processing two stores in sequence.

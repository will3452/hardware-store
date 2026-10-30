# Hardware Store Decisions and Research

Baseline date: October 31, 2026, Asia/Manila. This register separates user-confirmed requirements from engineering defaults and decisions that need product or operational input.

## Confirmed requirements

| ID | Decision | Basis |
| --- | --- | --- |
| C-01 | Stores belong to different businesses and owners | User clarification |
| C-02 | First release operates online | User clarification |
| C-03 | Platform Admin registers stores with their physical addresses | Original product brief |
| C-04 | Registering a store automatically provisions an initial owner user | Original product brief |
| C-05 | Additional users must be possible in the future | Original product brief |
| C-06 | Admin controls enabled modules or features per store | Original product brief |
| C-07 | Admin publishes announcements | Original product brief |
| C-08 | Store operations include reporting on earnings or performance | Original product brief |
| C-09 | Store performance is also visible centrally to the admin | Original product brief |
| C-10 | Produce a PRD and documents needed for implementation | Follow-up request |
| C-11 | Clone the supplied GitHub repository and retrieve its Markdown files | Follow-up request; repository was empty at retrieval |

## Proposed implementation defaults

These defaults make the first build concrete. They may be changed before implementation; changing a financial or authorization rule requires updating all affected specifications and tests.

| ID | Default | Reason and effect |
| --- | --- | --- |
| P-01 | Names: Admin Portal, Store Portal, Shared Platform; roles: Platform Admin and Store Owner | Simple and consistent product vocabulary |
| P-02 | One business workspace and one physical location per Store in release one | Matches initial scope while preserving a future location model |
| P-03 | Modular monolith with a relational database; PostgreSQL preferred | Allows atomic business operations and explicit store isolation |
| P-04 | Fully paid sales only initially; credit, quotations, and deliveries follow | Keeps the first transaction and reconciliation model bounded |
| P-05 | Moving weighted average inventory costing, recorded at each movement | Produces stable historical cost of goods sold |
| P-06 | Block negative inventory and duplicate business postings | Preserves stock and report integrity |
| P-07 | One open cash session per store; opening required for sales and refunds | Creates a clear daily reconciliation boundary |
| P-08 | Paid operating expenses only; purchasing does not record supplier settlement | Avoids implying a general ledger or payable subsystem |
| P-09 | PHP and Asia/Manila as pilot defaults; persist currency and timezone explicitly | Fits the conversation context; requires confirmation at setup |
| P-10 | Money totals at 2 decimals, unit cost and quantity at 6 decimals; decimal arithmetic and half-up rounding | Handles retail totals and fractional hardware quantities |
| P-11 | Posted business documents are immutable; corrections are linked reversals | Keeps reports and audit history explainable |
| P-12 | No arbitrary backdating of inventory, sales, returns, receipts, expenses, reversals, or cash events | Prevents retroactive changes to costing and closed cash sessions |
| P-13 | Separate platform reporting permissions from store operational permissions | Central oversight does not silently grant merchant write access |
| P-14 | CSV export, basic printable sale copy, and manual product entry initially | Supports practical use without assuming printer or import integrations |
| P-15 | One current operational owner membership per store in release one | Matches the one-user requirement; preserves membership history |
| P-16 | Restricted mode permits narrowly defined resolution of historical work | Stops new use without stranding obligations or deleting history |
| P-17 | Cash refunds and manual withdrawals cannot make expected drawer cash negative | Requires a recorded cash addition or another confirmed payment method when funds are insufficient |

## Open decisions and gates

Implementation may proceed on independent areas while these are unresolved. A gate blocks only the dependent capability or launch step.

| ID | Decision | Proposed direction | Gate | Decision owner |
| --- | --- | --- | --- | --- |
| O-01 | Confirm pilot country, currency, timezone, and tax registration scenarios | Philippines, PHP, Asia/Manila | Before final tax and document design | Product owner |
| O-02 | Determine applicable fiscal document, numbering, registration, and software requirements | Review actual pilot store obligations with a local accounting or compliance specialist | Before fiscal acceptance tests and real fiscal operation | Product owner with specialist |
| O-03 | UI framework, backend runtime, ORM, and authentication provider | Keep the modular monolith and database transaction boundary | Before application scaffold | Engineering with product owner on operating costs |
| O-04 | Hosting region, managed database, file storage, email service, and budget | Managed services with automated backups | Before production infrastructure | Engineering and product owner |
| O-05 | Platform visibility policy and merchant disclosure | Explicit per-role reporting access with merchant-facing disclosure | Before real merchant data onboarding | Product owner |
| O-06 | Retention, deletion, audit retention, and attachment limits | Archive business records; define lawful retention and export process | Before production onboarding | Product owner with operational review |
| O-07 | Pilot stores, data volumes, devices, and receipt printers | Desktop browser and keyboard barcode scanners first | Before pilot readiness review | Product owner |
| O-08 | Exact scope of the next increment | Credit first if pilot stores routinely sell on account | Before expansion estimation | Product owner |
| O-09 | Backup retention and acceptance of recovery and availability targets | Start with the proposed PRD targets and test restoration | Before production infrastructure approval | Product owner and engineering |
| O-10 | Cash session maximum age and late expense policy | No forced midnight close; operational timestamps govern posting | Before cash workflow sign-off | Product owner |

Tax rates, statutory document rules, certification, subscription tiers, launch dates, and payment provider integrations are not assumed by this baseline. The tax fields in the model preserve room for the decision in O-02.

## Research supporting the module selection

External products establish useful retail patterns. They do not prescribe this system's tenant model, permissions, costing policy, or release scope. Those are design decisions above.

| Source | Supported finding | Effect on this plan |
| --- | --- | --- |
| [Lightspeed hardware store POS](https://www.lightspeedhq.com/pos/retail/hardware-store-pos/) | Hardware retail offerings combine inventory, customer profiles, and performance reporting | Supports the core sales and stock modules |
| [Lightspeed purchase orders](https://www.lightspeedhq.com/pos/retail/purchase-orders/) | Purchasing and receiving are connected but distinct, with partial delivery support | Separate order quantities from received stock |
| [Odoo units of measure](https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/inventory/product_management/configure/uom.html) | Purchase and sales units can convert to inventory units | Product-specific base units and conversion snapshots |
| [Odoo customer accounts](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/payment_methods/customer_credit.html) | POS supports customer credit and later settlement | A defined later credit and collections increment |
| [Epicor home centers](https://www.epicor.com/en-us/solutions/industries/building-supply/home-centers/) | Building supply workflows include inventory, quotes, contractor relationships, delivery, and financial analysis | Preserve quotations and delivery as later modules |
| [Lightspeed sales by item](https://shopkeep-support.lightspeedhq.com/hc/en-us/articles/47442968135067-Using-the-Sales-by-Item-report) | Net sales adjusts for returns and discounts; gross profit deducts sold item costs | Common report definitions and cost snapshots |
| [Lightspeed reporting measures](https://x-series-support.lightspeedhq.com/hc/en-us/articles/36918636674459-Understanding-Insights-and-advanced-reporting) | Cost of goods sold is based on cost at each sale, while current inventory cost may differ | Never recalculate old profit from today's product cost |
| [OWASP multi tenant security](https://cheatsheetseries.owasp.org/cheatsheets/Multi_Tenant_Security_Cheat_Sheet.html) | Tenant context must be server verified; isolation covers data, caches, files, jobs, and authorized admin access | Store boundary enforced across every access path |

Research date: October 31, 2026. The sources support the specific patterns listed above. No jurisdiction-specific legal conclusion is drawn from retail vendor documentation.

## Repository retrieval

The repository [will3452/hardware-store](https://github.com/will3452/hardware-store) was cloned to `/private/tmp/hardware-store-import`. The clone reported an empty repository, and the remote heads and tags query returned no references. There were no `.md` files to import at the time of the check.

The requested PRD and build specifications were therefore created as the initial documentation baseline for this repository.

## Change control

The PRD owns product scope and requirement IDs. Reporting owns metric formulas; access control owns permission semantics; the data model owns persistence constraints. When these conflict, resolve the decision here and update the affected documents in the same change. Do not implement divergent rules in each portal.

# Conflicts and unresolved questions

## Resolved for documentation organization

| Issue | Resolution and basis |
|---|---|
| Scaffold mandates 12 source modules; current app has 13 | Latest explicit user folder names govern code. Keep 12 sections as feature groupings and map their contents. |
| Old project guide has 12 shorthand module folders and folds parking into platform | Current map provides 13 exact names, including ParkingFacilities. Archived placeholders are not active guides. |
| Old docs say framework is unselected/uninstalled | Laravel already exists at root; legacy stack suggestions are historical. |
| Multiple entry points claim to be canonical | DOCS/00_START_HERE.md is the current entry point; references preserve detail and archive preserves superseded instructions. |
| Import script claims safe no-overwrite behavior | Its database/views/scaffold copying uses Force and can create more copies. Archived; not run. Sources are linked directly. |
| WebAR early versus late build sequence | Early feasibility experiments; integrate only after evidence. See build order. |

## Resolve before the affected feature or migration

| ID | Question / competing sources | Required action |
|---|---|---|
| C-01 | Functional scaffold assigns credentials to platform, products to shop operations and mall records to admin; ERD gives distinct data owners | Use current module routing for planning, then confirm a precise service/table mapping against ERD ownership before persistence work. Do not copy historical Mxx ownership into PHP namespaces. |
| C-02 | Catalogue includes orders/reservations/returns/rewards; ERD purchase scope is trusted completed-sale evidence | Agree the release slice and owning module/schema for those extensions. PurchaseVerification is not automatically a checkout domain. |
| C-03 | Catalogue broadly describes verified reviews; ERD allows verification only for product-listing reviews | Confirm entitlement policy before verified shop/mall reviews or per-purchase review uniqueness changes. Preserve existing baseline meanwhile. |
| C-04 | Broad parking/events/cases/leases/notifications requirements exceed the nine-domain ERD | Define records, ownership and integrations when requested; reuse existing FACILITY/map records where appropriate. No new table inferred here. |
| C-05 | Default Laravel User/auth/session files versus logical APP_USER/AUTH_SESSION | Decide physical mapping, authentication integration and migration strategy; avoid two account stores. |
| C-06 | Shared product definitions versus shop-owned catalogue curation | Confirm who creates/merges products and how shop listings are governed; see ERD open decisions. |
| C-07 | QR start point and requested WebAR versus unvalidated continuous localization | Select target devices and a measured alignment/tracking approach; preserve usable non-AR navigation. |
| C-08 | Nine-week roadmap and nine-month horizon, unfinished PRD, no acceptance matrix | Confirm milestone scope/dates and measurable criteria before claiming a delivery schedule. |
| C-09 | Forecasts, RAG, live parking and integrations lack final data/provider/evaluation choices | Record data provenance, permissions, baselines and prototype limits before implementation claims. |

Read [ERD open decisions](references/erd/DOC/03-database/OPEN-DECISIONS.md), [functional contracts](references/functional/DOC/integration/CONTRACTS.md), [full catalogue](references/functional/DOC/project/FEATURE_CATALOGUE.md) and [historical context](references/functional/DOC/project/PROJECT_CONTEXT.md) for details. Retention durations and additional schema assumptions remain open there.

These are targeted implementation questions, not reasons to stop unrelated work. Document explicit resolutions in DECISIONS.md before changing the affected semantics.

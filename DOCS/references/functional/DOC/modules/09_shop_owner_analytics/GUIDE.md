# M09 — Shop-owner analytics

Status: module boundary confirmed by the user's catalogue correction; application not implemented or verified here. Feature inclusion in this guide does not mean every optional feature is committed to the first release.

## Catalogue scope — preserved from attachment
- **Sales dashboard:** Show recorded revenue, orders, and average order value.
- **Product performance:** Identify popular, slow-moving, and frequently searched products.
- **Inventory reports:** Summarise stock levels and stock-outs.
- **Discovery analytics:** Track profile views, product views, and navigation requests.
- **Customer feedback trends:** Monitor ratings and recurring complaints.
- **Campaign performance:** Measure coupon use and purchases attributable to promotions where possible.
- **Sales forecasting:** Estimate future sales when sufficient historical data exists.
- **Report export:** Download periodic reports.

## Ownership and write boundaries
Own shop-scoped metric definitions, analytic views, sales forecast evaluation and exports. Read source records from M04/M05/M08 and permitted activity events. Scope every query/export to authorized shops.

## Internal components
- `sales/`
- `products_inventory/`
- `discovery/`
- `feedback/`
- `campaigns/`
- `sales_forecasting/`
- `exports/`

These are subcomponents within M09, not additional top-level modules. See [source folder](../../../modules/09_shop_owner_analytics/README.md).

## Proposed contract
ShopReport {shopId, period, metricVersion, sourceCutoff, metrics, coverage}; SalesForecast includes target, horizon, training cutoff, baseline error and test error.

## Dependencies
M04, M05, M08, M12

Resolve IDs through the [central connecting file](../../MODULE_CONNECTIONS.md). Dependencies represent service/data contracts, not permission to import another module's database internals.

## Implementation guidance
1. Read the catalogue scope and confirm the release slice; preserve deferred features in this guide.
2. Inspect existing source, then identify owned records versus records read from other modules.
3. Define runtime-validated contracts and authorization rules using the shared integration specification.
4. Implement one complete user journey across the required subcomponents, including failure and empty states.
5. Integrate with upstream owners through explicit commands/queries; update affected consumers when contracts change.
6. Run the checks below and record evidence. Keep external integrations mocked until real access and behavior have been validated, and label every mock.

## Completion checks
- [ ] Hand-calculated metrics match fixtures.
- [ ] Sales requires actual recorded transactions.
- [ ] Views and navigation requests are not visits.
- [ ] Exports preserve scope.
- [ ] Forecast uses chronological evaluation against a baseline..

## Progress and handoff
Record actual source changes, contract changes, verification and prototype limitations in [PROGRESS.md](../../planning/PROGRESS.md). The guide is a scope/implementation reference, not a completion claim.

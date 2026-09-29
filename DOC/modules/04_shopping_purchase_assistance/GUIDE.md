# M04 — Shopping and purchase assistance

Status: module boundary confirmed by the user's catalogue correction; application not implemented or verified here. Feature inclusion in this guide does not mean every optional feature is committed to the first release.

## Catalogue scope — preserved from attachment
- **Shopping lists:** Create a list of products to find.
- **Shop suggestions for a list:** Identify shops that carry the requested items.
- **Availability enquiries:** Ask a shop whether an item is available.
- **Reservations:** Reserve a product for an agreed period.
- **Click-and-collect orders:** Place an order and collect it at the shop.
- **Order tracking:** View confirmation, preparation, and collection status.
- **Digital receipts:** Keep purchase records in the account.
- **Return and exchange requests:** Submit requests according to each shop’s policy.
- **Coupons:** Save and redeem eligible offers.
- **Loyalty rewards:** Earn shop-specific or mall-wide points.
- **Payments:** Optionally support online payment and refunds.
- **Customer–shop messaging:** Communicate about products, reservations, or orders.

A shared cart containing products from multiple shops is a further extension: it also needs separate fulfilment, payment allocation, and refund handling for each shop.

## Ownership and write boundaries
Own shopping lists, enquiries, reservation/order lifecycle, purchase/receipt records, return/refund workflow and redemption/reward ledger when enabled. M08 operates these records through authorized commands. Payment adapters belong to M12; transaction rules remain here.

## Internal components
- `shopping_lists/`
- `enquiries_messaging/`
- `reservations_orders/`
- `receipts_returns/`
- `coupons_loyalty/`
- `payments/`

These are subcomponents within M04, not additional top-level modules. See [source folder](../../../modules/04_shopping_purchase_assistance/README.md).

## Proposed contract
Order {id, customerId, shopId, items, status, totals, paymentStatus}; Purchase {id, shopId, lineItems, evidenceSource}; enforce allowed state transitions. Multi-shop cart remains a later extension with distinct fulfilment/payment allocation.

## Dependencies
M01, M02, M08, M12

Resolve IDs through the [central connecting file](../../MODULE_CONNECTIONS.md). Dependencies represent service/data contracts, not permission to import another module's database internals.

## Implementation guidance
1. Read the catalogue scope and confirm the release slice; preserve deferred features in this guide.
2. Inspect existing source, then identify owned records versus records read from other modules.
3. Define runtime-validated contracts and authorization rules using the shared integration specification.
4. Implement one complete user journey across the required subcomponents, including failure and empty states.
5. Integrate with upstream owners through explicit commands/queries; update affected consumers when contracts change.
6. Run the checks below and record evidence. Keep external integrations mocked until real access and behavior have been validated, and label every mock.

## Completion checks
- [ ] Reservation expiry and concurrent stock claims are consistent.
- [ ] Order actions obey a state machine.
- [ ] Duplicate payment callbacks cannot double-charge/refund/award points.
- [ ] Refund outcomes reconcile.
- [ ] Mocked payments are visibly labelled..

## Progress and handoff
Record actual source changes, contract changes, verification and prototype limitations in [PROGRESS.md](../../planning/PROGRESS.md). The guide is a scope/implementation reference, not a completion claim.

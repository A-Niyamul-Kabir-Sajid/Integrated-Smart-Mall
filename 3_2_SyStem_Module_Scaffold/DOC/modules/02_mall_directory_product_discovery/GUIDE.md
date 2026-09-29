# M02 — Mall directory and product discovery

Status: module boundary confirmed by the user's catalogue correction; application not implemented or verified here. Feature inclusion in this guide does not mean every optional feature is committed to the first release.

## Catalogue scope — preserved from attachment
- **Shop directory:** List all shops by floor and category.
- **Shop profiles:** Show descriptions, opening hours, contact details, location, photos, and ratings.
- **Product search:** Find which shops sell a particular product.
- **Filters and sorting:** Filter by category, price, brand, rating, availability, and floor.
- **Product details:** Display descriptions, images, variants, prices, and availability.
- **Product comparison:** Compare similar products from different shops.
- **Service directory:** Find salons, repair centres, banking services, entertainment, and other services.
- **Facility search:** Locate washrooms, prayer rooms, ATMs, information desks, elevators, and exits.
- **Opening status:** Show whether shops and facilities are currently open.
- **Offers directory:** Browse active discounts and promotions.

## Ownership and write boundaries
Own public discovery queries, comparison and directory presentation. M08 owns shop/product edits and stock records; M10 owns approved mall/unit/facility records; M03 owns navigation coordinates. Read published source data.

## Internal components
- `directory/`
- `product_discovery/`
- `comparison/`
- `facilities/`
- `offers/`

These are subcomponents within M02, not additional top-level modules. See [source folder](../../../modules/02_mall_directory_product_discovery/README.md).

## Proposed contract
DiscoveryQuery {mallId, text, filters, sort, page}; result items carry canonical shop/product/facility IDs, published availability, rating count and destination reference where available.

## Dependencies
M03, M05, M08, M10, M12

Resolve IDs through the [central connecting file](../../MODULE_CONNECTIONS.md). Dependencies represent service/data contracts, not permission to import another module's database internals.

## Implementation guidance
1. Read the catalogue scope and confirm the release slice; preserve deferred features in this guide.
2. Inspect existing source, then identify owned records versus records read from other modules.
3. Define runtime-validated contracts and authorization rules using the shared integration specification.
4. Implement one complete user journey across the required subcomponents, including failure and empty states.
5. Integrate with upstream owners through explicit commands/queries; update affected consumers when contracts change.
6. Run the checks below and record evidence. Keep external integrations mocked until real access and behavior have been validated, and label every mock.

## Completion checks
- [ ] Filters and comparison use actual records.
- [ ] Unpublished data is hidden.
- [ ] Opening hours handle timezone.
- [ ] No-rating is distinct from zero.
- [ ] Stale availability is identified..

## Progress and handoff
Record actual source changes, contract changes, verification and prototype limitations in [PROGRESS.md](../../planning/PROGRESS.md). The guide is a scope/implementation reference, not a completion claim.

# M08 — Shop-owner operations

Status: module boundary confirmed by the user's catalogue correction; application not implemented or verified here. Feature inclusion in this guide does not mean every optional feature is committed to the first release.

## Catalogue scope — preserved from attachment
- **Shop onboarding:** Apply to join the platform and submit shop details.
- **Staff accounts:** Assign permissions to managers, sales staff, and catalogue editors.
- **Profile management:** Update opening hours, contacts, branding, and descriptions.
- **Catalogue management:** Add and edit products, variants, prices, and images.
- **Bulk catalogue import:** Upload many products at once.
- **Inventory management:** Maintain stock quantities and availability.
- **Low-stock alerts:** Identify products needing replenishment.
- **Order and reservation management:** Accept, reject, prepare, and complete requests.
- **Purchase registration:** Record purchases and issue verification credentials.
- **Receipt management:** Generate and retrieve digital receipts.
- **Returns and refunds:** Track requests and their outcomes.
- **Promotion management:** Create offers, coupons, and campaigns.
- **Customer support:** Respond to messages, complaints, and reviews.
- **Mall service requests:** Report maintenance or operational issues to mall management.
- **Mall notices and dues:** View announcements, rent, and service-charge records if these modules are included.

## Ownership and write boundaries
Own shop operational commands and catalog, inventory, promotions and shop-staff business records. Call M04 order/purchase/return services, M05 review/proof services, M10 tenant/service-request services and M12 permission services. Avoid duplicating those ledgers.

## Internal components
- `onboarding_staff/`
- `catalogue_inventory/`
- `order_fulfilment/`
- `purchase_registration/`
- `promotions/`
- `support/`
- `mall_requests/`

These are subcomponents within M08, not additional top-level modules. See [source folder](../../../modules/08_shop_owner_operations/README.md).

## Proposed contract
CatalogCommand {actorId, shopId, productData}; InventoryAdjustment {shopId, productId, delta, reason}; FulfilmentCommand {orderId, action}; proof issuance references an authorized recorded purchase.

## Dependencies
M04, M05, M10, M12

Resolve IDs through the [central connecting file](../../MODULE_CONNECTIONS.md). Dependencies represent service/data contracts, not permission to import another module's database internals.

## Implementation guidance
1. Read the catalogue scope and confirm the release slice; preserve deferred features in this guide.
2. Inspect existing source, then identify owned records versus records read from other modules.
3. Define runtime-validated contracts and authorization rules using the shared integration specification.
4. Implement one complete user journey across the required subcomponents, including failure and empty states.
5. Integrate with upstream owners through explicit commands/queries; update affected consumers when contracts change.
6. Run the checks below and record evidence. Keep external integrations mocked until real access and behavior have been validated, and label every mock.

## Completion checks
- [ ] Cross-shop writes fail.
- [ ] Bulk import errors are actionable.
- [ ] Inventory adjustments are auditable.
- [ ] Fulfilment uses the original order.
- [ ] Purchase records support token issuance.
- [ ] Operational notices and dues are permission-scoped..

## Progress and handoff
Record actual source changes, contract changes, verification and prototype limitations in [PROGRESS.md](../../planning/PROGRESS.md). The guide is a scope/implementation reference, not a completion claim.

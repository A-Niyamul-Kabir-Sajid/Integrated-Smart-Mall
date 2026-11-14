# ShopOwnerOperations

Shop-owner workspace: authorized shop workflows that call the owning domain modules.

- Namespace: `App\Modules\ShopOwnerOperations`
- Jira: EPIC-10 Shop Owner Operations
- Requirements, business rules and ERD: [DOCS/modules/ShopOwnerOperations/README.md](../../../DOCS/modules/ShopOwnerOperations/README.md)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

Implemented for EPIC-10. Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md) and the design in decision D-014 ([DOCS/DECISIONS.md](../../../DOCS/DECISIONS.md)).

- Owns (outside the ERD): shop applications, staff invitations and functions, stock and its ledger, orders, receipts, returns, promotions, support threads and maintenance requests.
- Orchestrates: `ShopService`, `MembershipService`, `CatalogueService`, `PurchaseService`, `VerificationCredentialService`; reads listings along foreign keys.
- Access: `work-in-shop` for the catalogue and review inbox; `manage-shop` for profile, hours and staff; `shop-area-stock`, `shop-area-orders`, `shop-area-promotions`, `shop-area-support` and `shop-area-maintenance` (work function plus an active shop) for the rest.
- Console: `php artisan orders:expire-overdue` (scheduled daily).

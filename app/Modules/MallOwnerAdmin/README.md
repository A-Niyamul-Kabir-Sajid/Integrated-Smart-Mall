# MallOwnerAdmin

Mall-owner workspace: tenant administration, map/QR administration, moderation and operations.

- Namespace: `App\Modules\MallOwnerAdmin`
- Jira: EPIC-11 Mall Owner Administration
- Requirements, business rules and ERD: [DOCS/modules/MallOwnerAdmin/README.md](../../../DOCS/modules/MallOwnerAdmin/README.md)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

Implemented for EPIC-11 except map and QR administration (waits for EPIC-05). Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md) and the design in decision D-015 ([DOCS/DECISIONS.md](../../../DOCS/DECISIONS.md)).

- Owns (outside the ERD): announcements, complaints with their history, work orders with their history.
- Orchestrates: `MallService`, `FloorService`, `ShopService`, `ShopCategoryService` (MallShopDirectory), `MembershipService` and `AccountLookupService` (AccountsAccess), `MaintenanceRequestService` (ShopOwnerOperations), `ModerationService` (ReviewsModeration), `AuditLogger` (SharedPlatform).
- Access: everything under `/admin/malls/{mall}` needs `administer-mall` for that mall; `/admin/categories` needs `administer-platform`; customers use `/account/complaints` and the public `/malls/{mall}/news`.

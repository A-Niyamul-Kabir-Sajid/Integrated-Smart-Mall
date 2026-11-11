# MallShopDirectory

Malls, floors, shop categories, shops, shop units, opening hours and facilities; customer discovery of shops.

- Namespace: `App\Modules\MallShopDirectory`
- Jira: EPIC-03 Mall & Shop Directory
- Requirements, business rules and ERD: [DOCS/modules/MallShopDirectory/README.md](../../../DOCS/modules/MallShopDirectory/README.md) (ERD domain 02)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

Implemented: data layer (3.2 to 3.5), customer directory, search and shop pages (3.6 to 3.8), contracts (3.9). Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md).

## Domain

```text
MALL 1──* FLOOR 1──* SHOP_UNIT *──1 SHOP *──1 SHOP_CATEGORY (parent: SHOP_CATEGORY, acyclic)
  │                                    │
  └──────────────* SHOP ───────────────┘── 1──* SHOP_OPENING_HOUR
FLOOR 1──* FACILITY
```

| Entity (ERD) | Table | Key rules |
|---|---|---|
| MALL | `malls` | Unique `slug`; IANA `timezone` (e.g. Asia/Dhaka); status draft, active or archived |
| FLOOR | `floors` | Unique `(mall_id, code)`; `sort_order` is display order only; status active or archived |
| SHOP_CATEGORY | `shop_categories` | Unique `slug`; optional parent; the parent chain must stay acyclic |
| SHOP | `shops` | One business in one mall; unique `(mall_id, slug)`; status draft, active, closed or archived |
| SHOP_UNIT | `shop_units` | Unique `(floor_id, unit_code)`; the floor must belong to the shop's mall; positive area when given |
| SHOP_OPENING_HOUR | `shop_opening_hours` | Key `(shop_id, weekday, interval_no)`; ISO weekday 1–7; intervals on a day never overlap; no interval means closed; overnight via `closes_next_day`; equal times with `closes_next_day` mean 24 hours |
| FACILITY | `facilities` | Washroom, prayer room, information desk, exit, ATM, lift or other on one active floor, with an optional location note (D-013); written by `FacilityService`, screens in ParkingFacilities (EPIC-09) |

Rules a foreign key cannot express (unit floor in the shop's mall, acyclic categories, non-overlapping intervals) are enforced by this module's services and reported with `BusinessRuleViolation`.

### Lifecycle

- Customers see only **active** malls and **active or closed** shops; drafts are visible to their owners and mall administrators only.
- Archive instead of delete: archived shops, units, floors and malls stay referenced by listings, reviews and history.
- Opening hours are local times interpreted in the mall's timezone; holiday exceptions are not part of the baseline.

## Provided to other modules

| Consumer | Needs from the directory |
|---|---|
| AccountsAccess | `mall_id` for MALL_MEMBERSHIP, `shop_id` for SHOP_MEMBERSHIP |
| ProductsCatalogue | `shop_id` for SHOP_PRODUCT; whether a shop is active (only active shops sell) |
| MapsNavigation | `floor_id` for floor maps and nodes, `shop_unit_id` for SHOP_ENTRANCE, `facility_id` for FACILITY_ANCHOR, the mall boundary for map versions |
| ShopOwnerOperations, MallOwnerAdmin | Shop profile, units, hours and status changes through this module's services |
| ParkingFacilities | Facilities and floors |

Reads go through `Contracts\DirectoryLookup` (bound to `Services\DirectoryLookupService`):

| Method | Promise | Used by |
|---|---|---|
| `activeMallBySlug(slug)` | Active malls only; null otherwise | All public pages |
| `shop(id)` | Any status | ShopOwnerOperations, MallOwnerAdmin |
| `isShopOperational(id)` | True only for active shops | ProductsCatalogue (who may sell) |
| `listedShopIds(mallId)` | Active and closed shops of the mall | ProductsCatalogue (public search scope) |
| `floorsOf(mallId)` | Active floors in display order | MapsNavigation |
| `locationsOf(shopId)` | Non-archived units with their floor loaded | MapsNavigation (entrances) |

Writes go through `ShopService`, `MallService`, `FloorService` and `FacilityService` (which also finds customer-visible facilities by type, floor, step-free and open status). `ShopStatusChanged` is dispatched after commit whenever a shop changes status.

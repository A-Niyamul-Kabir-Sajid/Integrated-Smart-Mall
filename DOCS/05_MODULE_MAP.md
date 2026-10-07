# Application module map

Exact names match `app/Modules/`. Catalogue references use their original 12-section numbering; ERD references use a separate nine-domain numbering. A referenced ERD is not automatically owned by the consuming application module.

| Application module | Responsibility | Catalogue sections | ERD reference domains |
|---|---|---|---|---|
| [AccountsAccess](modules/AccountsAccess/README.md) | Identity, customer accounts, profiles and scoped access | 01, 12 | 01 |
| [MallShopDirectory](modules/MallShopDirectory/README.md) | Malls, shops, floors, categories and discovery | 02, 10 | 02 |
| [ProductsCatalogue](modules/ProductsCatalogue/README.md) | Shared products, variants, shop listings and availability | 02, 08 | 03 |
| [MapsNavigation](modules/MapsNavigation/README.md) | Versioned maps, checkpoints, graph routing and AR coordination | 03, 10 | 04 |
| [PurchaseVerification](modules/PurchaseVerification/README.md) | Trusted purchase evidence, credentials and redemption | 04, 05, 08 | 05 |
| [ReviewsModeration](modules/ReviewsModeration/README.md) | Reviews, ratings, evidence binding, replies and moderation | 05 | 06 |
| [FavouritesPreferences](modules/FavouritesPreferences/README.md) | Saved shop/listing favourites and user preferences | 01 | 07 |
| [ChatbotRag](modules/ChatbotRag/README.md) | Customer retrieval, recommendations and authorized assistant integration | 06, 11 | 08 |
| [AnalyticsReports](modules/AnalyticsReports/README.md) | Shared metrics, shop/mall reports and evaluated forecasts | 09, 11 | 09 |
| [ParkingFacilities](modules/ParkingFacilities/README.md) | Saved parking, visitor support, events and facility-facing flows | 07 | 02, 04 |
| [ShopOwnerOperations](modules/ShopOwnerOperations/README.md) | Authorized shop workflows calling the owning domains | 08, 04, 09 | 01, 02, 03, 05, 06, 09 |
| [MallOwnerAdmin](modules/MallOwnerAdmin/README.md) | Tenant/admin workflows, maps, moderation and mall operations | 10, 11 | 01, 02, 04, 06, 09 |
| [SharedPlatform](modules/SharedPlatform/README.md) | Notifications, audit, integrations, localization and shared infrastructure | 12 | 01 |

## Ownership rules

ShopOwnerOperations and MallOwnerAdmin orchestrate services owned by the data modules. ParkingFacilities reuses directory facilities and navigation destinations; additional cases/events/parking records need explicit design. SharedPlatform does not become a second account store.

The full shopping/order catalogue has no separate application folder in the requested structure. Its future ownership is open (C-02), not silently assigned to PurchaseVerification. See [conflicts](CONFLICTS.md) before those features.

Module README pages link to the original guides and business rules instead of duplicating their contents.

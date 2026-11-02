# Module contracts and dependency rules

How modules talk to each other. Read with [architecture](03_ARCHITECTURE.md), [API contracts](07_API_CONTRACTS.md) and the folder layout in [app/Modules/README.md](../app/Modules/README.md).

## Dependency direction

```text
            Core  <-- every module may use Core; Core uses no module
              ^
       SharedPlatform  <-- notifications, audit, localization; used by all modules
              ^
  Data-owning modules  (AccountsAccess, MallShopDirectory, ProductsCatalogue, MapsNavigation,
              ^         PurchaseVerification, ReviewsModeration, FavouritesPreferences,
              |         ParkingFacilities, AnalyticsReports, ChatbotRag)
  Orchestrating modules (ShopOwnerOperations, MallOwnerAdmin)
```

- Orchestrating modules call the services of the data-owning modules; data-owning modules never depend on an orchestrating module.
- Between data-owning modules, dependencies follow foreign keys in the ERD: a module may reference a module whose table it points at (ProductsCatalogue → MallShopDirectory for `shop_id`), not the other way round. React to the other direction with events.
- Cycles are not allowed. If two modules need each other, one of them exposes a contract or dispatches an event.

The architecture tests enforce the two mechanical rules: no module references another module's `Http\` namespace, and no data-owning module references `ShopOwnerOperations` or `MallOwnerAdmin`.

## What another module may use

| Need | Allowed | Not allowed |
|---|---|---|
| Read data owned by another module | Its `Contracts\` interface or a `Services\` query method; Eloquent relationships along an ERD foreign key | Raw queries on its tables from elsewhere |
| Change data owned by another module | A method on the owner's service or contract (it validates and enforces business rules) | `Model::create()`, `->update()`, `->delete()` on another module's model |
| React to something that happened | Listen to the owner's event | Polling, or the owner calling the reacting module |
| Show a page or return JSON | Its own controller | Calling another module's controller, Form Request or middleware |

## Naming

| Element | Rule | Example |
|---|---|---|
| Service | `<Noun>Service`, methods start with a verb | `DirectoryService::listShops()`, `PurchaseService::registerPurchase()` |
| Contract | Interface named for the capability, in `Contracts/`, bound in the module provider | `DirectoryLookup` → `DirectoryService` |
| DTO | `final readonly class <Name>Data` in the module's `DTOs/`; constructor-promoted typed properties, no behaviour | `RouteRequestData(startNode, destinationNode, avoidStairs)` |
| Event | Past tense, in `Events/`, payload of identifiers and small scalars | `ShopStatusChanged(shopId, from, to)` |
| Listener | `<Verb><Object>` in the consuming module's `Listeners/`, registered in that module's provider | `RecordShopStatusAudit` |
| Business rule code | `<area>.<rule>` in snake case, thrown with `BusinessRuleViolation` | `shop_unit.floor_in_other_mall` |

## Repositories

Services query Eloquent models directly by default. Add a repository in the owning module's `Repositories/` only when a query is complex and reused by several services, or when the data has several sources (database plus an external system), as listed in [design patterns](04_DESIGN_PATTERNS.md). A repository never crosses module ownership: it returns only the owning module's models.

## Events

- Dispatch domain events from services, never from controllers or models.
- Events raised inside a database transaction implement `ShouldDispatchAfterCommit`, so listeners never see rolled-back data.
- Carry identifiers, not models, when the listener may be queued. Listeners reload what they need.
- A listener must not be required for the dispatching workflow to succeed. If something must happen atomically, it belongs in the owner's transaction, not in a listener.

## Examples

Reading another module's data through its contract:

```php
final class ProductSearchService
{
    public function __construct(private DirectoryLookup $directory) {}

    public function search(string $mallSlug, string $term): Collection
    {
        $mall = $this->directory->activeMallBySlug($mallSlug); // MallShopDirectory contract
        // ...
    }
}
```

Changing another module's data through its service, from an orchestrating module:

```php
// ShopOwnerOperations controller
public function update(UpdateListingRequest $request, ShopProduct $listing, ListingService $listings): RedirectResponse
{
    $listings->updatePrice($listing, $request->validated('price_amount')); // ProductsCatalogue owns the write
    return back();
}
```

Reacting with an event:

```php
// MallShopDirectory service (the owner dispatches)
ShopStatusChanged::dispatch($shop->shop_id, $previous, $shop->status);

// ShopOwnerOperations provider boot() (an orchestrating module reacts; dependency points at the owner)
Event::listen(ShopStatusChanged::class, NotifyShopStaffOfStatusChange::class);
```

# FavouritesPreferences

Saved shop and listing favourites, shopping lists and user preferences.

- Namespace: `App\Modules\FavouritesPreferences`
- Jira: EPIC-08 Favourites & Preferences
- Requirements, business rules and ERD: [DOCS/modules/FavouritesPreferences/README.md](../../../DOCS/modules/FavouritesPreferences/README.md)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

Implemented (EPIC-08). Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md).

## Owns

| Table | Source | Written by |
|---|---|---|
| `user_preferences` | ERD USER_PREFERENCE plus D-010 notification columns | `PreferenceService` |
| `favourite_shops`, `favourite_products` | ERD FAVOURITE_SHOP / FAVOURITE_PRODUCT | `FavouriteService` |
| `shopping_lists`, `shopping_list_items` | D-012 (not in the ERD) | `ShoppingListService` |

## Entry points

| Route | Who | Does |
|---|---|---|
| `GET/PUT /account/preferences` | signed in | language, usual mall, route mode, notifications |
| `GET /account/saved` | signed in | saved shops and offers, unavailable ones marked |
| `POST/DELETE /favourites/shops/{shop}`, `/favourites/listings/{listing}` | signed in, `throttle:favourites` | save or remove (idempotent; 404 for hidden targets) |
| `GET/POST /account/lists`, `GET/PUT/DELETE /account/lists/{list}` | signed in (owner only, else 404) | shopping lists of one mall each |
| `POST /account/lists/{list}/items`, `PATCH/DELETE .../items/{item}` | owner only | add (product or free text), tick off, change, remove |

## Contracts

- `Contracts\PreferenceLookup::preferencesFor(?userId): PreferenceData` (bound to `PreferenceService`): locale, route mode, usual mall, AR and personalisation; defaults for guests. `needsStepFreeRoutes()` is meant for MapsNavigation.
- `SharedPlatform\Contracts\NotificationPreferences` is implemented by `UserNotificationPreferences`.

## Pages of other modules

View composers in the provider add data to other modules' pages, so those modules stay independent: save buttons on `customer.shops.show` and `customer.products.show`, the "Add to a shopping list" box on product pages, and the usual mall first on `customer.malls.index`.

## Rules

- Only shops customers can see (active or closed, in an active mall) and visible listings can be saved; saved items that later disappear stay listed as "No longer available".
- A shopping list belongs to one mall; only products that mall sells can be added; repeats merge into one line; at most 20 lists, 100 lines and quantity 99.
- Every list, line and favourite is scoped to its account; another account's list answers 404.

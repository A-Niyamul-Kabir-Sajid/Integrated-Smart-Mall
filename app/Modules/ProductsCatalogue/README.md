# ProductsCatalogue

Shared products and variants, shop-specific listings, prices and availability.

- Namespace: `App\Modules\ProductsCatalogue`
- Jira: EPIC-04 Products Catalogue
- Requirements, business rules and ERD: [DOCS/modules/ProductsCatalogue/README.md](../../../DOCS/modules/ProductsCatalogue/README.md) (ERD domain 03)
- Ownership decision: [D-011](../../../DOCS/DECISIONS.md) (shared catalogue with guarded edits)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

Domain reviewed (ISM-49). Data layer, shop catalogue management and customer discovery follow in ISM-50 to ISM-56. Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md).

## Domain

```text
PRODUCT_CATEGORY (parent: PRODUCT_CATEGORY, acyclic) 1──* PRODUCT 1──* PRODUCT_VARIANT 1──* SHOP_PRODUCT *──1 SHOP (MallShopDirectory)
                                                      PRODUCT 1──* PRODUCT_IMAGE
```

| Entity (ERD) | Table | Key rules |
|---|---|---|
| PRODUCT_CATEGORY | `product_categories` | Unique slug; optional parent; acyclic (distinct from shop categories) |
| PRODUCT | `products` | Shared definition sold by many shops; name and brand are not unique; active or archived |
| PRODUCT_VARIANT | `product_variants` | Unique `(product_id, variant_code)`; `default` when a product has no real variants; optional unique barcode; JSON display attributes |
| SHOP_PRODUCT (listing) | `shop_products` | One shop's offer for one variant: unique `(shop_id, variant_id)` and `(shop_id, seller_sku)`; price ≥ 0 as fixed decimal; currency (BDT); availability available/unavailable/unknown; active or archived |
| PRODUCT_IMAGE | `product_images` | Ordered images of a shared product, unique `(product_id, sort_order)`, with alt text |
| INVENTORY_BALANCE | — | Optional in the ERD; not built. Availability is owner-maintained (no stock ledger) |

### Ownership and edit rules (D-011)

- A shop owns its listings only: price, SKU, availability and archive state of its own `shop_products` rows. A listing never moves to another shop.
- Shared product and variant details (name, brand, description, category, images, variants) may be edited by a shop only while it is the **sole** shop listing that product; otherwise only the mall administrator curates them.
- Only **operational** (active) shops may hold active listings (`DirectoryLookup::isShopOperational`). Customers see listings of active products and variants in their mall's operational shops.
- Archive instead of delete, so reviews, favourites and purchase history keep their references.
- Who may act: `work-in-shop` (owners and staff) for the shop's catalogue; `administer-mall` for shared curation.

### Downstream needs

| Consumer | Needs |
|---|---|
| FavouritesPreferences | A listing by id (seller context preserved) |
| MapsNavigation | The shop of a listing, for the "directions" hand-off |
| ChatbotRag | Structured listing search (category, price range, availability, shop) instead of free text |
| ReviewsModeration, PurchaseVerification | Stable `shop_product_id` references |

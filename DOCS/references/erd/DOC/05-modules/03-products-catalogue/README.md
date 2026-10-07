# Module 03 — Products and Catalogue

Shared products and variants, shop listings, images, and optional inventory.

Status: proposed logical design v1.0; not an implemented or supervisor-approved database.

## Files

- [Full Mermaid source](ERD.mmd) and [Markdown rendering](ERD.md).
- [Data dictionary](DATA-DICTIONARY.md): all attributes, keys, nullability, references, and indexes.
- [Business rules](BUSINESS-RULES.md): constraints and lifecycle decisions.
- `ERD.svg`: ready-to-open vector preview.
- `views/`: smaller diagrams for detailed reading where supplied.

## Owned tables

| Table | Scope | Purpose |
| --- | --- | --- |
| PRODUCT_CATEGORY | Core | Searchable product taxonomy; distinct from shop categories. |
| PRODUCT | Core | Shared product definition that can be sold by multiple shops. |
| PRODUCT_VARIANT | Core | A purchasable version such as size, volume, or colour. |
| SHOP_PRODUCT | Core | A shop-specific listing and price for one product variant. |
| PRODUCT_IMAGE | Core | Shared product images ordered for presentation. |
| INVENTORY_BALANCE | Optional | Optional latest inventory snapshot for a listing. |

## External references

| Referenced table | Owner |
| --- | --- |
| SHOP | Mall and Shop Directory |

## Design explanation

PRODUCT is the shared description, PRODUCT_VARIANT identifies size/colour/volume, and SHOP_PRODUCT is the actual listing with seller, price, and availability. Even a product without variants gets one default variant. The same variant may be offered by several shops at different prices. Reviews, purchases, recommendations, and favourites reference SHOP_PRODUCT so the selling shop is never ambiguous.

INVENTORY_BALANCE is optional and records a snapshot only. Owner-maintained available/unavailable status is sufficient for the initial catalogue. No order basket, payment, delivery, inventory movement, reservation lifecycle, or bargaining workflow is claimed here. Prices use fixed decimal arithmetic; freeze currency for a listing once purchases exist, or create a new listing when changing currency.

## Update rule

Update this module, its cross-module contract, and the master diagram in the same change. Existing parent tables are referenced, never copied into this module. All current proposals and unresolved choices are listed in the shared design notes.

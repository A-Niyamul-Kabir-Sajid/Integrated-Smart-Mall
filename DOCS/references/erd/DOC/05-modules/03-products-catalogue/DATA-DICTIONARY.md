# Products and Catalogue — data dictionary

All fields are required unless Nullable is Yes. Types are logical; see [shared conventions](../../03-database/CONVENTIONS.md) before generating SQL. PK, FK, and UK mean primary key, foreign key, and single-column unique key. Multiple PK fields form one composite primary key.

## PRODUCT_CATEGORY

Searchable product taxonomy; distinct from shop categories.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| product_category_id | uuid | PK | No | — | Product category identifier. |
| parent_category_id | uuid | FK | Yes | PRODUCT_CATEGORY.product_category_id | Optional parent category. |
| slug | varchar(80) | UK | No | — | Unique category slug. |
| name | varchar(100) | — | No | — | Category name, such as Drinks. |

Primary key: `(product_category_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## PRODUCT

Shared product definition that can be sold by multiple shops.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| product_id | uuid | PK | No | — | Shared product identifier. |
| product_category_id | uuid | FK | No | PRODUCT_CATEGORY.product_category_id | Primary category. |
| name | varchar(160) | — | No | — | Product name. |
| brand | varchar(100) | — | Yes | — | Brand when known. |
| description | text | — | Yes | — | Product description. |
| status | varchar(16) | — | No | — | active or archived. |
| created_at | timestamptz | — | No | — | Creation time. |

Primary key: `(product_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## PRODUCT_VARIANT

A purchasable version such as size, volume, or colour.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| variant_id | uuid | PK | No | — | Variant identifier. |
| product_id | uuid | FK | No | PRODUCT.product_id | Parent product. |
| variant_code | varchar(60) | — | No | — | Code unique within the product; use default when no variants exist. |
| label | varchar(120) | — | No | — | Example 500 ml bottle. |
| attributes_json | json | — | No | — | Display attributes; important searchable fields may later be normalized. |
| barcode | varchar(80) | UK | Yes | — | Optional verified global barcode. |
| status | varchar(16) | — | No | — | active or archived. |

Primary key: `(variant_id)`.

Additional unique keys: `(product_id, variant_code)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## SHOP_PRODUCT

A shop-specific listing and price for one product variant.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| shop_product_id | uuid | PK | No | — | Listing identifier used by reviews and favourites. |
| shop_id | uuid | FK | No | SHOP.shop_id | Selling shop. |
| variant_id | uuid | FK | No | PRODUCT_VARIANT.variant_id | Listed variant. |
| seller_sku | varchar(80) | — | No | — | Shop-assigned SKU. |
| price_amount | decimal | — | No | — | Current advertised unit price. |
| currency | char(3) | — | No | — | ISO currency code; initial proposal BDT. |
| availability_status | varchar(20) | — | No | — | available, unavailable, or unknown. |
| status | varchar(16) | — | No | — | active or archived. |
| updated_at | timestamptz | — | No | — | Last listing update. |

Primary key: `(shop_product_id)`.

Additional unique keys: `(shop_id, variant_id)`; `(shop_id, seller_sku)`; `(shop_product_id, shop_id)`.

Suggested query indexes: `(shop_id, status)`; `(variant_id, status)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## PRODUCT_IMAGE

Shared product images ordered for presentation.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| product_image_id | uuid | PK | No | — | Image record identifier. |
| product_id | uuid | FK | No | PRODUCT.product_id | Product depicted. |
| object_key | text | — | No | — | Managed image storage location, not an expiring signed URL. |
| alt_text | varchar(200) | — | No | — | Accessible description. |
| sort_order | integer | — | No | — | Display position. |

Primary key: `(product_image_id)`.

Additional unique keys: `(product_id, sort_order)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## INVENTORY_BALANCE

Optional latest inventory snapshot for a listing.

Scope: **Optional**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| shop_product_id | uuid | PK, FK | No | SHOP_PRODUCT.shop_product_id | Listing whose balance is tracked. |
| quantity_on_hand | integer | — | No | — | Observed units in stock. |
| quantity_reserved | integer | — | No | — | Units unavailable due to reservations. |
| observed_at | timestamptz | — | No | — | Time of the stock observation. |
| source | varchar(20) | — | No | — | manual, imported, or ledger. |

Primary key: `(shop_product_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

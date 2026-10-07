# Purchase Verification — data dictionary

All fields are required unless Nullable is Yes. Types are logical; see [shared conventions](../../03-database/CONVENTIONS.md) before generating SQL. PK, FK, and UK mean primary key, foreign key, and single-column unique key. Multiple PK fields form one composite primary key.

## PURCHASE

Recorded sale used as evidence; not a complete checkout/payment system.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| purchase_id | uuid | PK | No | — | Purchase identifier. |
| shop_id | uuid | FK | No | SHOP.shop_id | Shop that recorded the sale. |
| buyer_user_id | uuid | FK | Yes | APP_USER.user_id | Known buyer or null for a walk-in purchase. |
| external_receipt_ref | varchar(120) | — | No | — | Shop receipt reference for idempotent import/entry. |
| status | varchar(20) | — | No | — | draft, completed, voided, or refunded. |
| currency | char(3) | — | No | — | Sale currency. |
| total_amount | decimal | — | No | — | Final sale amount after line discounts; proposed baseline excludes separate tax/shipping. |
| purchased_at | timestamptz | — | No | — | Sale time. |
| recorded_at | timestamptz | — | No | — | Database entry time. |

Primary key: `(purchase_id)`.

Additional unique keys: `(shop_id, external_receipt_ref)`.

Suggested query indexes: `(shop_id, purchased_at)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## PURCHASE_ITEM

Immutable record of the product listing and amount bought.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| purchase_item_id | uuid | PK | No | — | Line identifier. |
| purchase_id | uuid | FK | No | PURCHASE.purchase_id | Owning sale. |
| line_no | integer | — | No | — | Receipt line sequence. |
| shop_product_id | uuid | FK | No | SHOP_PRODUCT.shop_product_id | Purchased listing. |
| product_label_snapshot | varchar(200) | — | No | — | Name/variant at purchase time. |
| quantity | integer | — | No | — | Purchased units. |
| unit_price_amount | decimal | — | No | — | Unit price at purchase time. |
| line_discount_amount | decimal | — | No | — | Total discount applied to this line. |
| line_total_amount | decimal | — | No | — | quantity times unit price minus line discount. |

Primary key: `(purchase_item_id)`.

Additional unique keys: `(purchase_id, line_no)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## VERIFICATION_TOKEN

One rotatable opaque review credential per purchase line.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| verification_token_id | uuid | PK | No | — | Credential record identifier. |
| purchase_item_id | uuid | FK, UK | No | PURCHASE_ITEM.purchase_item_id | Line eligible for one verified product review. |
| token_digest | varchar(128) | UK | No | — | Digest of a cryptographically random secret; never the raw token. |
| issued_by_user_id | uuid | FK | No | APP_USER.user_id | Authorized shop issuer. |
| issued_at | timestamptz | — | No | — | Current token issuance time. |
| expires_at | timestamptz | — | No | — | Token claim deadline. |
| revoked_at | timestamptz | — | Yes | — | Explicit invalidation time. |

Primary key: `(verification_token_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## TOKEN_REDEMPTION

Atomic, single successful claim of a purchase token by a user.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| verification_token_id | uuid | PK, FK | No | VERIFICATION_TOKEN.verification_token_id | Claimed token; primary key prevents second redemption. |
| user_id | uuid | FK | No | APP_USER.user_id | Account that claimed purchase evidence. |
| redeemed_at | timestamptz | — | No | — | Successful claim time. |

Primary key: `(verification_token_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

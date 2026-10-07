# Favourites and Preferences — data dictionary

All fields are required unless Nullable is Yes. Types are logical; see [shared conventions](../../03-database/CONVENTIONS.md) before generating SQL. PK, FK, and UK mean primary key, foreign key, and single-column unique key. Multiple PK fields form one composite primary key.

## USER_PREFERENCE

Optional one-to-one user settings record.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| user_id | uuid | PK, FK | No | APP_USER.user_id | Account whose preferences are stored. |
| default_mall_id | uuid | FK | Yes | MALL.mall_id | Preferred mall. |
| locale | varchar(20) | — | No | — | Language tag, such as en or bn. |
| route_mode | varchar(20) | — | No | — | shortest_distance, fastest, or accessible_distance. |
| ar_preferred | boolean | — | No | — | Preferred UI mode, subject to capability and consent. |
| personalization_opt_in | boolean | — | No | — | Whether preference/history-based suggestions are allowed. |
| updated_at | timestamptz | — | No | — | Last change time. |

Primary key: `(user_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## FAVOURITE_SHOP

A shop saved by a user.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| user_id | uuid | PK, FK | No | APP_USER.user_id | Saving account. |
| shop_id | uuid | PK, FK | No | SHOP.shop_id | Saved shop. |
| created_at | timestamptz | — | No | — | Save time. |

Primary key: `(user_id, shop_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## FAVOURITE_PRODUCT

A shop listing saved by a user, preserving seller context.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| user_id | uuid | PK, FK | No | APP_USER.user_id | Saving account. |
| shop_product_id | uuid | PK, FK | No | SHOP_PRODUCT.shop_product_id | Saved listing. |
| created_at | timestamptz | — | No | — | Save time. |

Primary key: `(user_id, shop_product_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

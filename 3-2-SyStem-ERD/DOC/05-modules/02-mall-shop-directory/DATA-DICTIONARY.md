# Mall and Shop Directory — data dictionary

All fields are required unless Nullable is Yes. Types are logical; see [shared conventions](../../03-database/CONVENTIONS.md) before generating SQL. PK, FK, and UK mean primary key, foreign key, and single-column unique key. Multiple PK fields form one composite primary key.

## MALL

A mall boundary used for directory, map, and permission ownership.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| mall_id | uuid | PK | No | — | Mall identifier. |
| slug | varchar(100) | UK | No | — | Stable public URL slug. |
| name | varchar(160) | — | No | — | Mall name. |
| address | text | — | No | — | Street address. |
| city | varchar(100) | — | No | — | City. |
| timezone | varchar(60) | — | No | — | IANA zone, for example Asia/Dhaka. |
| status | varchar(16) | — | No | — | draft, active, or archived. |
| created_at | timestamptz | — | No | — | Record creation time. |

Primary key: `(mall_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## FLOOR

Physical level of a mall, independent of any map version.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| floor_id | uuid | PK | No | — | Floor identifier. |
| mall_id | uuid | FK | No | MALL.mall_id | Owning mall. |
| code | varchar(20) | — | No | — | Stable code such as B1 or F2. |
| name | varchar(80) | — | No | — | Display name. |
| sort_order | integer | — | No | — | Display order, not a routing cost. |
| elevation_m | decimal | — | No | — | Elevation relative to the mall origin, in metres. |
| status | varchar(16) | — | No | — | active or archived. |

Primary key: `(floor_id)`.

Additional unique keys: `(mall_id, code)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## SHOP_CATEGORY

Shop classification such as food, clothing, or electronics.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| shop_category_id | uuid | PK | No | — | Category identifier. |
| parent_category_id | uuid | FK | Yes | SHOP_CATEGORY.shop_category_id | Optional parent category. |
| slug | varchar(80) | UK | No | — | Unique category slug. |
| name | varchar(100) | — | No | — | Category display name. |

Primary key: `(shop_category_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## SHOP

One shop business in one mall; locations are represented separately.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| shop_id | uuid | PK | No | — | Shop identifier. |
| mall_id | uuid | FK | No | MALL.mall_id | Owning mall. |
| shop_category_id | uuid | FK | No | SHOP_CATEGORY.shop_category_id | Main shop category. |
| slug | varchar(100) | — | No | — | URL slug unique within its mall. |
| name | varchar(160) | — | No | — | Shop name. |
| description | text | — | Yes | — | Public description. |
| contact_phone | varchar(24) | — | Yes | — | Public business contact. |
| status | varchar(16) | — | No | — | draft, active, closed, or archived. |
| created_at | timestamptz | — | No | — | Record creation time. |
| updated_at | timestamptz | — | No | — | Last directory update. |

Primary key: `(shop_id)`.

Additional unique keys: `(mall_id, slug)`.

Suggested query indexes: `(mall_id, status, shop_category_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## SHOP_UNIT

A physical unit occupied by a shop; a shop can occupy several floors.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| shop_unit_id | uuid | PK | No | — | Location identifier. |
| shop_id | uuid | FK | No | SHOP.shop_id | Occupying shop. |
| floor_id | uuid | FK | No | FLOOR.floor_id | Physical floor. |
| unit_code | varchar(40) | — | No | — | Physical unit code, unique on its floor. |
| area_sq_m | decimal | — | Yes | — | Optional area in square metres. |
| status | varchar(16) | — | No | — | open, temporarily_closed, or archived. |

Primary key: `(shop_unit_id)`.

Additional unique keys: `(floor_id, unit_code)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## SHOP_OPENING_HOUR

Weekly opening intervals, including split opening periods.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| shop_id | uuid | PK, FK | No | SHOP.shop_id | Shop whose hours are recorded. |
| weekday | smallint | PK | No | — | ISO weekday: Monday 1 through Sunday 7. |
| interval_no | smallint | PK | No | — | Interval sequence within the weekday. |
| opens_at | time | — | No | — | Opening time in the mall timezone. |
| closes_at | time | — | No | — | Closing time in the mall timezone. |
| closes_next_day | boolean | — | No | — | True for an overnight interval. |

Primary key: `(shop_id, weekday, interval_no)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## FACILITY

Public destinations such as toilets, information desks, or prayer rooms.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| facility_id | uuid | PK | No | — | Facility identifier. |
| floor_id | uuid | FK | No | FLOOR.floor_id | Physical floor. |
| name | varchar(120) | — | No | — | Public name. |
| facility_type | varchar(32) | — | No | — | toilet, prayer_room, information, exit, or other. |
| is_accessible | boolean | — | No | — | Whether the destination itself is accessible. |
| status | varchar(16) | — | No | — | open, temporarily_closed, or archived. |

Primary key: `(facility_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

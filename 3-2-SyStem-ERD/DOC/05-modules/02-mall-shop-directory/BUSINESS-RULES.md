# Mall and Shop Directory — business rules

These rules are part of the design. An ER diagram alone does not enforce them. Use [constraint enforcement](../../03-database/CONSTRAINT-ENFORCEMENT.md) to distinguish database constraints from transactional/application checks.

## MALL

- Apply declared keys, foreign keys, nullability, and field meanings.

## FLOOR

- Unique tuple: `(mall_id, code)`.
- Apply declared keys, foreign keys, nullability, and field meanings.

## SHOP_CATEGORY

- The parent hierarchy must be acyclic; a category cannot be its own parent.

## SHOP

- Unique tuple: `(mall_id, slug)`.
- Apply declared keys, foreign keys, nullability, and field meanings.

## SHOP_UNIT

- Unique tuple: `(floor_id, unit_code)`.
- SHOP.mall_id must equal FLOOR.mall_id.
- area_sq_m is positive when present. This baseline stores current occupancy; tenant history needs a separate lease model.

## SHOP_OPENING_HOUR

- Composite primary key (shop_id, weekday, interval_no). weekday between 1 and 7; interval_no > 0.
- Intervals cannot overlap; no intervals on a day means closed. A 24-hour interval uses equal times and closes_next_day=true.
- Holiday exceptions are outside this baseline and must not be inferred from weekly hours.

## FACILITY

- Apply declared keys, foreign keys, nullability, and field meanings.

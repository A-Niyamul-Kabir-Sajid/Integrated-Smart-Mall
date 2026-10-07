# Favourites and Preferences — business rules

These rules are part of the design. An ER diagram alone does not enforce them. Use [constraint enforcement](../../03-database/CONSTRAINT-ENFORCEMENT.md) to distinguish database constraints from transactional/application checks.

## USER_PREFERENCE

- Missing row means application defaults. Guest preferences can remain local to the device.

## FAVOURITE_SHOP

- Composite primary key (user_id, shop_id) prevents duplicates.

## FAVOURITE_PRODUCT

- Composite primary key (user_id, shop_product_id) prevents duplicates.

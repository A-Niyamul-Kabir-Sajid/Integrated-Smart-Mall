# Products and Catalogue — business rules

These rules are part of the design. An ER diagram alone does not enforce them. Use [constraint enforcement](../../03-database/CONSTRAINT-ENFORCEMENT.md) to distinguish database constraints from transactional/application checks.

## PRODUCT_CATEGORY

- Parent links form an acyclic hierarchy.

## PRODUCT

- Brand/name are not assumed globally unique. Establish who may create or merge shared products.

## PRODUCT_VARIANT

- Unique tuple: `(product_id, variant_code)`.
- Apply declared keys, foreign keys, nullability, and field meanings.

## SHOP_PRODUCT

- Unique tuple: `(shop_id, variant_id)`.
- Unique tuple: `(shop_id, seller_sku)`.
- Unique tuple: `(shop_product_id, shop_id)`.
- price_amount >= 0. Use a fixed decimal representation for money.
- Availability is owner-maintained unless the optional inventory workflow is implemented.

## PRODUCT_IMAGE

- Unique tuple: `(product_id, sort_order)`.
- Apply declared keys, foreign keys, nullability, and field meanings.

## INVENTORY_BALANCE

- 0 <= quantity_reserved <= quantity_on_hand.
- This snapshot is not a stock movement ledger. Do not claim transaction-safe inventory without a separate stock/reservation workflow.

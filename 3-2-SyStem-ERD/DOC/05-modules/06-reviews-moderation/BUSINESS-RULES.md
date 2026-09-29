# Reviews and Moderation — business rules

These rules are part of the design. An ER diagram alone does not enforce them. Use [constraint enforcement](../../03-database/CONSTRAINT-ENFORCEMENT.md) to distinguish database constraints from transactional/application checks.

## REVIEW

- 1 <= rating <= 5. Listing, when supplied, belongs to shop_id; enforce a composite FK (shop_product_id, shop_id).
- Unique (author_user_id, shop_id) where shop_product_id IS NULL. Unique (author_user_id, shop_product_id) where shop_product_id IS NOT NULL.
- One persistent review per user/target; edits and withdrawals update the same review. Target and author are immutable.
- Normal/verified is derived from REVIEW_VERIFICATION validity, not a user-editable boolean. Product reviews are shop-listing specific, not automatically global product scores.

## REVIEW_VERIFICATION

- Review must target a product listing. Review author equals TOKEN_REDEMPTION.user_id; purchased listing equals reviewed listing.
- The baseline verifies product reviews only. A normal shop review has no REVIEW_VERIFICATION row.
- Create/update review plus evidence binding transactionally. Binding is retained after withdrawal to prevent token reuse.
- A refund/void/revocation removes eligibility for a current verified badge; preserve the historical evidence record.

## REVIEW_REPLY

- Check active shop membership at write time; former membership does not erase historical authorship.

## REVIEW_REPORT

- Unique tuple: `(review_id, reporter_user_id)`.
- Apply declared keys, foreign keys, nullability, and field meanings.

## MODERATION_ACTION

- Report, when supplied, references this same review.
- Moderator must have permission for the reviewed shop mall. Record the action and update review/report state in one transaction.

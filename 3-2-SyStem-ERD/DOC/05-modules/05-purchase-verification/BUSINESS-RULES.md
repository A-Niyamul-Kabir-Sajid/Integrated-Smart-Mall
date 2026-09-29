# Purchase Verification — business rules

These rules are part of the design. An ER diagram alone does not enforce them. Use [constraint enforcement](../../03-database/CONSTRAINT-ENFORCEMENT.md) to distinguish database constraints from transactional/application checks.

## PURCHASE

- Unique tuple: `(shop_id, external_receipt_ref)`.
- total_amount >= 0. A completed sale contains at least one item and total_amount equals its sum of line_total_amount.
- Only authorized shop staff or a trusted import may record or complete a sale. This is a trust boundary, not bank/payment proof.
- Partial refunds, payments, delivery, and checkout are outside this baseline.

## PURCHASE_ITEM

- Unique tuple: `(purchase_id, line_no)`.
- quantity > 0; unit_price_amount >= 0; 0 <= line_discount_amount <= quantity * unit_price_amount.
- line_total_amount = quantity * unit_price_amount - line_discount_amount. Listing shop and currency must equal purchase shop and currency.
- After completion, keep snapshots immutable; later catalogue price/name edits do not rewrite history.

## VERIFICATION_TOKEN

- expires_at > issued_at. Issue only for a completed purchase.
- Token issuance confirms issuer membership in the purchase shop. A token is not a public navigation QR.
- Reissue by rotating the digest before redemption; old secrets immediately stop working. No rotation after redemption.
- Do not generate predictable tokens from product ID plus a fixed mall key. The random secret is tied to purchase, listing, shop, and mall through database relationships.

## TOKEN_REDEMPTION

- In one transaction, lock the token, verify digest, expiry/revocation and completed purchase, then insert the redemption.
- If the purchase has buyer_user_id, it must equal the claimant. For an unbound walk-in purchase, the first valid holder claims it.
- Possession of a leaked unbound token is a risk; keep secrets out of logs and URLs where practical.

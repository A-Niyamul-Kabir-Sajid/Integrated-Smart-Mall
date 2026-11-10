# PurchaseVerification

Trusted purchase evidence, verification credentials and their redemption.

- Namespace: `App\Modules\PurchaseVerification`
- Jira: EPIC-06 Purchase Verification
- Requirements, business rules and ERD: [DOCS/modules/PurchaseVerification/README.md](../../../DOCS/modules/PurchaseVerification/README.md) (ERD domain 05)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

EPIC-06 complete at the service layer: purchase records (ISM-66), credential lifecycle (ISM-68) and credential validation and redemption (ISM-71). There are no routes or screens yet: shop staff will record sales and print credentials from the shop workspace (EPIC-10), and customers will redeem a credential while writing a review (EPIC-07). Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md).

## Domain

```text
SHOP 1──* PURCHASE 1──* PURCHASE_ITEM 1──0..1 VERIFICATION_TOKEN 1──0..1 TOKEN_REDEMPTION
APP_USER 0..1──* PURCHASE (buyer)        SHOP_PRODUCT 1──* PURCHASE_ITEM
```

| Entity (ERD) | Table | Key rules |
|---|---|---|
| PURCHASE | `purchases` | Unique `(shop_id, external_receipt_ref)`; status draft/completed/voided/refunded; total equals the sum of its lines; buyer optional (walk-in sale). After recording, only the status may change (completed → voided or refunded) |
| PURCHASE_ITEM | `purchase_items` | Unique `(purchase_id, line_no)`; label, unit price, discount and line total are snapshots; the listing must belong to the sale's shop; never edited or deleted |
| VERIFICATION_TOKEN | `verification_tokens` | One per purchase line; stores only the SHA-256 digest of a 256-bit random secret; issuer, issue time, expiry, optional revocation |
| TOKEN_REDEMPTION | `token_redemptions` | Primary key is the token id: the database accepts one successful claim per token |

## Services and contract

| Class | Does |
|---|---|
| `Services\PurchaseService` | Staff (`work-in-shop`) record a completed sale; amounts are computed and checked server-side, void or refund a sale, list a shop's sales (staff only) and a customer's own purchases |
| `Services\VerificationCredentialService` | Issue one credential per line of a completed sale (default 90 days, at most one year), reissue (rotates the secret, restarts expiry, reactivates a revoked credential), revoke. The raw secret is returned once in `DTOs\IssuedCredentialData` and is never stored, logged or audited |
| `Services\CredentialSecrets` | Generates `ismv_` + 43 URL-safe characters from `random_bytes(32)`; digests and format check |
| `Contracts\PurchaseVerifier` → `Services\TokenVerificationService` | `checkEligibility()` (does not consume), `redeem()` (atomic, once) and `redeemedTokenIdsForPurchase()` for ReviewsModeration |
| `Events\PurchaseStatusChanged` | Dispatched after a sale is voided or refunded; ReviewsModeration revokes the verified badge of the reviews it backed |

### Verification rules (ISM-71)

Checked in this order; the first failure is returned as `Enums\VerificationFailure`, and `redeem()` throws `BusinessRuleViolation` with rule `verification.<failure>`:

1. The customer account is active (`customer_not_allowed`).
2. The text looks like a secret and its digest matches a credential, and the sale's named buyer (if any) is this customer. Otherwise `unknown_credential`, the same answer for unknown, malformed and someone else's credentials. A walk-in sale (no buyer) goes to the first valid holder.
3. If a listing id is given it must be the credential's listing (`wrong_scope`).
4. Not already redeemed (`already_redeemed`), not revoked (`revoked`), sale still completed (`purchase_not_completed`), not expired (`expired`).

Redemption runs in one transaction that locks the token row and inserts the redemption. Two simultaneous requests: one wins, the other gets `already_redeemed` (the redemption primary key is the backstop if the lock is bypassed). A redeemed credential stays verified after it expires and can no longer be reissued or revoked.

### Using it from another module

```php
$result = app(PurchaseVerifier::class)->redeem($user->getKey(), $secretFromForm, $listing->getKey());
// $result->shopProductId, ->purchaseItemId, ->redeemedAt; throws BusinessRuleViolation otherwise.
```

## Security notes

- Keep the secret out of URLs and logs where practical; the audit logger already redacts keys that look like secrets, and credential audit entries carry ids only.
- When the HTTP entry point is built (EPIC-07), add a named rate limiter for redemption attempts: a leaked unbound (walk-in) credential is claimable by whoever presents it first.
- A verification QR is distinct from the public navigation QR codes of MapsNavigation.

## Limitations

- No routes or screens yet: the review module redeems credentials through the contract (EPIC-07); shop staff will record sales and print credentials from the workspace (EPIC-10).
- Orders, reservations, returns and rewards stay out of scope (C-02). Refunds are a status change only: no partial refund amounts.
- Verified shop or mall reviews (C-03) are not supported: a credential verifies one purchased listing.
- Amounts are single-currency per sale (a sale's lines must share one currency) and use two decimals.

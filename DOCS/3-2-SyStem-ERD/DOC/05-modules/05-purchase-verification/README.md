# Module 05 — Purchase Verification

Recorded purchases and single-use purchase evidence for product reviews.

Status: proposed logical design v1.0; not an implemented or supervisor-approved database.

## Files

- [Full Mermaid source](ERD.mmd) and [Markdown rendering](ERD.md).
- [Data dictionary](DATA-DICTIONARY.md): all attributes, keys, nullability, references, and indexes.
- [Business rules](BUSINESS-RULES.md): constraints and lifecycle decisions.
- `ERD.svg`: ready-to-open vector preview.
- `views/`: smaller diagrams for detailed reading where supplied.

## Owned tables

| Table | Scope | Purpose |
| --- | --- | --- |
| PURCHASE | Core | Recorded sale used as evidence; not a complete checkout/payment system. |
| PURCHASE_ITEM | Core | Immutable record of the product listing and amount bought. |
| VERIFICATION_TOKEN | Core | One rotatable opaque review credential per purchase line. |
| TOKEN_REDEMPTION | Core | Atomic, single successful claim of a purchase token by a user. |

## External references

| Referenced table | Owner |
| --- | --- |
| APP_USER | Accounts and Access |
| SHOP | Mall and Shop Directory |
| SHOP_PRODUCT | Products and Catalogue |

## Design explanation

This module records purchase evidence from trusted shop entry/import; it is not an online payment platform. A PURCHASE contains PURCHASE_ITEM rows. Each line may have one VERIFICATION_TOKEN; a quantity of three on one line still authorizes one product-review verification in this baseline.

Generate a high-entropy random secret, store its digest, and bind the credential to the purchase item. The mall is derived through the purchase shop. This implements the intended mall-specific purchase proof without a predictable hash of a product identifier and a fixed secret. Keep a verification QR distinct from public navigation QR codes.

Redeem in one transaction after checking token validity and purchase state. TOKEN_REDEMPTION uses the token ID as its primary key, so concurrent second redemption fails. If a named buyer exists, only that account may claim the token. For a walk-in purchase without a known buyer, the first valid holder claims it. Reissuing a token rotates the same unredeemed row; expiry prevents a new claim but does not by itself invalidate a previously verified purchase.

## Update rule

Update this module, its cross-module contract, and the master diagram in the same change. Existing parent tables are referenced, never copied into this module. All current proposals and unresolved choices are listed in the shared design notes.

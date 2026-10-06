# Module 06 — Reviews and Moderation

Normal and purchase-verified reviews, replies, reports, and moderation history.

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
| REVIEW | Core | Shared review content with a concrete shop target and optional listing target. |
| REVIEW_VERIFICATION | Core | Optional, unique binding between a product review and claimed purchase evidence. |
| REVIEW_REPLY | Core | Public replies by authorized staff of the reviewed shop. |
| REVIEW_REPORT | Core | A user report about one review. |
| MODERATION_ACTION | Core | Append-only review moderation history. |

## External references

| Referenced table | Owner |
| --- | --- |
| APP_USER | Accounts and Access |
| SHOP | Mall and Shop Directory |
| SHOP_PRODUCT | Products and Catalogue |
| TOKEN_REDEMPTION | Purchase Verification |

## Design explanation

The earlier outline mentioned ShopReview and ProductReview separately. This detailed design uses one REVIEW table: shop_product_id null means a shop review; a non-null listing ID means a product-listing review. This shares authorship, moderation, replies, and reporting while preserving real foreign keys. It is a proposed design refinement, not an already implemented change.

A normal review has no active REVIEW_VERIFICATION binding. A purchase-verified product review binds to one TOKEN_REDEMPTION. Check that the claimant is the review author and the purchased listing is the reviewed listing. A binding is never reusable for a different review. The verified badge is computed from binding revocation, token revocation, and purchase state; do not let clients set it.

The baseline allows one review per user/target, updated when the user changes their opinion. Two conditional unique indexes enforce this. For example, a user may write one shop review and one product review for each listing in that shop. Only published reviews feed public aggregates. Do not mix shop scores and product scores silently.

Refund/void handling keeps the review as an ordinary opinion but removes current purchase verification according to the documented rule. Shop replies require membership in the reviewed shop. Mall moderation requires mall-scoped authorization and appends a MODERATION_ACTION. Define any staff/self-review exclusion policy before release; it is a product policy rather than an assumed universal rule.

## Update rule

Update this module, its cross-module contract, and the master diagram in the same change. Existing parent tables are referenced, never copied into this module. All current proposals and unresolved choices are listed in the shared design notes.

# Reviews and Moderation — data dictionary

All fields are required unless Nullable is Yes. Types are logical; see [shared conventions](../../03-database/CONVENTIONS.md) before generating SQL. PK, FK, and UK mean primary key, foreign key, and single-column unique key. Multiple PK fields form one composite primary key.

## REVIEW

Shared review content with a concrete shop target and optional listing target.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| review_id | uuid | PK | No | — | Review identifier. |
| author_user_id | uuid | FK | No | APP_USER.user_id | Review author. |
| shop_id | uuid | FK | No | SHOP.shop_id | Reviewed shop, including the seller of a reviewed product. |
| shop_product_id | uuid | FK | Yes | SHOP_PRODUCT.shop_product_id | Null for shop review; present for product-listing review. |
| rating | smallint | — | No | — | Integer rating from 1 through 5. |
| title | varchar(160) | — | Yes | — | Optional summary title. |
| body | text | — | No | — | Review text. |
| status | varchar(16) | — | No | — | pending, published, hidden, or withdrawn. |
| created_at | timestamptz | — | No | — | Creation time. |
| updated_at | timestamptz | — | No | — | Last content/status update. |

Primary key: `(review_id)`.

Suggested query indexes: `(shop_id, status, created_at)`; `(shop_product_id, status)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## REVIEW_VERIFICATION

Optional, unique binding between a product review and claimed purchase evidence.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| review_id | uuid | PK, FK | No | REVIEW.review_id | Verified review; at most one evidence binding. |
| verification_token_id | uuid | FK, UK | No | TOKEN_REDEMPTION.verification_token_id | Claimed token; cannot verify multiple reviews. |
| verified_at | timestamptz | — | No | — | Binding time. |
| revoked_at | timestamptz | — | Yes | — | Verification withdrawn after refund/fraud or correction. |
| revocation_reason | text | — | Yes | — | Why evidence is no longer valid. |

Primary key: `(review_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## REVIEW_REPLY

Public replies by authorized staff of the reviewed shop.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| review_reply_id | uuid | PK | No | — | Reply identifier. |
| review_id | uuid | FK | No | REVIEW.review_id | Review being answered. |
| author_user_id | uuid | FK | No | APP_USER.user_id | Authorized shop account. |
| body | text | — | No | — | Reply text. |
| status | varchar(16) | — | No | — | published or hidden. |
| created_at | timestamptz | — | No | — | Reply time. |
| updated_at | timestamptz | — | No | — | Last edit. |

Primary key: `(review_reply_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## REVIEW_REPORT

A user report about one review.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| review_report_id | uuid | PK | No | — | Report identifier. |
| review_id | uuid | FK | No | REVIEW.review_id | Reported review. |
| reporter_user_id | uuid | FK | No | APP_USER.user_id | Reporting account. |
| reason_code | varchar(30) | — | No | — | spam, abuse, conflict_of_interest, or other. |
| details | text | — | Yes | — | Optional explanation. |
| status | varchar(16) | — | No | — | open, resolved, or dismissed. |
| created_at | timestamptz | — | No | — | Submission time. |
| resolved_at | timestamptz | — | Yes | — | Resolution time. |

Primary key: `(review_report_id)`.

Additional unique keys: `(review_id, reporter_user_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## MODERATION_ACTION

Append-only review moderation history.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| moderation_action_id | uuid | PK | No | — | Action identifier. |
| review_id | uuid | FK | No | REVIEW.review_id | Moderated review. |
| review_report_id | uuid | FK | Yes | REVIEW_REPORT.review_report_id | Optional triggering report. |
| actor_user_id | uuid | FK | No | APP_USER.user_id | Authorized moderator. |
| action | varchar(24) | — | No | — | publish, hide, restore, dismiss_report, or revoke_verification. |
| reason | text | — | No | — | Reason for the action. |
| previous_status | varchar(16) | — | No | — | Review status before action. |
| resulting_status | varchar(16) | — | No | — | Review status after action. |
| created_at | timestamptz | — | No | — | Action time. |

Primary key: `(moderation_action_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

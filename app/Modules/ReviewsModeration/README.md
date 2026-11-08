# ReviewsModeration

Reviews and ratings, verified-purchase binding, shop replies, reports and moderation.

- Namespace: `App\Modules\ReviewsModeration`
- Jira: EPIC-07 Reviews & Moderation
- Requirements, business rules and ERD: [DOCS/modules/ReviewsModeration/README.md](../../../DOCS/modules/ReviewsModeration/README.md) (ERD domain 06)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

EPIC-07 complete: data layer and rating summaries (ISM-74), customer reviews, shop replies and review pages (ISM-77), reporting and the mall moderation desk (ISM-80). Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md).

## Domain

```text
USER 1──* REVIEW *──1 SHOP            REVIEW *──0..1 SHOP_PRODUCT (listing must belong to the shop)
REVIEW 1──0..1 REVIEW_VERIFICATION 1──1 TOKEN_REDEMPTION (PurchaseVerification)
REVIEW 1──* REVIEW_REPLY      REVIEW 1──* REVIEW_REPORT      REVIEW 1──* MODERATION_ACTION
```

| Entity (ERD) | Table | Key rules |
|---|---|---|
| REVIEW | `reviews` | One table for shop reviews (no listing) and listing reviews. One review per user and target (two partial unique indexes); composite FK keeps a listing in the reviewed shop; rating 1 to 5; status pending/published/hidden/withdrawn; author and target never change; never deleted |
| REVIEW_VERIFICATION | `review_verifications` | Binds a product review to one redeemed credential (unique token); the verified badge is derived from it (not revoked), never stored on the review; kept after withdrawal so the credential cannot verify a second review |
| REVIEW_REPLY | `review_replies` | A public answer by staff of the reviewed shop; published or hidden, never deleted |
| REVIEW_REPORT | `review_reports` | Unique `(review, reporter)`; reason spam/abuse/conflict_of_interest/other; open, resolved or dismissed; the reporter id is hidden from serialisation |
| MODERATION_ACTION | `moderation_actions` | Append-only history: action, reason, status before and after, actor |

## Policy decisions made for this release

These fill the gaps the ERD and Jira leave open. They are defaults, kept in one place (`Services\ReviewPolicy`), and need the team's confirmation:

- A new review is **published at once**; reports and moderators police it afterwards. Pending exists for a mall that wants approval first (a moderator can publish).
- The author may **edit for 30 days** after writing; a withdrawn review can be rewritten at any time (same row); a hidden review cannot be changed by its author.
- **Staff and owners of a shop cannot review that shop or its products** (the conflict-of-interest rule the ERD asks to define before release).
- A shop gets **one public reply per review** (editable; removing it allows a new one).
- Verification applies to **product-listing reviews only** (C-03 stays open for shop or mall reviews).

## Services and contracts

| Class | Does |
|---|---|
| `Services\ReviewService` | Submit (create or edit in place), withdraw and verify a customer's own review. Reads only rating, title and body: the verified badge is never an input. A purchase code is redeemed through `PurchaseVerifier` inside the same transaction as the review, so a refused code saves nothing |
| `Services\ReviewReplyService` | Staff of the reviewed shop reply, edit and hide, only on a published review |
| `Services\ReviewQueryService` | Public lists (published reviews and published replies only), a customer's own reviews, the shop inbox |
| `Services\ReviewReportService` | A customer reports a published review that is not theirs, once, with a reason |
| `Services\ModerationService` | The mall desk: reported and hidden queues; hide, restore, publish, dismiss reports, remove the verified badge. Needs `administer-mall` for the review's own mall and a written reason; each decision is recorded in the history in the same transaction |
| `Contracts\ReviewSummaryLookup` → `Services\RatingSummaryService` | Count, average, 1 to 5 star distribution and verified count from published reviews only, for a shop (its own reviews) or a listing, in bulk without extra queries. Shop and product scores are never mixed. Computed on every call, so a moderation decision or withdrawal changes it at once |
| `Listeners\RevokeVerificationWhenPurchaseEnds` | When a sale is voided or refunded (`PurchaseStatusChanged`), the badge of the reviews it verified is revoked; the reviews stay |

## Pages

| Route | Access | Purpose |
|---|---|---|
| `GET /malls/{mall}/shops/{shop}/reviews` | public | Shop reviews, rating summary, your review form |
| `GET /malls/{mall}/listings/{listing}/reviews` | public | Product reviews with a "verified purchases" filter |
| `POST` the same URLs | auth, `throttle:review-submissions` | Publish or edit your review (no verified field) |
| `POST /reviews/{review}/withdraw`, `/verify`, `/report` | auth, throttled | Withdraw, verify with a purchase code, report |
| `GET /account/reviews` | auth | Your reviews in every status |
| `GET /admin/malls/{mall}/moderation` and its `POST` decisions | auth, `administer-mall` | The moderation desk |
| `GET /workspace/shops/{shop}/reviews` and reply routes | auth, `work-in-shop` (module ShopOwnerOperations) | The shop's review inbox |

Rating summaries appear on the shop page and next to each product offer through view composers registered here, so MallShopDirectory and ProductsCatalogue stay unaware of reviews.

## Security notes

- Reporter identity is kept for abuse control but is never loaded into the moderation queue, shown to the author or the shop, or serialised. It is only in the audit log.
- Replies, reviews and moderation decisions are scoped: a shop's staff cannot reach another shop's reviews through their own URL, and a moderator cannot reach another mall's reviews through theirs.
- Audit entries carry ids and ratings, never review text or purchase codes.

## Limitations

- No edit history of a review's earlier texts (only the current text and the moderation history are kept).
- Shop list cards do not show ratings yet; use `ReviewSummaryLookup::forShops()` when that screen is changed.
- Moderators are not notified of new reports and customers are not notified of decisions (EPIC-09 notifications are not built); the desk is a pull queue.
- Sentiment analysis (optional prototype in the epic) is not built and must never replace human moderation.
- Mall administration screens live here until EPIC-11 provides a mall admin area.
- Blade classes added for these pages need `npm run build` before they show in the browser.

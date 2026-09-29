# Data lifecycle and retention

Retention durations are open product decisions. Configure them before collecting real user data rather than inventing fixed durations in the schema.

| Data | Update/deletion policy |
| --- | --- |
| APP_USER | Retain stable ID while anonymizing identifying fields when appropriate; suspend authentication before clearing credentials |
| Roles/memberships | Revoke membership rather than deleting historical authorship; restrictive FKs prevent dangling references |
| AUTH_SESSION | Revoke immediately when needed; purge expired sessions after the configured security window |
| MALL/FLOOR/SHOP/SHOP_UNIT/FACILITY | Archive records that historical maps, purchases, or reviews reference |
| PRODUCT/PRODUCT_VARIANT/SHOP_PRODUCT | Archive listings; do not retarget their IDs to a different seller/variant after transactions exist |
| Favourites/preferences | User may delete; cascade from an approved hard account deletion only after retention dependencies are resolved |
| MAP_VERSION | Edit drafts; published datasets are immutable except documented operational edge closures; retain retired versions while referenced |
| QR_CHECKPOINT | Keep stable public identity across map versions; retired codes display a clear invalid/retired result |
| ROUTE_SESSION/ROUTE_STEP | Optional and time-limited; purge steps with their session; prefer stateless routing if history is not needed |
| PURCHASE/PURCHASE_ITEM | Retain immutable completed-sale evidence; refund/void through status changes with authorized controls |
| VERIFICATION_TOKEN/TOKEN_REDEMPTION | Retain digest/binding while review evidence is retained; never re-enable a redeemed or revoked secret |
| REVIEW and replies | Withdraw/hide rather than repurposing the row; preserve token uniqueness and moderation history |
| MODERATION_ACTION | Append-only; any correction is another action |
| Conversation/messages | Expire according to policy; remove dependent citations and recommendations with the conversation |
| Knowledge revisions/chunks | Replace active retrieval content through new revisions; retain referenced revisions for citations or apply explicit redaction |
| Vector index | Delete obsolete vectors alongside approved source cleanup; ensure external index deletion is part of the workflow |
| Metric/report/forecast records | Treat frozen results as immutable; version corrections and retain input links needed for reproducibility |
| Files and media | Store controlled object keys; remove orphaned assets only after reference checks; authorize private downloads |

Default ON DELETE RESTRICT is appropriate for retained business history. Explicit cascades can be used for dependent session steps, expired conversation content, and disposable favourites after the relevant parent deletion is authorized. Do not blanket-cascade a shop deletion into purchases, reviews, or reports.

Backups must include the database and referenced map/report assets. Test restoration to a consistent map version and verify that private objects remain protected after recovery.

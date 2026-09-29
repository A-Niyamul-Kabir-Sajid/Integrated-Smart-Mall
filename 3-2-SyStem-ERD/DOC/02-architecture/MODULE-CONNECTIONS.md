# Connecting the modules

A module owns its tables and write rules. Other modules reference their IDs and use a documented service/API for protected operations. This is a modular monolith data design; it does not require nine deployable services.

## End-to-end flows

| Flow | Table path | Boundary rule |
| --- | --- | --- |
| Browse a mall | MALL -> FLOOR / SHOP -> SHOP_UNIT | Only public, active records are visible |
| Search a product | PRODUCT_CATEGORY -> PRODUCT -> PRODUCT_VARIANT -> SHOP_PRODUCT -> SHOP | Return the actual listing and selling shop; refresh price/availability |
| Begin at a QR | QR_CHECKPOINT -> current MAP_VERSION -> CHECKPOINT_ANCHOR -> NAVIGATION_NODE | Stable QR identity resolves to the current published graph |
| Route to a shop | SHOP -> SHOP_UNIT -> SHOP_ENTRANCE -> NAVIGATION_NODE -> NAVIGATION_EDGE | Choose an entrance in the pinned map version and apply route filters |
| Change floors | NAVIGATION_EDGE -> VERTICAL_CONNECTOR / CONNECTOR_STOP | Both endpoint stops are in the same map version and valid floors |
| Display AR guidance | Chosen graph path + AR_REFERENCE_ANCHOR | Runtime pose alignment/tracking is required; DB coordinates alone are insufficient |
| Verify a purchase review | PURCHASE_ITEM -> VERIFICATION_TOKEN -> TOKEN_REDEMPTION -> REVIEW_VERIFICATION -> REVIEW | User and listing match the purchase evidence; single-use constraints hold |
| Save a destination | APP_USER -> FAVOURITE_SHOP / FAVOURITE_PRODUCT | Composite keys prevent duplicate saves |
| Answer a shopping request | CONVERSATION -> CHAT_MESSAGE -> MESSAGE_RECOMMENDATION; citations -> chunks -> revisions | SQL validates IDs/availability; retrieval respects mall and access scope |
| Produce a monthly report | SHOP -> SHOP_METRIC_SNAPSHOT -> REPORT_METRIC_SNAPSHOT -> REPORT_RUN -> REPORT_ARTIFACT | Freeze source cutoff, shop scope, inputs, and dataset label |
| Add a forecast | SHOP_METRIC_SNAPSHOT -> FORECAST_TRAINING_SNAPSHOT -> FORECAST_RUN -> FORECAST_POINT | Use historical inputs only; distinguish predictions from measured results |

## Shared identifiers and boundaries

| Identifier | Owner | Meaning |
| --- | --- | --- |
| user_id | M01 | One identity across interfaces |
| mall_id, floor_id, shop_id, shop_unit_id | M02 | Stable directory identities |
| product_id, variant_id, shop_product_id | M03 | Shared definition, variant, and seller-specific listing |
| map_version_id, node_id, edge_id | M04 | One versioned routing graph |
| checkpoint_id | M04 | Stable physical QR marker |
| purchase_item_id, verification_token_id | M05 | Immutable line evidence and a claimable credential record |
| review_id | M06 | Opinion with its moderation/evidence lifecycle |
| conversation_id, message_id, chunk_id | M08 | Conversation and retrieval evidence |
| report_run_id, snapshot_id, forecast_run_id | M09 | Reproducible analytics and outputs |

## Suggested implementation sequence

1. Create M02 directory and M01 accounts/scoped permissions. These modules refer to each other conceptually, but their table FKs can be ordered safely: MALL/FLOOR/SHOP_CATEGORY/SHOP plus APP_USER/ROLE before memberships.
2. Implement M03 catalogue and M04 map graph independently using the shared shop/floor IDs.
3. Deliver QR -> search -> destination -> route with one complete map version; test AR alignment as its own feature.
4. Add M05 purchase recording and M06 normal/verified review workflows.
5. Add M07 saved items/preferences.
6. Add M09 descriptive monthly reports from real or clearly labelled demonstration data.
7. Add M08 RAG and M09 forecasting after their data sources and evaluation cases exist.

Create independent tables before FK children, or add FKs after both tables exist. A self-referencing category FK is valid once the table exists. Use the ownership and cross-module relation inventories to plan migrations rather than assuming folder order alone is a migration order.

# Cross-module foreign keys

Each row is a child field referencing an existing parent key. The rightmost column is the number of child rows allowed per parent by this individual FK; additional compound constraints may narrow it. Composite consistency checks are listed separately.

| Child field | Child owner | Parent field | Parent owner | Parent per child | Children per parent |
| --- | --- | --- | --- | --- | --- |
| MALL_MEMBERSHIP.mall_id | M01 | MALL.mall_id | M02 | 1 | 0..many |
| SHOP_MEMBERSHIP.shop_id | M01 | SHOP.shop_id | M02 | 1 | 0..many |
| SHOP_PRODUCT.shop_id | M03 | SHOP.shop_id | M02 | 1 | 0..many |
| MAP_VERSION.mall_id | M04 | MALL.mall_id | M02 | 1 | 0..many |
| FLOOR_MAP.floor_id | M04 | FLOOR.floor_id | M02 | 1 | 0..many |
| NAVIGATION_NODE.floor_id | M04 | FLOOR.floor_id | M02 | 1 | 0..many |
| VERTICAL_CONNECTOR.mall_id | M04 | MALL.mall_id | M02 | 1 | 0..many |
| CONNECTOR_STOP.floor_id | M04 | FLOOR.floor_id | M02 | 1 | 0..many |
| QR_CHECKPOINT.mall_id | M04 | MALL.mall_id | M02 | 1 | 0..many |
| SHOP_ENTRANCE.shop_unit_id | M04 | SHOP_UNIT.shop_unit_id | M02 | 1 | 0..many |
| FACILITY_ANCHOR.facility_id | M04 | FACILITY.facility_id | M02 | 1 | 0..many |
| ROUTE_SESSION.user_id | M04 | APP_USER.user_id | M01 | 0..1 | 0..many |
| PURCHASE.shop_id | M05 | SHOP.shop_id | M02 | 1 | 0..many |
| PURCHASE.buyer_user_id | M05 | APP_USER.user_id | M01 | 0..1 | 0..many |
| PURCHASE_ITEM.shop_product_id | M05 | SHOP_PRODUCT.shop_product_id | M03 | 1 | 0..many |
| VERIFICATION_TOKEN.issued_by_user_id | M05 | APP_USER.user_id | M01 | 1 | 0..many |
| TOKEN_REDEMPTION.user_id | M05 | APP_USER.user_id | M01 | 1 | 0..many |
| REVIEW.author_user_id | M06 | APP_USER.user_id | M01 | 1 | 0..many |
| REVIEW.shop_id | M06 | SHOP.shop_id | M02 | 1 | 0..many |
| REVIEW.shop_product_id | M06 | SHOP_PRODUCT.shop_product_id | M03 | 0..1 | 0..many |
| REVIEW_VERIFICATION.verification_token_id | M06 | TOKEN_REDEMPTION.verification_token_id | M05 | 1 | 0..1 |
| REVIEW_REPLY.author_user_id | M06 | APP_USER.user_id | M01 | 1 | 0..many |
| REVIEW_REPORT.reporter_user_id | M06 | APP_USER.user_id | M01 | 1 | 0..many |
| MODERATION_ACTION.actor_user_id | M06 | APP_USER.user_id | M01 | 1 | 0..many |
| USER_PREFERENCE.user_id | M07 | APP_USER.user_id | M01 | 1 | 0..1 |
| USER_PREFERENCE.default_mall_id | M07 | MALL.mall_id | M02 | 0..1 | 0..many |
| FAVOURITE_SHOP.user_id | M07 | APP_USER.user_id | M01 | 1 | 0..many |
| FAVOURITE_SHOP.shop_id | M07 | SHOP.shop_id | M02 | 1 | 0..many |
| FAVOURITE_PRODUCT.user_id | M07 | APP_USER.user_id | M01 | 1 | 0..many |
| FAVOURITE_PRODUCT.shop_product_id | M07 | SHOP_PRODUCT.shop_product_id | M03 | 1 | 0..many |
| CONVERSATION.mall_id | M08 | MALL.mall_id | M02 | 1 | 0..many |
| CONVERSATION.user_id | M08 | APP_USER.user_id | M01 | 0..1 | 0..many |
| KNOWLEDGE_DOCUMENT.mall_id | M08 | MALL.mall_id | M02 | 1 | 0..many |
| KNOWLEDGE_DOCUMENT.shop_id | M08 | SHOP.shop_id | M02 | 0..1 | 0..many |
| KNOWLEDGE_DOCUMENT.shop_product_id | M08 | SHOP_PRODUCT.shop_product_id | M03 | 0..1 | 0..many |
| MESSAGE_RECOMMENDATION.shop_product_id | M08 | SHOP_PRODUCT.shop_product_id | M03 | 1 | 0..many |
| SHOP_METRIC_SNAPSHOT.shop_id | M09 | SHOP.shop_id | M02 | 1 | 0..many |
| REPORT_RUN.mall_id | M09 | MALL.mall_id | M02 | 1 | 0..many |
| REPORT_RUN.requested_by_user_id | M09 | APP_USER.user_id | M01 | 0..1 | 0..many |
| REPORT_SHOP_SCOPE.shop_id | M09 | SHOP.shop_id | M02 | 1 | 0..many |
| FORECAST_RUN.shop_id | M09 | SHOP.shop_id | M02 | 1 | 0..many |

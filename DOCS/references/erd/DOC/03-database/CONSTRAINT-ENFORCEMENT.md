# Constraint enforcement plan

An ERD describes structure. Its arrows cannot by themselves enforce all business rules. This plan identifies the required enforcement points; it is not executed SQL.

## Standard database constraints

| Rule | Enforcement |
| --- | --- |
| Required fields | NOT NULL |
| Entity identities | PRIMARY KEY |
| Individual parent references | FOREIGN KEY, default ON DELETE RESTRICT for retained history |
| Unique tuple | UNIQUE on the complete column list in the dictionary |
| Rating 1–5, nonnegative amounts, positive quantities | Row-level CHECK |
| No repeated favourite, role assignment, redemption, route step, or citation | Composite or shared primary key |
| One token record per purchase line | UNIQUE(VERIFICATION_TOKEN.purchase_item_id) |
| One review binding per token | UNIQUE(REVIEW_VERIFICATION.verification_token_id) |
| One platform/mall/shop role assignment | USER_ROLE primary key and scoped membership unique tuples |

## Conditional uniqueness

Where a database supports partial/filtered indexes, use the following predicates. Otherwise implement an equivalent constraint strategy; a pre-insert SELECT alone is race-prone.

| Table | Unique columns | Predicate |
| --- | --- | --- |
| MAP_VERSION | mall_id | status = published |
| SHOP_ENTRANCE | shop_unit_id, map_version_id | is_primary = true |
| REVIEW | author_user_id, shop_id | shop_product_id IS NULL |
| REVIEW | author_user_id, shop_product_id | shop_product_id IS NOT NULL |

## Composite foreign keys and same-parent consistency

| Child constraint | Required target / additional rule |
| --- | --- |
| NAVIGATION_NODE(map_version_id, floor_id) | FLOOR_MAP(map_version_id, floor_id), a declared unique tuple |
| NAVIGATION_EDGE(from_node_id, map_version_id) | NAVIGATION_NODE(node_id, map_version_id) |
| NAVIGATION_EDGE(to_node_id, map_version_id) | NAVIGATION_NODE(node_id, map_version_id) |
| CONNECTOR_STOP(node_id, map_version_id, floor_id) | NAVIGATION_NODE(node_id, map_version_id, floor_id) |
| CHECKPOINT_ANCHOR(node_id, map_version_id) | NAVIGATION_NODE(node_id, map_version_id) |
| SHOP_ENTRANCE(node_id, map_version_id) | NAVIGATION_NODE(node_id, map_version_id) |
| FACILITY_ANCHOR(node_id, map_version_id) | NAVIGATION_NODE(node_id, map_version_id) |
| AR_REFERENCE_ANCHOR(node_id, map_version_id) | NAVIGATION_NODE(node_id, map_version_id) |
| ROUTE_SESSION(start_node_id, map_version_id) | NAVIGATION_NODE(node_id, map_version_id) |
| ROUTE_SESSION(destination_node_id, map_version_id) | NAVIGATION_NODE(node_id, map_version_id) |
| REVIEW(shop_product_id, shop_id) | SHOP_PRODUCT(shop_product_id, shop_id); nullable listing allows a shop review |
| KNOWLEDGE_DOCUMENT(shop_product_id, shop_id) | SHOP_PRODUCT(shop_product_id, shop_id); CHECK requires shop when listing is present |

Single-field lines in the diagrams show the semantic parent references; the tuples above strengthen them in the physical database. Avoid adding duplicate indexes where a composite FK replaces a scalar FK.

## Cross-table rules requiring a transaction, trigger, or controlled write service

| Invariant | Enforcement approach |
| --- | --- |
| Membership ROLE.scope_type matches its assignment table | Trigger on assignment and reject incompatible role scope changes |
| Shop unit, floor, map, connector, checkpoint, and node belong to the correct mall | Validate joins inside the write transaction; use triggers for every direct DB write/import |
| Shop/facility entrance node has the correct floor | Validate physical unit/facility floor against the referenced node |
| Cross-floor edge endpoints are stops of its connector | Validate both CONNECTOR_STOP rows within the same map version |
| Category hierarchy contains no cycles | Cycle detection on insert/update; prevent concurrent conflicting parent changes |
| Opening intervals do not overlap | Normalize overnight intervals and check in one locked shop schedule transaction |
| Completed purchase has items and correct total | Complete purchase after validating/locking lines; freeze immutable snapshots |
| Purchase listing belongs to the purchase shop and has compatible currency | Validate before completing purchase; make referenced listing shop/currency immutable thereafter |
| Token issue/redeem is valid | Lock token/purchase state; authenticate issuer/claimant; validate expiry/revocation; rely on redemption PK for uniqueness |
| Review author/listing matches the redeemed purchase | Validate the full token -> item -> purchase and redemption chain before binding |
| Review target/author and evidence binding cannot be swapped | Immutable fields or tightly controlled correction transaction with an audit trail |
| Refund/void removes current verification | Serialize with token/review operations; derive badge from current purchase/token/binding validity |
| Moderation report refers to the same review | Validate parent report and update review/report/action atomically |
| Report inputs match mall/scope/period/cutoff | Validate every input snapshot before freezing the report run |
| Forecast training data matches run shop/metric/period/dataset | Validate links; choose one observation revision per period |
| Citation/recommendation matches conversation permissions and mall | Server-side retrieval and write-time checks; recheck access on display |

Row CHECK constraints cannot safely express joins to unrelated tables in a portable relational design. Do not pretend an ordinary FK proves these multi-table conditions.

## Critical concurrency scenarios

1. Two requests redeem the same token: exactly one redemption succeeds.
2. Two requests create a review for the same user/target: the unique constraint prevents duplicates.
3. A sale is refunded while a token is redeemed or bound: one transaction sees the definitive purchase state; current badge logic remains correct.
4. A new map version is published during routing: each route uses one pinned version throughout.
5. A membership is revoked while privileged content is changed: authorization is checked at the protected operation, with an appropriate transaction boundary.
6. A report is regenerated after late reviews arrive: create a new cutoff/snapshot, retaining the earlier report's inputs.

# Integration contracts and ownership — proposed v0.2

## Shared conventions
Use opaque IDs, UTC stored timestamps, Asia/Dhaka display/report timezone, paginated lists, structured error codes, runtime validation and server-enforced authorization. A role supplied by the browser is never authority. Contracts are proposed until implemented/accepted; examples in module guides are logical interfaces, not existing APIs.

## Ownership table
| Records/services | Owner | Other interfaces |
|---|---|---|
| Credentials, access, audit, notifications, integrations | M12 | All modules consume |
| Customer profile, preferences, saved favourites/history | M01 | M02/M03/M06/M07 consume preferences |
| Shop/product/inventory/promotion operational records | M08 | M02 discovers; M04 transacts; M09 analyzes |
| Public directory queries/comparison | M02 | M03/M04/M06 consume IDs and data |
| Mall/building/unit/facility/tenant records | M10 | M02/M03/M07 consume approved records |
| Maps/graphs/checkpoints/routes/AR alignment | M03 | M10 edits through M03; M02/M06/M07 hand off destinations |
| Orders/purchases/receipts/return workflow/reward ledger | M04 | M08 operates; M05 verifies; M09/M11 analyze |
| Proof tokens/reviews/ratings/moderation decisions | M05 | M08 replies/issues proof; M10 moderates; analytics read |
| Visitor cases/saved parking | M07 | M10 assigns operational resolution using linked/same case ID |
| Shop reports/forecasts | M09 | Authorized shop users; M11 may reuse definitions |
| Mall reports/forecasts/admin answers | M11 | Authorized mall users |
| Customer assistant responses/retrieval | M06 | Public sources only |

Where staff manually record a purchase, M08 writes through M04's purchase-registration service and records evidence source/issuer. Real payment processing is not mandatory for a purchase record, but demo records must be labelled. M05 issues proof only against authorized evidence.

## Spatial data
Use a documented metre-based local frame per floor with origin/axes and calibrated transforms to the building and renderer frames. Checkpoints, graph, map assets and shop entrances must reference a coherent published map version. Route output includes floor transitions and units. QR resolves a known checkpoint; camera pose/heading/tracking requires independent validation. AR loss must not silently display an invented location.

## Mutable workflows
Define state machines for reservations/orders, returns/refunds, tokens, cases and maintenance. Use transactions/idempotency for stock claims, purchase proof redemption, receipts, payment callbacks, refunds and reward entries. Choose a documented policy for which purchase line permits which shop/product review. Token validation and review binding must avoid consuming a token without a recoverable review outcome.

## Events
ProductChanged (M08) refreshes M02/M06. MapPublished (M03, initiated through M10) invalidates incompatible active routes. ReviewApproved/Hidden (M05) updates M02/M09/M11 aggregates. OrderCompleted/Refunded (M04) updates eligible analytics/rewards. VisitorCaseUpdated synchronizes M07/M10. Events can be ordinary application calls/jobs; a broker is not required.

## Analytics and AI
Reports include period, metric version, source cutoff and coverage. Use [start,end) time ranges. Null rating is not zero. Navigation requests measure demand, not visits; revenue needs purchase data; parking occupancy/footfall needs actual sources. Forecasts need chronological holdouts, baseline comparisons and honest data labels. Customer/admin answers must show supporting records and respect separate authorization scopes.

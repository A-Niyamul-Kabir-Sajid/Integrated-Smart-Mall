# Build order

This is dependency guidance, not an approved dated delivery schedule. See [original priorities](references/functional/DOC/project/FEATURE_CATALOGUE.md) and [historical build guidance](references/functional/DOC/planning/BUILD_ORDER.md).

1. Reconcile identity/schema decisions; establish minimum AccountsAccess and platform authorization.
2. Build MallShopDirectory and ProductsCatalogue with authorized owner/admin operations.
3. Establish MapsNavigation map versions, graph/checkpoints and basic 2D route guidance.
4. Add multi-floor/accessibility behavior; run isolated WebAR feasibility experiments early alongside navigation. Production AR integration follows measured device results.
5. Add trusted purchase registration, PurchaseVerification and ReviewsModeration.
6. Build AnalyticsReports and bounded ChatbotRag using maintained source records.
7. Expand ParkingFacilities, administrative/support flows, favourites and operational features as requested.
8. Implement checkout/payments, full inventory, leases, integrations and forecasting only after explicit scope/data/ownership decisions.

The older guide placed WebAR late while the feature catalogue requested early feasibility testing. Parallel early experiments and later validated integration reconcile those recommendations without making device support claims.

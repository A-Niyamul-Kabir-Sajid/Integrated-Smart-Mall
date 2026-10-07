# PurchaseVerification

## Responsibility

Trusted purchase evidence, credentials and redemption. Source folder: [app/Modules/PurchaseVerification](../../../app/Modules/PurchaseVerification/).

Status: folder architecture only; this page routes to existing requirements, not implemented APIs.

## Read before implementation

Start with [the reading order](../../00_START_HERE.md) and [task checklist](../../IMPLEMENTATION_CHECKLIST.md).

### Detailed functional requirements

- [Catalogue section 04: shopping purchase assistance](../../references/functional/DOC/modules/04_shopping_purchase_assistance/GUIDE.md), including its completion checks.
- [Catalogue section 05: reviews ratings trust](../../references/functional/DOC/modules/05_reviews_ratings_trust/GUIDE.md), including its completion checks.
- [Catalogue section 08: shop owner operations](../../references/functional/DOC/modules/08_shop_owner_operations/GUIDE.md), including its completion checks.

Historical feature guides may assign data to a dashboard module. Translate their ownership through the current module map and ERD table ownership; their old source-layout instructions are not the current code layout.

### Data and business rules

- 05-purchase-verification: [overview](../../references/erd/DOC/05-modules/05-purchase-verification/README.md), [business rules](../../references/erd/DOC/05-modules/05-purchase-verification/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/05-purchase-verification/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/05-purchase-verification/ERD.md).

Read [table ownership](../../references/erd/DOC/03-database/TABLE-OWNERSHIP.md) and [database guidance](../../06_DATABASE_GUIDE.md).

## Dependencies and checks

Related modules: ProductsCatalogue, ReviewsModeration, ShopOwnerOperations, AnalyticsReports. Review their contracts and authorization boundaries before changing shared behavior.

Relevant [open questions](../../CONFLICTS.md): C-02, C-03. [API guidance](../../07_API_CONTRACTS.md) applies before defining cross-module inputs, outputs and events.

Use the source guides' completion checks and [test guidance](../../09_TESTING.md); record actual results in [progress](../../PROGRESS.md). Keep business logic in Services and add patterns only when justified.

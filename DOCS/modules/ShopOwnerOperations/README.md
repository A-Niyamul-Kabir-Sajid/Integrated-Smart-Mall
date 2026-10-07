# ShopOwnerOperations

## Responsibility

Authorized shop workflows calling the owning domains. Source folder: [app/Modules/ShopOwnerOperations](../../../app/Modules/ShopOwnerOperations/).

Status: folder architecture only; this page routes to existing requirements, not implemented APIs.

## Read before implementation

Start with [the reading order](../../00_START_HERE.md) and [task checklist](../../IMPLEMENTATION_CHECKLIST.md).

### Detailed functional requirements

- [Catalogue section 08: shop owner operations](../../references/functional/DOC/modules/08_shop_owner_operations/GUIDE.md), including its completion checks.
- [Catalogue section 04: shopping purchase assistance](../../references/functional/DOC/modules/04_shopping_purchase_assistance/GUIDE.md), including its completion checks.
- [Catalogue section 09: shop owner analytics](../../references/functional/DOC/modules/09_shop_owner_analytics/GUIDE.md), including its completion checks.

Historical feature guides may assign data to a dashboard module. Translate their ownership through the current module map and ERD table ownership; their old source-layout instructions are not the current code layout.

### Data and business rules

- 01-accounts-access: [overview](../../references/erd/DOC/05-modules/01-accounts-access/README.md), [business rules](../../references/erd/DOC/05-modules/01-accounts-access/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/01-accounts-access/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/01-accounts-access/ERD.md).
- 02-mall-shop-directory: [overview](../../references/erd/DOC/05-modules/02-mall-shop-directory/README.md), [business rules](../../references/erd/DOC/05-modules/02-mall-shop-directory/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/02-mall-shop-directory/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/02-mall-shop-directory/ERD.md).
- 03-products-catalogue: [overview](../../references/erd/DOC/05-modules/03-products-catalogue/README.md), [business rules](../../references/erd/DOC/05-modules/03-products-catalogue/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/03-products-catalogue/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/03-products-catalogue/ERD.md).
- 05-purchase-verification: [overview](../../references/erd/DOC/05-modules/05-purchase-verification/README.md), [business rules](../../references/erd/DOC/05-modules/05-purchase-verification/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/05-purchase-verification/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/05-purchase-verification/ERD.md).
- 06-reviews-moderation: [overview](../../references/erd/DOC/05-modules/06-reviews-moderation/README.md), [business rules](../../references/erd/DOC/05-modules/06-reviews-moderation/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/06-reviews-moderation/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/06-reviews-moderation/ERD.md).
- 09-analytics-reports: [overview](../../references/erd/DOC/05-modules/09-analytics-reports/README.md), [business rules](../../references/erd/DOC/05-modules/09-analytics-reports/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/09-analytics-reports/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/09-analytics-reports/ERD.md).

These are collaborating data domains, not a new dedicated schema for this module. Some requested capabilities have no table design yet. Do not invent missing entities or duplicate existing records.

Read [table ownership](../../references/erd/DOC/03-database/TABLE-OWNERSHIP.md) and [database guidance](../../06_DATABASE_GUIDE.md).

## Dependencies and checks

Related modules: AccountsAccess, ProductsCatalogue, PurchaseVerification, ReviewsModeration, AnalyticsReports. Review their contracts and authorization boundaries before changing shared behavior.

Relevant [open questions](../../CONFLICTS.md): C-01, C-02, C-04. [API guidance](../../07_API_CONTRACTS.md) applies before defining cross-module inputs, outputs and events.

Use the source guides' completion checks and [test guidance](../../09_TESTING.md); record actual results in [progress](../../PROGRESS.md). Keep business logic in Services and add patterns only when justified.

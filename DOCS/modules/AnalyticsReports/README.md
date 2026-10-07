# AnalyticsReports

## Responsibility

Shared metrics, shop/mall reports and evaluated forecasts. Source folder: [app/Modules/AnalyticsReports](../../../app/Modules/AnalyticsReports/).

Status: folder architecture only; this page routes to existing requirements, not implemented APIs.

## Read before implementation

Start with [the reading order](../../00_START_HERE.md) and [task checklist](../../IMPLEMENTATION_CHECKLIST.md).

### Detailed functional requirements

- [Catalogue section 09: shop owner analytics](../../references/functional/DOC/modules/09_shop_owner_analytics/GUIDE.md), including its completion checks.
- [Catalogue section 11: mall owner analytics decision support](../../references/functional/DOC/modules/11_mall_owner_analytics_decision_support/GUIDE.md), including its completion checks.

Historical feature guides may assign data to a dashboard module. Translate their ownership through the current module map and ERD table ownership; their old source-layout instructions are not the current code layout.

### Data and business rules

- 09-analytics-reports: [overview](../../references/erd/DOC/05-modules/09-analytics-reports/README.md), [business rules](../../references/erd/DOC/05-modules/09-analytics-reports/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/09-analytics-reports/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/09-analytics-reports/ERD.md).

Read [table ownership](../../references/erd/DOC/03-database/TABLE-OWNERSHIP.md) and [database guidance](../../06_DATABASE_GUIDE.md).

## Dependencies and checks

Related modules: ReviewsModeration, PurchaseVerification, ShopOwnerOperations, MallOwnerAdmin. Review their contracts and authorization boundaries before changing shared behavior.

Relevant [open questions](../../CONFLICTS.md): C-09. [API guidance](../../07_API_CONTRACTS.md) applies before defining cross-module inputs, outputs and events.

Use the source guides' completion checks and [test guidance](../../09_TESTING.md); record actual results in [progress](../../PROGRESS.md). Keep business logic in Services and add patterns only when justified.

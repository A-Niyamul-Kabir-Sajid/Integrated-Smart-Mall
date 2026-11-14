# AnalyticsReports

## Responsibility

Shared metrics, shop/mall reports and evaluated forecasts. Source folder: [app/Modules/AnalyticsReports](../../../app/Modules/AnalyticsReports/).

Status: visitor activity analytics implemented for EPIC-14 (search demand, page views, view-to-order funnel, daily summaries, retention, demonstration traffic); the metric catalogue, dashboards, monthly reports, exports and forecasts of EPIC-12 are not built; see [progress](../../PROGRESS.md) and decision D-016.

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

## Visitor activity metrics (EPIC-14, decision D-016)

Source tables `activity_search_events`, `activity_view_events`; summaries `activity_daily_summaries` (one row per mall day in the mall time zone, per metric, kind, subject and dataset label; formula version 1). Raw events hold no personal data; real and demonstration rows are separate.

| Code | Meaning and formula | Who may see it |
|---|---|---|
| `search_total` | Recorded first-page searches that day (value) and how many returned no result (secondary), masked searches included | Mall administrators |
| `search_term_count` | Searches whose normalised text equals the term, per search kind | Mall administrators |
| `search_term_zero_result_count` | The same, only searches with no result ("searched but not found") | Mall administrators |
| `search_category_count` / `search_floor_count` | Searches that used that category or floor filter | Mall administrators |
| `shop_page_views` | Views of the shop page (value) and distinct anonymous visitors that day (secondary) | Mall administrators; the shop's owner for their own shop |
| `product_page_views` | Views of the product page and distinct visitors that day; shared by every shop selling the product | Mall administrators; shop owners see the products they list |

Counting rules: one search or view per visitor per page per 30 minutes; only page one of a search; no crawlers, no accounts that run a shop or the mall; visitor-days add up daily distinct visitors and are not distinct people over a period. A page view is not a visit to the mall. The funnel (product views, orders containing the listing, completed sales containing the listing) is real traffic only. A change of formula gets a new metric code, never an edit of an old one.

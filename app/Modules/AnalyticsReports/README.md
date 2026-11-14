# AnalyticsReports

Metric definitions, shop and mall dashboards, monthly reports and evaluated forecasts.

- Namespace: `App\Modules\AnalyticsReports`
- Jira: EPIC-12 Analytics & Reports
- Requirements, business rules and ERD: [DOCS/modules/AnalyticsReports/README.md](../../../DOCS/modules/AnalyticsReports/README.md)
- Folder layout and coupling rules: [app/Modules/README.md](../README.md)

## Status

Visitor activity analytics implemented for EPIC-14; metric catalogue, monthly reports, exports and forecasts (EPIC-12) are not built. Progress is recorded in [DOCS/PROGRESS.md](../../../DOCS/PROGRESS.md) and the design in decision D-016 ([DOCS/DECISIONS.md](../../../DOCS/DECISIONS.md)).

- Owns (outside the ERD): `activity_search_events`, `activity_view_events`, `activity_daily_summaries`.
- Listens to the `VisitorActivity` event of SharedPlatform, which the public shop and product pages send; reads orders and sales through their tables for the funnel.
- Console: `php artisan analytics:rollup` (hourly) and `php artisan analytics:purge-events` (daily).
- Access: `/admin/malls/{mall}/analytics/*` needs `administer-mall`; `/workspace/shops/{shop}/analytics` needs `manage-shop`.

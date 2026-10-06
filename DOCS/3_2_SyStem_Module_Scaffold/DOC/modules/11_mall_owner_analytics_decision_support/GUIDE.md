# M11 — Mall-owner analytics and decision support

Status: module boundary confirmed by the user's catalogue correction; application not implemented or verified here. Feature inclusion in this guide does not mean every optional feature is committed to the first release.

## Catalogue scope — preserved from attachment
This module supports your idea of helping the owner understand shop performance.

- **Mall overview:** Summarise participating shops, platform activity, complaints, and ratings.
- **Shop performance comparison:** Compare shops using clearly defined metrics.
- **Category and floor analysis:** Compare demand across categories and locations.
- **Search-demand analysis:** Identify products visitors search for but cannot find.
- **Navigation-demand analysis:** Show frequently requested destinations and routes.
- **Review analysis:** Identify recurring praise and complaints.
- **Service-quality flags:** Highlight repeated unresolved complaints or declining ratings for investigation.
- **Monthly reports:** Generate summaries with charts, findings, and supporting data.
- **Performance forecasting:** Use regression or another appropriate method to estimate a defined outcome.
- **Admin assistant:** Answer questions such as “Which shops received the most cleanliness complaints this month?”
- **Data exports:** Export authorised data for further analysis.

**Keep measurements honest:** Navigation requests measure interest, not confirmed visits. Sales analysis needs purchase data, and footfall analysis needs an actual visitor-counting source.

## Ownership and write boundaries
Own mall-wide metric definitions, comparisons, monthly reports, performance forecasts and permission-scoped admin assistant. Read underlying records and share common metric definitions with M09; neither analytics module rewrites source transactions.

## Internal components
- `overview_comparisons/`
- `demand_analysis/`
- `review_analysis/`
- `monthly_reports/`
- `performance_forecasting/`
- `admin_assistant/`
- `exports/`

These are subcomponents within M11, not additional top-level modules. See [source folder](../../../modules/11_mall_owner_analytics_decision_support/README.md).

## Proposed contract
MallReport {mallId, period, metricVersion, sourceCutoff, metrics, coverage}; Forecast and AdminAnswer include evaluation/evidence and permission scope.

## Dependencies
M03, M04, M05, M07, M08, M09, M10, M12

Resolve IDs through the [central connecting file](../../MODULE_CONNECTIONS.md). Dependencies represent service/data contracts, not permission to import another module's database internals.

## Implementation guidance
1. Read the catalogue scope and confirm the release slice; preserve deferred features in this guide.
2. Inspect existing source, then identify owned records versus records read from other modules.
3. Define runtime-validated contracts and authorization rules using the shared integration specification.
4. Implement one complete user journey across the required subcomponents, including failure and empty states.
5. Integrate with upstream owners through explicit commands/queries; update affected consumers when contracts change.
6. Run the checks below and record evidence. Keep external integrations mocked until real access and behavior have been validated, and label every mock.

## Completion checks
- [ ] Monthly boundary fixtures match.
- [ ] Comparisons include count/coverage.
- [ ] Quality flags cite evidence.
- [ ] Navigation demand is not footfall.
- [ ] Forecasts avoid future leakage.
- [ ] Admin assistant cannot expose unauthorized records..

## Progress and handoff
Record actual source changes, contract changes, verification and prototype limitations in [PROGRESS.md](../../planning/PROGRESS.md). The guide is a scope/implementation reference, not a completion claim.

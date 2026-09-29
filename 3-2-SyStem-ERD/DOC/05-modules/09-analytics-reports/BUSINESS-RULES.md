# Analytics and Reports — business rules

These rules are part of the design. An ER diagram alone does not enforce them. Use [constraint enforcement](../../03-database/CONSTRAINT-ENFORCEMENT.md) to distinguish database constraints from transactional/application checks.

## METRIC_DEFINITION

- Use a new metric code when the definition changes; never silently change historical semantics.

## SHOP_METRIC_SNAPSHOT

- Unique tuple: `(shop_id, metric_code, period_start, period_end, source_cutoff_at, dataset_label)`.
- period_end > period_start; sample_size >= 0.
- Store review count and rating mean separately; do not turn zero observations into a zero rating.
- Separate shop-level and product-listing ratings in metric definitions; distinguish verified and unverified populations.

## REPORT_RUN

- period_end > period_start. Compute monthly boundaries in MALL.timezone, then store UTC instants.
- Scheduled jobs execute with explicit service authorization; a null requester is not public permission.

- dataset_label is real or demo. Every input snapshot and included forecast must use the same label; never mix them in one report.

## REPORT_SHOP_SCOPE

- Every shop belongs to the report mall. Record explicit rows even for an all-shops report.

## REPORT_METRIC_SNAPSHOT

- Snapshot shop is in REPORT_SHOP_SCOPE; period, cutoff, and intended dataset label match report configuration.

## REPORT_ARTIFACT

- size_bytes > 0. Access is authorized through the report mall before issuing a temporary download link.

## FORECAST_RUN

- training_end > training_start. Future observations must not enter training or preprocessing.
- Report insufficient data explicitly. Synthetic/demo forecasts are labelled and cannot substantiate real shop performance.

## FORECAST_TRAINING_SNAPSHOT

- Snapshot shop, target metric, and dataset label equal the run; each observation ends no later than training_end.
- Choose one source cutoff per training period; do not train on multiple revised copies of the same observation.

## FORECAST_POINT

- Unique tuple: `(forecast_run_id, period_start, period_end)`.
- period_end > period_start and period_start >= training_end.
- Interval bounds are both null or both present with lower_bound <= predicted_value <= upper_bound; state interval confidence/method in run parameters.

## REPORT_FORECAST

- Forecast shop belongs to REPORT_SHOP_SCOPE and dataset_label equals REPORT_RUN.dataset_label; clearly label forecast output separately from observed metrics.

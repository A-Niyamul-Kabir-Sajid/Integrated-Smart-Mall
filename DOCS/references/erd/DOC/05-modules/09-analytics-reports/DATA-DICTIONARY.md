# Analytics and Reports — data dictionary

All fields are required unless Nullable is Yes. Types are logical; see [shared conventions](../../03-database/CONVENTIONS.md) before generating SQL. PK, FK, and UK mean primary key, foreign key, and single-column unique key. Multiple PK fields form one composite primary key.

## METRIC_DEFINITION

Meaning and formula version for each metric.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| metric_code | varchar(80) | PK | No | — | Versioned key, such as mean_published_rating_v1. |
| name | varchar(120) | — | No | — | Metric display name. |
| unit | varchar(24) | — | No | — | count, rating_1_5, amount_BDT, or ratio. |
| formula_version | varchar(40) | — | No | — | Version of the calculation specification. |
| definition | text | — | No | — | Population, exclusions, aggregation, and time-boundary rules. |

Primary key: `(metric_code)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## SHOP_METRIC_SNAPSHOT

Frozen shop metric for a specific observation period and calculation cutoff.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| snapshot_id | uuid | PK | No | — | Snapshot identifier. |
| shop_id | uuid | FK | No | SHOP.shop_id | Measured shop. |
| metric_code | varchar(80) | FK | No | METRIC_DEFINITION.metric_code | Versioned metric definition. |
| period_start | timestamptz | — | No | — | Inclusive observation start. |
| period_end | timestamptz | — | No | — | Exclusive observation end. |
| source_cutoff_at | timestamptz | — | No | — | Latest source-update time included. |
| metric_value | decimal | — | Yes | — | Null when undefined or insufficient data. |
| sample_size | integer | — | No | — | Count of underlying observations. |
| dataset_label | varchar(16) | — | No | — | real or demo. |
| calculated_at | timestamptz | — | No | — | Calculation time. |

Primary key: `(snapshot_id)`.

Additional unique keys: `(shop_id, metric_code, period_start, period_end, source_cutoff_at, dataset_label)`.

Suggested query indexes: `(shop_id, metric_code, period_start)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## REPORT_RUN

One reproducible report request for a mall and period.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| report_run_id | uuid | PK | No | — | Report generation identifier. |
| mall_id | uuid | FK | No | MALL.mall_id | Report mall. |
| requested_by_user_id | uuid | FK | Yes | APP_USER.user_id | Authorized requester; null for a configured scheduled job. |
| report_type | varchar(32) | — | No | — | monthly_reviews or shop_performance. |
| period_start | timestamptz | — | No | — | Inclusive reporting start. |
| period_end | timestamptz | — | No | — | Exclusive reporting end. |
| source_cutoff_at | timestamptz | — | No | — | Data cutoff used by the run. |
| dataset_label | varchar(16) | — | No | — | real or demo; all report input snapshots and included forecasts use this label. |
| status | varchar(16) | — | No | — | queued, running, succeeded, or failed. |
| created_at | timestamptz | — | No | — | Request time. |
| completed_at | timestamptz | — | Yes | — | Completion time. |
| error_summary | text | — | Yes | — | Sanitized failure description. |

Primary key: `(report_run_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## REPORT_SHOP_SCOPE

Frozen list of shops included in a report run.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| report_run_id | uuid | PK, FK | No | REPORT_RUN.report_run_id | Owning report. |
| shop_id | uuid | PK, FK | No | SHOP.shop_id | Included shop. |

Primary key: `(report_run_id, shop_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## REPORT_METRIC_SNAPSHOT

Exact immutable metric snapshots used in a report.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| report_run_id | uuid | PK, FK | No | REPORT_RUN.report_run_id | Owning report. |
| snapshot_id | uuid | PK, FK | No | SHOP_METRIC_SNAPSHOT.snapshot_id | Included snapshot. |

Primary key: `(report_run_id, snapshot_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## REPORT_ARTIFACT

A generated downloadable output from a report run.

Scope: **Core**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| report_artifact_id | uuid | PK | No | — | Artifact identifier. |
| report_run_id | uuid | FK | No | REPORT_RUN.report_run_id | Generating report. |
| object_key | text | UK | No | — | Private file storage key. |
| format | varchar(12) | — | No | — | pdf, csv, or html. |
| sha256 | varchar(64) | — | No | — | File checksum. |
| size_bytes | bigint | — | No | — | Output size. |
| created_at | timestamptz | — | No | — | Output creation time. |

Primary key: `(report_artifact_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## FORECAST_RUN

Optional experimental regression run for one shop and metric.

Scope: **Prototype**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| forecast_run_id | uuid | PK | No | — | Experiment identifier. |
| shop_id | uuid | FK | No | SHOP.shop_id | Forecast subject. |
| metric_code | varchar(80) | FK | No | METRIC_DEFINITION.metric_code | Forecast target definition. |
| model_name | varchar(80) | — | No | — | Example linear_regression. |
| model_version | varchar(60) | — | No | — | Implementation or fitted model version. |
| training_start | timestamptz | — | No | — | Inclusive training period start. |
| training_end | timestamptz | — | No | — | Exclusive training period end. |
| parameters_json | json | — | No | — | Features, hyperparameters, preprocessing, and reproducibility details. |
| evaluation_json | json | — | Yes | — | Time-based holdout errors and sample counts. |
| dataset_label | varchar(16) | — | No | — | real or demo. |
| status | varchar(20) | — | No | — | succeeded, failed, or insufficient_data. |
| created_at | timestamptz | — | No | — | Run time. |

Primary key: `(forecast_run_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## FORECAST_TRAINING_SNAPSHOT

Exact historical snapshots used to train a forecast run.

Scope: **Prototype**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| forecast_run_id | uuid | PK, FK | No | FORECAST_RUN.forecast_run_id | Training run. |
| snapshot_id | uuid | PK, FK | No | SHOP_METRIC_SNAPSHOT.snapshot_id | Input observation. |

Primary key: `(forecast_run_id, snapshot_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## FORECAST_POINT

Predicted value for one future period.

Scope: **Prototype**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| forecast_point_id | uuid | PK | No | — | Predicted point identifier. |
| forecast_run_id | uuid | FK | No | FORECAST_RUN.forecast_run_id | Producing model run. |
| period_start | timestamptz | — | No | — | Inclusive forecast start. |
| period_end | timestamptz | — | No | — | Exclusive forecast end. |
| predicted_value | decimal | — | No | — | Point prediction in the target metric unit. |
| lower_bound | decimal | — | Yes | — | Optional validated interval lower bound. |
| upper_bound | decimal | — | Yes | — | Optional validated interval upper bound. |

Primary key: `(forecast_point_id)`.

Additional unique keys: `(forecast_run_id, period_start, period_end)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

## REPORT_FORECAST

Optional forecast runs included in a generated report.

Scope: **Prototype**.

| Field | Logical type | Keys | Nullable | References | Meaning |
| --- | --- | --- | --- | --- | --- |
| report_run_id | uuid | PK, FK | No | REPORT_RUN.report_run_id | Report containing forecasts. |
| forecast_run_id | uuid | PK, FK | No | FORECAST_RUN.forecast_run_id | Included experiment. |

Primary key: `(report_run_id, forecast_run_id)`.

Index each frequently joined FK unless already covered by a suitable primary/unique/query index. Composite-key order affects whether it covers that access pattern.

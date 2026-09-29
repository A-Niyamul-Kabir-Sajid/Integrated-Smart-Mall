# Module 09 — Analytics and Reports

Defined metrics, reproducible snapshots, monthly reports, and optional forecasts.

Status: proposed logical design v1.0; not an implemented or supervisor-approved database.

## Files

- [Full Mermaid source](ERD.mmd) and [Markdown rendering](ERD.md).
- [Data dictionary](DATA-DICTIONARY.md): all attributes, keys, nullability, references, and indexes.
- [Business rules](BUSINESS-RULES.md): constraints and lifecycle decisions.
- `ERD.svg`: ready-to-open vector preview.
- `views/`: smaller diagrams for detailed reading where supplied.

## Owned tables

| Table | Scope | Purpose |
| --- | --- | --- |
| METRIC_DEFINITION | Core | Meaning and formula version for each metric. |
| SHOP_METRIC_SNAPSHOT | Core | Frozen shop metric for a specific observation period and calculation cutoff. |
| REPORT_RUN | Core | One reproducible report request for a mall and period. |
| REPORT_SHOP_SCOPE | Core | Frozen list of shops included in a report run. |
| REPORT_METRIC_SNAPSHOT | Core | Exact immutable metric snapshots used in a report. |
| REPORT_ARTIFACT | Core | A generated downloadable output from a report run. |
| FORECAST_RUN | Prototype | Optional experimental regression run for one shop and metric. |
| FORECAST_TRAINING_SNAPSHOT | Prototype | Exact historical snapshots used to train a forecast run. |
| FORECAST_POINT | Prototype | Predicted value for one future period. |
| REPORT_FORECAST | Prototype | Optional forecast runs included in a generated report. |

## External references

| Referenced table | Owner |
| --- | --- |
| APP_USER | Accounts and Access |
| MALL | Mall and Shop Directory |
| SHOP | Mall and Shop Directory |

## Design explanation

METRIC_DEFINITION describes what is measured. SHOP_METRIC_SNAPSHOT freezes observations and their cutoff so reports can be reproduced. REPORT_SHOP_SCOPE freezes membership of a report, REPORT_METRIC_SNAPSHOT records exact input snapshots, and REPORT_ARTIFACT points to the generated file.

Suggested initial metrics: published shop-review count; mean published shop rating; product-review count; valid verified-product-review share; and recorded completed-sale amount. The last metric describes recorded evidence only, not total shop revenue unless purchase coverage is complete. Always disclose sample size, dataset label, period, exclusions, and formula version.

Use half-open periods [period_start, period_end). Monthly boundaries come from the mall timezone. New late-arriving data produces a new snapshot/cutoff rather than silently rewriting an old report. Report files are private and downloaded only after mall authorization.

FORECAST_RUN, FORECAST_TRAINING_SNAPSHOT, FORECAST_POINT, and REPORT_FORECAST are optional prototype tables. They record exactly which observations trained a model and keep predictions separate from measurements. Use time-based evaluation and report insufficient data rather than manufacturing a trend. Low ratings alone do not establish misconduct or identify a shop as polluting; use a clearly defined review flag and human investigation if that feature is desired.

## Update rule

Update this module, its cross-module contract, and the master diagram in the same change. Existing parent tables are referenced, never copied into this module. All current proposals and unresolved choices are listed in the shared design notes.

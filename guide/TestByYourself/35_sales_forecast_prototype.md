# 35 Sales forecast prototype

**Covers:** EPIC-12 ISM-125 / ISM-127 (forecasting experiment: target, dataset, baseline, evaluation, limits), AnalyticsReports
**Automated tests:** `tests/Feature/Modules/AnalyticsReports/ForecastTest.php`, `tests/Unit/AnalyticsReports/RegressionTest.php`, `ForecastingAlgorithmsTest.php`, `ComparisonAlgorithmsTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 25 minutes

## What you are proving

The forecast is clearly an estimate. It says what it predicts (recorded sales revenue per day), from which data, how much of that data is simulated, how it was chosen, how it did on days it never saw compared with simple baselines, and what it cannot know. The same data always gives the same forecast. A shop without enough history gets an explanation, not a made-up number.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**; adds simulated sales history).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).
5. `php artisan tinker guide/TestByYourself/scripts/show-links.php`. Keep: Cafe Brew's `sales forecast` as `CAFE_FORECAST`, Book Nook's `sales forecast` as `NOOK_FORECAST`, Easy Bank Service Point's `sales forecast` as `BANK_FORECAST`, and the mall's `Sales forecast` as `MALL_FORECAST`.

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>:

| Short | Account | Role |
|---|---|---|
| O | `owner@demo.test` | Cafe Brew owner |
| M | `mall.admin@demo.test` | Mall administrator |
| P | `platform@demo.test` | Platform administrator |
| C | `customer@demo.test` | Customer (must be refused) |

## Steps

### 1. Read the forecast (O)

1. Sign in as O and open `CAFE_FORECAST`.

**Expect:**
- The title carries the label `Estimate`.
- `Data and target`: the target (`Recorded sales revenue per day (BDT)`), the history used (about 150 days, all with a sale), the recorded revenue in it, `Simulated records: N of N recorded sales`, the training cutoff (yesterday), and a **run id** of 12 characters.
- A yellow note that part of the history is simulated demonstration data, so the forecast demonstrates the method and says nothing about a real shop.
- A chart with the last 42 days, the forecast for the next 14 days with a shaded 80% interval, and a dashed "repeat last week" baseline; below it a table with a day, the estimate, the interval and the baseline for each of the 14 days. Intervals never go below 0.
- `Limits you should know`: no knowledge of holidays, promotions, weather or footfall; the short history; the single hold-out window; the independence assumption of intervals; the simulated share.

### 2. How good is it? (O)

1. On the same page read `How good is it? Evaluation on days it never saw`.

**Expect:**
- A table with four methods: two baselines (`repeat last week`; `mean of the same weekday over four weeks`) and two models (`Ridge regression (weekly trend and weekday effects)`, `Holt–Winters smoothing (damped trend, weekly season)`), each with cross-validation MAE, hold-out MAE, RMSE, sMAPE, MASE and bias. One row is marked `chosen`: the one with the lowest cross-validation error.
- A verdict sentence, green or amber. If the chosen method did not beat the best baseline on the hold-out days, the page **says so** and tells you to treat the forecast as no better than repeating recent weeks. With this simulated, noisy data that outcome is normal and correct.
- Notes explaining that the last 14 days were never used to fit or choose, and how to read MAE, RMSE, MASE (below 1 beats repeating last week) and bias.

### 3. What the regression says (O)

1. Read `What the regression says about the history`.

**Expect:** the trend in BDT per week with its p-value, R², adjusted R², the Durbin–Watson statistic, and a weekday effect (relative to Monday) with a p-value for Tuesday to Sunday. Saturday is clearly above Monday in the simulated data. If the Durbin–Watson value is below 1.5 or above 2.5 a limitation line says the residuals are correlated.

### 4. Reproducible

1. Reload the page twice.

**Expect:** the run id, the forecast numbers and the evaluation table are identical every time.

2. The run id is a fingerprint of the history used. It changes only when a past day's sales change (a sale recorded today is not yet part of the history, which ends yesterday).

### 5. Not enough history (platform administrator)

Easy Bank Service Point has no listings and no sales in the demo, so it has no history.

1. Sign in as `platform@demo.test` (platform operators may open any shop's pages) and open `BANK_FORECAST`.

**Expect:** a grey box: `A forecast needs at least 56 days of history from the first sale and at least 21 days with a sale; this history has 0 days, 0 with a sale.` No chart, no numbers, still labelled `Estimate`, and the `Limits you should know` list.

### 6. The mall forecast (M)

1. Sign in as M and open `MALL_FORECAST`.

**Expect:** the same layout for the whole mall (all shops' recorded sales added up); amounts are larger; labelled `Estimate`.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | any forecast page | Redirect to sign in |
| O | `NOOK_FORECAST` (another shop) or `MALL_FORECAST` | 403 |
| M | `CAFE_FORECAST` | 403 |
| C | any forecast page | 403 |
| Anyone | a forecast that is not labelled an estimate, a forecast without its evaluation, or one from fewer than 56 days | Never |

## If something looks wrong

- The page says the history is too short right after `migrate:fresh` without `--seed`: the simulated history comes from the seed (`php artisan db:seed --class=DemoSalesHistorySeeder`).
- The numbers move after reseeding: the simulated history uses a fixed seed but is dated relative to today, so a new day shifts the window.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____

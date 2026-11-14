# 36 Monthly reports, summaries and CSV exports

**Covers:** EPIC-12 ISM-125 / ISM-126 (monthly report generation, summaries drafted from computed figures, exports), EPIC-14 ISM-175 / ISM-183 (visitor activity joined to the reports and exports), AnalyticsReports
**Automated tests:** `tests/Feature/Modules/AnalyticsReports/MonthlyReportTest.php`, `InsightsScreensTest.php`, `ShopDashboardTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 30 minutes

## What you are proving

A shop owner and the mall administrator can generate a report for a calendar month. It keeps the month's figures as they were when generated, compares them with the month before, and adds a short summary in which every sentence is written from the figures and names the metrics behind it. The report can be read on screen, printed or saved as PDF from the browser, and downloaded as CSV; the download carries the same numbers as the screen. A report can only be opened through the shop or mall it belongs to.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**; adds simulated sales, orders, reviews and complaints).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).
5. `php artisan tinker guide/TestByYourself/scripts/show-links.php`. Keep: Cafe Brew's `monthly reports` as `CAFE_REPORTS`, Book Nook's `monthly reports` as `NOOK_REPORTS`, and the mall's `Monthly reports` as `MALL_REPORTS`.

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>:

| Short | Account | Role |
|---|---|---|
| O | `owner@demo.test` | Cafe Brew owner |
| M | `mall.admin@demo.test` | Mall administrator |
| S | `staff@demo.test` | Cafe Brew cashier (must be refused) |
| C | `customer@demo.test` | Customer (must be refused) |

## Steps

### 1. Generate a report for last month (O)

1. Sign in as O and open `CAFE_REPORTS`: the page says `No report has been generated yet.`
2. Choose last month (the second entry of **Month**, for example the month before the current one) and press **Generate report**.

**Expect:** you are taken to `Monthly report: <Month Year>` with the shop name, the period (first to last day of the month, `Asia/Dhaka`), the time the data was read and `catalogue version 1`; then:
- `Summary`: sentences such as `Cafe Brew recorded N sales worth BDT X in <Month> (average BDT A per sale, U units). That is P% more than the BDT Y recorded in <previous month>.`, the orders and completion rate, the top product with its class, reviews and rating (and a trend sentence when the trend is significant), unusual days, and a closing note that the sales are simulated demonstration records. After each sentence the metric codes it rests on are listed in grey brackets, for example `[sales_count, sales_revenue, ...]`.
- `Figures`: a table with every shop metric for the month, the month before and the change in percent (stock figures are snapshots and show `—`).
- `Recorded sales per day` and `Top products`.

### 2. The numbers agree with the dashboard (O)

1. Note `Recorded sales revenue` in `Figures`.
2. Open `CAFE_DASH` (the dashboard of guide 34) with **From** the first and **To** the last day of that month and press **Show**.

**Expect:** the same revenue, sales, orders and rating. Check one sentence by hand: P% in the summary equals (this month − previous month) ÷ previous month from the `Figures` table.

### 3. Print and download (O)

1. On the report press **Print or save as PDF** and choose "Save as PDF" in the browser's print dialog.

**Expect:** the buttons and links disappear from the printed page; the summary, figures and tables fit the page.

2. Press **Download CSV**.

**Expect:** `monthly-report-<shop>-<YYYY-MM>.csv`. It starts with the report name, subject, period, catalogue version, the time the data was read and the simulated-records note; then `Metrics` with an extra column `previous month`, daily revenue, products, orders by status, promotions and unusual days. `sales_revenue` equals the figure on screen. Only Cafe Brew's data is in it.

### 4. A month that is not over, and generating again (O)

1. On `CAFE_REPORTS` generate the report for the **current month** (the first entry of **Month**).

**Expect:** a yellow note `This month is not over. The figures run up to <today>; generate the report again after the month ends.` The summary says `The month is not over, so these figures run up to <today>.` and the list on `CAFE_REPORTS` marks it `month not over`. The daily table stops at today.

2. Generate the same month again.

**Expect:** the list still holds one report for that month, now with a newer time: generating the same month again replaces it. (That a stored report is a snapshot and does not change when sales are recorded later is covered by the automatic test `test_the_stored_report_is_a_snapshot_and_regenerating_replaces_it`.)

### 5. Wrong input

The month list offers only the current month and the eleven before it, so a future month or a malformed month cannot be chosen on screen; the server refuses them anyway (`That month has not started yet.`, `Choose a month as year-month, for example 2026-10.`) and stores nothing. That is covered by `test_future_and_malformed_months_are_refused` and `test_a_bad_month_is_refused_with_a_message`.

### 6. The mall report (M)

1. Sign in as M and open `MALL_REPORTS`; generate last month's report.

**Expect:** a summary for the whole mall: total sales and the change on the month before, how many shops had a sale, the top shop and its share, complaints (filed, resolved, open, the most common category, the median time to resolve), reviews, search demand when there were real searches, the flags raised, the shop segments, and the sentence `Navigation demand is not measured yet because the maps module is not built; it would measure interest in destinations, not footfall.` Below: `Figures`, daily sales, a `Shops` table and the quality flags with their evidence. **Download CSV** adds the shop comparison, demand by category and floor, complaints by category and the flags.

### 7. Visitor activity in the exports (M)

1. Create a little real search traffic: in a private window open <http://127.0.0.1:8000/malls/niagara-city-mall/shops?q=umbrella>, then run `php artisan analytics:rollup`.
2. Press **Download CSV** on the mall dashboard of guide 34 (`MALL_DASH`).

**Expect:** the CSV ends with `Search and page views (real visitors)`, a `search_total` line that includes your search, `searches with no result`, and `shop_page_views`. The numbers equal `Searches (real visitors)` and `Shop page views (real visitors)` on the dashboard. Demonstration traffic is never included.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | any report page or download | Redirect to sign in |
| O | `NOOK_REPORTS`, or any report of another shop | 403 |
| O | a report id of another shop or of the mall opened under `CAFE_REPORTS`'s own URL | 404 |
| O | `MALL_REPORTS` | 403 |
| M | `CAFE_REPORTS` | 403 |
| M | a shop's report id under the mall's reports URL | 404 |
| S or C | any report page | 403 |
| Anyone | a report for a month that has not started, or a month written any other way than `YYYY-MM` | Refused with a message, nothing stored |
| Anyone | a report sentence with a figure that is not in the figures table | Never: every sentence is a template filled from the figures |

## If something looks wrong

- The summary is short: sentences are only written for figures that exist (no orders, no sentence about orders).
- The previous month shows `0`: the simulated history covers about 150 days, so a month older than that has no data.
- The page looks unstyled: run `npm run build`.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____

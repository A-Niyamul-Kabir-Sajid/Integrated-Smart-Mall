# 34 Sales and performance dashboards and the metric catalogue

**Covers:** EPIC-12 ISM-119 (metric catalogue, access matrix), ISM-122 (shop and mall dashboards), AnalyticsReports
**Automated tests:** `tests/Feature/Modules/AnalyticsReports/ShopDashboardTest.php`, `MallDashboardTest.php`, `MetricCatalogueTest.php`, `InsightsScreensTest.php`, `DemoSalesHistoryTest.php`, `tests/Unit/AnalyticsReports/` (the algorithms)
**Needs:** nothing else; this file is self-contained
**Time:** about 40 minutes

## What you are proving

The shop owner sees recorded sales, orders and reservations, product performance, stock, customer feedback and promotions for their own shop only. The mall administrator sees the whole mall: how shops compare (with counts, not just rankings), demand by category and floor, complaints, service-quality flags with evidence, and search demand. Every figure is defined in a catalogue that says where it comes from, its formula and who may see it. Sales come from recorded transactions only; navigation demand is shown as "not available yet" and never as footfall; simulated demonstration records are labelled as such.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**). The seed adds about 150 days of **simulated** sales, orders, reviews, complaints and stock for the demo shops (every simulated record is marked: receipts and orders start with `SIM-`, complaints with `SIM-`, reviewers use `@sim.demo.test`).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).
5. `php artisan tinker guide/TestByYourself/scripts/show-links.php`. Keep: the `Sales and performance` line under **MALL** as `MALL_DASH`, `Metric catalogue` as `MALL_METRICS`; and under **Cafe Brew** its `sales and performance` as `CAFE_DASH`, `metric catalogue` as `CAFE_METRICS`; under **Book Nook** its `sales and performance` as `NOOK_DASH`.

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>:

| Short | Account | Role |
|---|---|---|
| O | `owner@demo.test` | Cafe Brew owner |
| M | `mall.admin@demo.test` | Mall administrator |
| S | `staff@demo.test` | Cafe Brew cashier (must be refused) |
| C | `customer@demo.test` | Customer (must be refused) |

## Steps

### 1. The shop dashboard (O)

1. Sign in as O and open `CAFE_DASH` (or **Sales and performance** on the workspace home).

**Expect:**
- A yellow note `Demonstration data included` saying how many of the sales, orders and reviews are simulated (all of them on a fresh seed).
- Tiles: `Recorded sales revenue` (BDT, with the number of recorded sales), `Average sale value`, `Orders placed`, `Order completion rate`, `Returns and exchanges`, `Average rating`, `Listings out of stock`, `Promotion redemptions`.
- `Recorded sales per day`: one row for every day of the period (the last 30 days, ending today in mall time), days without sales show `0.00`. Today shows `0.00` because simulated history ends yesterday.
- `Product performance`: one row per active listing with revenue, units, share, class (for example `AX`) and stock; the classes are explained above the table.
- `Stock now`, `Orders and reservations by status`, `Customer feedback` (average, the star distribution, the rating trend, words that mark poor and good reviews when there are enough reviews), `Promotion performance` (empty message: no promotion was redeemed).
- Nothing about any other shop: the page never contains `Book Nook` or `Little Steps`.

### 2. The figures add up (O)

1. On `CAFE_DASH` note `Recorded sales revenue`.
2. Add up the `Revenue (BDT)` column of `Recorded sales per day`, and separately the `Revenue (BDT)` column of `Product performance`.

**Expect:** all three amounts are equal.

3. Check the figure against the records: `php artisan tinker --execute 'echo DB::table("purchases")->join("shops","shops.shop_id","=","purchases.shop_id")->where("shops.name","Cafe Brew")->where("purchases.status","completed")->where("purchases.purchased_at",">=",now("Asia/Dhaka")->subDays(29)->startOfDay()->utc())->sum("purchases.total_amount");'`

**Expect:** the same amount as `Recorded sales revenue` (voided and refunded sales are not counted; that is why the simple "all purchases" number would be higher).

### 3. The date filter and the download (O)

1. On `CAFE_DASH` set **From** and **To** to the same single past day and press **Show**: the bars show one day, the tiles shrink accordingly.
2. Set **From** to a date after **To**: an error says the end date must not be before the start date and nothing is shown. Set a period longer than 366 days: an error says to choose at most 366 days.
3. Choose a normal period again and press **Download CSV**.

**Expect:** a `.csv` file named `shop-dashboard-...`. Opened in a spreadsheet it starts with the report name, subject, period, catalogue version and the time the data was read; a `Simulated demonstration records included` note; `Metrics` (code, metric, value, unit) whose `sales_revenue` equals the tile; then daily revenue, products, orders by status, promotions and unusual days. It contains only Cafe Brew's data.

### 4. Unusual days and slow stock (O)

1. On `CAFE_DASH` look at `Unusual sales days` (use a period of at least 28 days; the default 30 is enough).

**Expect:** either a list of days with the usual revenue for that weekday and a z-score, or "No day stood out". A busy Saturday is **not** listed just for being a Saturday. If the period is shorter than 28 days the page says what is needed instead.

### 5. The mall dashboard (M)

1. Sign out; sign in as M and open `MALL_DASH` (or **Sales and performance** on the mall admin home).

**Expect:**
- Tiles for recorded sales revenue, shops with a recorded sale (for example `11 / 12`), orders, average rating, complaints, median time to resolve, searches and shop page views (zero for real visitors on a fresh seed; use guides 31–33 to create some).
- `Coverage: ...` stating how many shops have sales and reviews, so a ranking is never shown without its basis.
- `Shop comparison`: one row per shop with revenue, share, change against the same number of days before, sales, orders (collected / placed), raw rating and review count, the **adjusted** rating, views, complaints and segment. Shops with few reviews have an adjusted rating pulled towards the mall average (the explanation is above the table).
- `Segments of shops with activity`: shops grouped into 2 to 4 segments with their mean revenue and rating, with the silhouette score.
- `Demand by shop category` and `Demand by floor` (shops without a unit appear under `No floor assigned`).
- `Complaints to management` by category and by status; `Service-quality flags`.
- `Search and navigation demand`: `Navigation requests: Not available yet: ... (EPIC-05) ...`. Nothing here calls anything footfall.

### 6. Service-quality flags cite evidence (M)

On the fresh seed, with the default period, `Service-quality flags` shows:
- `Cafe Brew · Falling ratings` with the slope in stars per 30 days, the p-value and the number of reviews;
- `Phone Fix · Sales drop` with the percentage and both amounts;
- `Book Nook · Unresolved complaints` listing the complaint numbers (`SIM-C...`), and probably `Book Nook · Many complaints`.

**Expect:** each flag has a sentence of evidence; none says a shop is at fault.

### 7. Export and filter (M)

1. Press **Download CSV** on `MALL_DASH`; change the period and press **Show**.

**Expect:** a file `mall-dashboard-...csv` with metrics, a `Shops` table equal to the screen, demand by category and floor, complaints by category, the flags, and a `Navigation requests` line saying it is not available.

### 8. The metric catalogue (O, then M)

1. As O open `CAFE_METRICS`.

**Expect:** a card for each shop figure with `Formula`, `Method` (where an analysis is used), `Unit`, `Source` (table names), `Split by`, `Refresh` and `Who may see it`, plus the visitor metrics `shop_page_views` and `product_page_views`. It does **not** list `complaints_filed`, `navigation_requests` or `search_total`.

2. As M open `MALL_METRICS`.

**Expect:** the mall figures including `complaints_filed`, `search_total` and `navigation_requests` (marked `Not available yet`). It does **not** list `stock_units_on_hand`.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | `CAFE_DASH`, `MALL_DASH`, either download or catalogue | Redirect to sign in |
| O | `NOOK_DASH` (another shop) | 403 |
| O | `MALL_DASH`, `MALL_METRICS` | 403 |
| M | `CAFE_DASH`, `CAFE_METRICS` | 403: shop figures belong to the shop's owner |
| S (cashier) or C | `CAFE_DASH` or `MALL_DASH` | 403 |
| O | From after To, a period over 366 days, a non-date | An error, no figures |
| Anyone | a CSV of a shop holding another shop's rows | Never: the export is built from the same data as the screen |
| Anyone | a spreadsheet formula typed as a shop name | Exported with a leading apostrophe, so it does not run |

## If something looks wrong

- Everything shows zero: the simulated history is part of the seed; run `php artisan migrate:fresh --seed` or only `php artisan db:seed --class=DemoSalesHistorySeeder` (it replaces earlier simulated records and never touches real ones).
- The page looks unstyled: run `npm run build`.
- A figure differs from the database by a few sales near midnight: days follow the mall's time zone (Dhaka, six hours ahead of UTC), not UTC.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____

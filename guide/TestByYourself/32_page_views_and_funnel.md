# 32 Page views and the view-to-order funnel

**Covers:** EPIC-14 ISM-174 (shop and product views, funnel), AnalyticsReports
**Automated tests:** `tests/Feature/Modules/AnalyticsReports/ActivityCaptureTest.php`, `tests/Feature/Modules/AnalyticsReports/AnalyticsScreensTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 25 minutes

## What you are proving

Opening a shop page or a product page is counted anonymously, once per visitor per page per 30 minutes. The shop owner sees the views of their own shop and, per product, how many views became orders and recorded sales. The mall administrator sees every shop. Staff and crawlers are not counted, visitors cannot be followed from one day to the next, and no shop sees another shop's figures.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**; adds labelled demonstration traffic).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).
5. `php artisan tinker guide/TestByYourself/scripts/show-links.php`; keep: the `Page views` line as `VIEWS`; Cafe Brew's `page` URL as `CAFE_PAGE`; Cafe Brew's `visitors/views` URL as `CAFE_ANALYTICS`; one product page URL for a product Cafe Brew sells (see the `LISTINGS` and `PRODUCT PAGES` sections) as `PRODUCT_PAGE`.

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>:

| Short | Account | Role |
|---|---|---|
| C | `customer@demo.test` | Customer who views and orders |
| O | `owner@demo.test` | Cafe Brew owner |
| M | `mall.admin@demo.test` | Mall administrator |
| S | `staff@demo.test` | Cafe Brew cashier (must be refused) |

## Steps

### 1. Visit as an anonymous visitor

Use a normal browser window that is **not** signed in.

1. Open `CAFE_PAGE`, reload it twice.
2. Open `PRODUCT_PAGE`, reload it once.
3. Open `CAFE_PAGE` in a **different browser** (for example Firefox if you used Chrome): that is a second visitor.

**Expect:** the pages show as usual; later in step 5 the shop has **2** views (not 4) and the product **1**.

### 2. Visits that must not count

1. Sign in as O and open `CAFE_PAGE` and `PRODUCT_PAGE`; sign out. Do the same as M; sign out.
2. In a terminal: `curl CAFE_PAGE`.

**Expect:** none of these appear in step 5.

### 3. Place an order as a customer

1. Sign in as C, open `CAFE_PAGE` (this counts as a third view, so expect **3**), click **Order from this shop**, choose the product from step 1 (quantity 1), choose a pickup time, and send the order. Sign out.

### 4. Run the daily summary

1. `php artisan analytics:rollup`. Orders and sales are read live; views come from the summaries, which are also rebuilt every hour.

### 5. The shop owner's screen (O)

1. Sign in as O and open `CAFE_ANALYTICS` (or **Visitors and views** on the workspace home).

**Expect:** under `Real visitor traffic`:
- `Shop page views` 3 and `Visitor-days` 3 (one per distinct visitor, per day).
- A bar per day for the whole period; days with no views show 0.
- In the table `From view to order to sale, per product`, the product from step 1 shows product views 1 (shared with every shop that sells it), `Orders` 1 and `Orders per 100 views` 100; a product nobody viewed shows `—` for the rate, not 0 and not infinite.

2. Complete the order's sale on the orders screen (guide 22) and reload: `Recorded sales` rises by 1. A voided sale does not count.

### 6. The mall administrator's screen (M)

1. Open `VIEWS`.

**Expect:** `Shop page views` includes Cafe Brew's 3 and every other shop's views of the real traffic (none for the demo shops); the lists `Shops` and `Products` show names with views and visitor-days. Another mall's figures never appear.

### 7. Demonstration data

1. Choose `Demonstration data (seeded, not real visitors)` in **Data** on `VIEWS`: busy demo numbers appear under a yellow note.
2. As O choose the same on `CAFE_ANALYTICS`: the orders and sales columns show `—` and a note says demonstration views are not matched with real orders.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | `VIEWS` or `CAFE_ANALYTICS` | Redirect to sign in |
| S (cashier) or any customer | `CAFE_ANALYTICS` | 403 |
| M (mall administrator, not a platform operator) | `CAFE_ANALYTICS` | 403: shop analytics belong to the shop's owner |
| O | `VIEWS` | 403: mall-wide figures belong to the mall office |
| Owner of another shop | `CAFE_ANALYTICS` of Cafe Brew | 403 |
| Anyone | the stored data | There is no user, name, email, phone number or address stored with a view; the visitor key changes every day |

## If something looks wrong

- Numbers are zero: run `php artisan analytics:rollup` first.
- A reload did not count: that is the 30 minute rule. Use another browser, or wait.
- A page view is not a visit to the mall, and product views are shared by every shop that sells the same product.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____

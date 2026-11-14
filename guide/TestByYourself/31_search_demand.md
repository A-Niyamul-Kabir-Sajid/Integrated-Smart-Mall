# 31 Search demand and "searched but not found"

**Covers:** EPIC-14 ISM-172 / ISM-173 (search events, privacy rules, unmet-demand analysis), AnalyticsReports
**Automated tests:** `tests/Feature/Modules/AnalyticsReports/ActivityCaptureTest.php`, `tests/Unit/AnalyticsReports/SearchTermSanitizerTest.php`, `tests/Feature/Modules/AnalyticsReports/AnalyticsScreensTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 20 minutes

## What you are proving

Searches on the public shop and product search are counted anonymously. The mall administrator sees the most searched terms and, more usefully, the terms that found nothing. Text that looks like an email address or a phone number is counted but never stored. Reloads, later pages, crawlers and the mall's own staff do not inflate the numbers. Nobody else can see the figures.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**). The seed adds labelled **demonstration** traffic; you will not see it unless you ask for it (step 6).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).
5. `php artisan tinker guide/TestByYourself/scripts/show-links.php`; keep the `Search demand` line as `SEARCH_DEMAND`.

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>:

| Short | Account | Role |
|---|---|---|
| M | `mall.admin@demo.test` | Mall administrator |
| O | `owner@demo.test` | Cafe Brew owner (must be refused, and their own searches are not counted) |

## Steps

### 1. Search as an anonymous visitor

Use a normal browser window that is **not** signed in (a private window is fine).

1. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops?q=umbrella> (no shop matches).
2. Open <http://127.0.0.1:8000/malls/niagara-city-mall/products?q=Umbrella> (no product matches; if your demo catalogue has one, use another word nobody sells).
3. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops?q=cafe> (finds Cafe Brew).
4. Reload the `cafe` page three times, then open <http://127.0.0.1:8000/malls/niagara-city-mall/shops?q=CAFE%20> (different case, trailing space).

**Expect:** the pages work normally. In steps 5 to 7 the `cafe` searches still count as **one**.

### 2. Text that looks personal

1. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops?q=someone@example.test>.
2. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops?q=01712345678>.

**Expect:** the pages work, but the text is never saved (checked in step 5).

### 3. Searches that must not be counted

1. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops?q=cafe&page=2>, <http://127.0.0.1:8000/malls/niagara-city-mall/shops?q=> and <http://127.0.0.1:8000/malls/niagara-city-mall/shops?category=food-drinks> (page 2, empty text, a filter only).
2. In a terminal: `curl "http://127.0.0.1:8000/malls/niagara-city-mall/shops?q=crawlertest"`.
3. Sign in as O and search `ownersearch` in the shop search; sign in as M and search `adminsearch`; sign out.

**Expect:** none of these appear in the report: not a later page, not an empty text, not a filter-only browse, not a command-line client (`curl` is treated as a crawler), not the shop owner and not the mall administrator.

### 4. Run the daily summary

1. `php artisan analytics:rollup`. It prints `Wrote N summary rows.` The dashboards read the summaries, and the same job runs every hour on its own.

### 5. Read the report (M)

1. Sign in as M and open `SEARCH_DEMAND`.

**Expect:** under `Real visitor traffic`, period = the last 30 days, and:

| What | Value |
|---|---|
| Searches | 5 (umbrella in the shop search, umbrella in the product search, cafe once, and the two personal-looking ones) |
| With no result | 4 (the two umbrella searches and the two personal-looking ones) |
| Text not stored (looked personal) | 2 |
| Searched but not found | `umbrella` with 2 |
| Most searched terms | `umbrella` 2, `cafe` 1; **no** email address or phone number anywhere on the page |

2. Pick `Product search` in the **Search** list and click **Show**: only `umbrella` (1) remains. Change **From**/**To** to a past week: `No searches were recorded in this period.`

### 6. Demonstration data is separate

1. Choose `Demonstration data (seeded, not real visitors)` in **Data** and click **Show**.

**Expect:** a yellow note says the numbers were generated for demonstration; terms such as `charger`, `umbrella`, `toy` and `gift card` appear under `Searched but not found`, `coffee` and `shoes` do not. Switching back to real traffic shows only your own five searches again.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out | `SEARCH_DEMAND` | Redirect to sign in |
| O (shop owner) | `SEARCH_DEMAND` | 403: search terms belong to the mall office only |
| Any customer | `SEARCH_DEMAND` | 403 |
| Administrator of another mall | `SEARCH_DEMAND` of this mall | 403 |
| M | `SEARCH_DEMAND`&from=2026-03-10&to=2026-03-01 | Error `The end date must not be before the start date.` |
| M | `SEARCH_DEMAND`&dataset=invented, or a period over 366 days | Error, nothing shown |

## If something looks wrong

- Numbers are zero: run `php artisan analytics:rollup` (the summaries are rebuilt hourly, not live).
- A search was not counted: the same visitor repeating a search within 30 minutes counts once; use another word or wait.
- Behind a proxy every visitor may look alike (same address and browser), which merges them into one visitor.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____

# 33 Daily summaries, retention and demonstration traffic

**Covers:** EPIC-14 ISM-175 (roll-ups, retention, demonstration traffic, dashboards), AnalyticsReports
**Automated tests:** `tests/Feature/Modules/AnalyticsReports/ActivityRollupTest.php`, `tests/Feature/Modules/AnalyticsReports/DemoTrafficTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 20 minutes

## What you are proving

Raw search and view events are summarised into daily figures that the dashboards read; running the summary again changes nothing; days follow the mall's time zone; raw events older than the retention period are deleted only after their day was summarised, and the summaries stay; demonstration traffic is labelled, repeatable and never mixed with real traffic.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).
5. `php artisan tinker guide/TestByYourself/scripts/show-links.php`; keep the `Search demand` line as `SEARCH_DEMAND` and the `Page views` line as `VIEWS`.

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Account (password `password`), sign in at <http://127.0.0.1:8000/login>: `mall.admin@demo.test` (M).

## Steps

### 1. The summary is repeatable

1. `php artisan analytics:rollup --days=3`; note `Wrote N summary rows.`
2. Run it again.

**Expect:** the same N both times, and no duplicated rows in the dashboards.

### 2. Demonstration traffic is labelled and kept apart

1. As M open `SEARCH_DEMAND` and `VIEWS` with no choice made: they show **Real visitor traffic**, which is empty on a fresh database (`No searches were recorded in this period.`).
2. Choose `Demonstration data (seeded, not real visitors)`: 30 days of figures appear with a yellow note; `umbrella` and `charger` are under `Searched but not found`.
3. Note the total on `SEARCH_DEMAND` with the demonstration data. Run `php artisan db:seed --class=DemoActivitySeeder` and reload.

**Expect:** the total is **the same** (the seeder replaces the earlier demonstration rows; it never adds to them and never touches real rows).

### 3. Real and demonstration never mix

1. In a private window open <http://127.0.0.1:8000/malls/niagara-city-mall/shops?q=mixtest>, run `php artisan analytics:rollup`, reload `SEARCH_DEMAND` as M.

**Expect:** under real traffic `mixtest` is the only term and the total is 1. Under demonstration data `mixtest` does not appear and the total has not changed.

### 4. Retention keeps the summary and deletes the old events

1. Insert an old event and a recent one: `php artisan tinker --execute '$m=\App\Modules\MallShopDirectory\Models\Mall::first(); foreach ([[100,"retentionold"],[5,"retentionnew"]] as [$d,$t]) \App\Modules\AnalyticsReports\Models\SearchEvent::create(["mall_id"=>$m->getKey(),"kind"=>"shop","term"=>$t,"term_masked"=>false,"result_count"=>0,"signed_in"=>false,"dataset_label"=>"real","occurred_at"=>now()->subDays($d)]);'`
2. `php artisan analytics:purge-events`

**Expect:** `Deleted 1 search events and 0 view events.` (the 100-day-old one; the retention period is 90 days, `ANALYTICS_RETENTION_DAYS`). Then:
- `php artisan tinker --execute 'echo \App\Modules\AnalyticsReports\Models\SearchEvent::pluck("term")->implode(",");'` lists `retentionnew` but not `retentionold`.
- `php artisan tinker --execute 'echo \App\Modules\AnalyticsReports\Models\ActivitySummary::where("subject_key","retentionold")->count();'` prints `2` (the term's search count and its no-result count): the old day was summarised before its event was deleted.
3. Run `php artisan analytics:purge-events` again: `Deleted 0 search events and 0 view events.`

### 5. The jobs are scheduled

1. `php artisan schedule:list`

**Expect:** `analytics:rollup` hourly and `analytics:purge-events` daily at 03:30.

### 6. Days follow the mall time zone

1. Dhaka is six hours ahead of UTC. Insert an event at 18:30 UTC yesterday: it belongs to *today* in Dhaka. `php artisan tinker --execute '$m=\App\Modules\MallShopDirectory\Models\Mall::first(); \App\Modules\AnalyticsReports\Models\SearchEvent::create(["mall_id"=>$m->getKey(),"kind"=>"shop","term"=>"tzcheck","term_masked"=>false,"result_count"=>0,"signed_in"=>false,"dataset_label"=>"real","occurred_at"=>now("UTC")->subDay()->setTime(18,30)]);'` then `php artisan analytics:rollup --days=3`.
2. `php artisan tinker --execute 'echo \App\Modules\AnalyticsReports\Models\ActivitySummary::where("subject_key","tzcheck")->value("day");'`

**Expect:** the date printed is **today's date in Dhaka** (not yesterday's UTC date).

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| Signed out, customers, shop owners, other malls' administrators | `SEARCH_DEMAND` or `VIEWS` | Redirect to sign in / 403 |
| M | choosing a period over 366 days, or an end before the start | Error shown, no figures |
| Anyone | seeing demonstration traffic inside a report labelled real | Never: the two are separate rows with separate labels |
| Anyone | editing or deleting a stored search or view in the application | There is no such screen; a recorded event cannot be changed |

## If something looks wrong

- Dashboards are empty after real visits: run `php artisan analytics:rollup` (hourly otherwise).
- Windows PowerShell quoting: if a `tinker --execute` line fails, put the same PHP in a file and run `php artisan tinker file.php`.
- Retention only deletes raw events; to keep them longer set `ANALYTICS_RETENTION_DAYS` in `.env`.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____

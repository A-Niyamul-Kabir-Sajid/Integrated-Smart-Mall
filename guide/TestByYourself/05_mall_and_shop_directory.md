# 05 Mall home and shop directory

**Covers:** EPIC-03 (MallShopDirectory), ISM-27 to ISM-37
**Automated tests:** `tests/Feature/Modules/MallShopDirectory/Http/CustomerDirectoryTest.php`, `.../ShopDetailTest.php`, `.../DirectoryQueryCountTest.php`, `.../Services/*`, `.../Models/*`
**Needs:** nothing else; this file is self-contained (no sign-in needed)
**Time:** about 10 minutes

## What you are proving

Anyone can browse the mall, search and filter shops, and open a shop with correct opening hours; draft and unknown shops stay hidden.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running.

PHP too old? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

## Steps

### 1. Mall home

1. Open <http://127.0.0.1:8000/>.

**Expect:** redirect to <http://127.0.0.1:8000/malls/niagara-city-mall> (only one active mall). The page shows categories with shop counts, floors and a shop search box. Draft shop Tech Galaxy is not counted.

### 2. Directory, search and filters

1. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops> (**Shop directory**).

**Expect:** shop cards with location (floor and unit) and an open/closed badge, paginated.

2. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops?q=coffee> or type `coffee` into **Search shops**.

**Expect:** Cafe Brew appears; the term stays in the box and after paging.

3. Use **Category** Food & Drinks, then **Floor**, then tick **Open now**, **Filter**.

**Expect:** only matching shops; filters stay selected; **Clear filters** resets. A filter with no match shows "No shops match these filters."

4. Change **Sort by** to Name A–Z, Name Z–A, Newest.

**Expect:** the order changes accordingly.

5. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops?sort=banana> and <http://127.0.0.1:8000/malls/niagara-city-mall/shops?floor=notanid>.

**Expect:** refused (validation error / redirect), not a crash.

### 3. Shop detail

1. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops/cafe-brew>.

**Expect:** profile, floor and unit `G-01`, contact, a weekly table of opening hours with "Times are in Asia/Dhaka", and the reviews block.

2. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops/juice-corner> (open until 01:00) and <http://127.0.0.1:8000/malls/niagara-city-mall/shops/pharma-care> (24 hours).

**Expect:** overnight and 24-hour hours display correctly. Open/closed matches the current time in Dhaka, not your PC's timezone.

3. Open <http://127.0.0.1:8000/malls/niagara-city-mall/shops/book-nook>.

**Expect:** Friday opens at 15:00; every other day at 10:00 (Sunday to Thursday and Saturday).

### 4. Closed and hidden shops

| Try | Expect |
|---|---|
| <http://127.0.0.1:8000/malls/niagara-city-mall/shops/sports-zone> | Shown as **Closed**, with no hours and no location |
| <http://127.0.0.1:8000/malls/niagara-city-mall/shops/tech-galaxy> | 404 (draft) |
| <http://127.0.0.1:8000/malls/niagara-city-mall/shops/no-such-shop> | 404 |
| <http://127.0.0.1:8000/malls/no-such-mall> | 404 |

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____

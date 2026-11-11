# 00 Prerequisites and demo data (reference)

**Covers:** EPIC-00 setup, `DemoMallSeeder`, `DemoAccountsSeeder`
**Automated tests:** `tests/Feature/Seeders/DemoMallSeederTest.php`, `tests/Feature/Seeders/DemoAccountsSeederTest.php`
**Needs:** nothing. Every other file repeats the setup it needs, so this file is only a reference.
**Time:** about 10 minutes the first time

## Start the app

1. Start Docker Desktop, then in the project folder: `docker compose up -d` (PostgreSQL on 127.0.0.1:5432).
2. `php artisan migrate:fresh --seed` creates a clean database with the demo mall, products and accounts. **It deletes all local data.**
3. `npm run build` once (or keep `npm run dev` running). Without it pages look unstyled.
4. `php artisan serve`, then open <http://127.0.0.1:8000>. `composer run dev` starts the server, queue, logs and Vite together.

**PHP version:** Laravel 13 needs PHP 8.3 or newer. If `php artisan` says "require a PHP version >= 8.4.1", the PHP first on your PATH is too old (XAMPP ships 8.2). Call the right one explicitly: `C:\Users\Shomik\.php\8.5\php.exe artisan serve`.

## Demo accounts

Sign in at <http://127.0.0.1:8000/login>. Every password is `password`.

| Email | Role | Used for |
|---|---|---|
| `customer@demo.test` | Customer | Browsing, reviews, reporting |
| `owner@demo.test` | Shop owner of Cafe Brew | Shop workspace, selling, replies |
| `staff@demo.test` | Shop staff of Cafe Brew | Same as owner but cannot manage the shop |
| `mall.admin@demo.test` | Mall administrator of Niagara City Mall | Moderation desk |
| `platform@demo.test` | Platform administrator | Passes every ability check |
| `test@example.com` | Plain account from `DatabaseSeeder` (no role) | "Someone with no role" checks |

## Demo data worth knowing

- One active mall: **Niagara City Mall**, slug `niagara-city-mall`, timezone Asia/Dhaka, 2 floors, 14 shops. Home: <http://127.0.0.1:8000/malls/niagara-city-mall>
- Most shops are active. **Sports Zone** is *closed* (listed, no hours, no location); **Tech Galaxy** is a *draft* (never visible to customers).
- 19 shared products with variants. Cafe Brew sells Cold Coffee (Regular, Large), Espresso and Butter Croissant. Mineral Water 500 ml is sold by four shops.
- Currency is BDT (shown with the ৳ sign).

## Print every link and id

Ids are random UUIDs and change after every `migrate:fresh --seed`. This prints, read-only, every full URL the guide files use for the data that exists right now (mall, shops, workspaces, CSV import pages, listings, product pages, moderation desk, existing reviews):

```text
php artisan tinker guide/TestByYourself/scripts/show-links.php
```

Other URLs in the guide have no id and are written out in full.

## Limits you may hit

| Limit | Value | If you hit it |
|---|---|---|
| Sign in | 5 attempts per minute per login | Wait one minute (HTTP 429) |
| Registration | 10 per hour per IP | Wait, or run `php artisan cache:clear` (the cache is the database) |
| Password reset | 3 per minute per IP | Wait one minute |
| Search pages | 60 per minute | Wait one minute |
| Review submissions | 10 per minute per account | Wait one minute |

## Reset to a clean state

`php artisan migrate:fresh --seed` returns everything, including purchase codes, reviews and any accounts you registered, to the seeded state.

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____

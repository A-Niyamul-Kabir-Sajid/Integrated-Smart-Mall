# 13 Access control: who may open what

**Covers:** EPIC-02 ISM-24 to ISM-26 and the protection of every later route
**Automated tests:** `tests/Feature/Security/RouteProtectionTest.php`, `tests/Feature/Modules/AccountsAccess/AbilitiesTest.php`, `.../Http/Middleware/AccessControlTest.php`, plus the "kept out" tests in each workspace and moderation test file
**Needs:** nothing else; this file is self-contained (it creates the one review it needs)
**Time:** about 25 minutes

## What you are proving

Every protected page answers correctly for every role: guests go to sign in, wrong roles get 403, and ids that belong to someone else's shop or mall never leak.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running.

PHP too old? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>; a private window per account saves time:

| Short | Account | Role |
|---|---|---|
| G | (signed out) | Guest |
| C | `customer@demo.test` | Customer |
| O | `owner@demo.test` | Owner of Cafe Brew |
| S | `staff@demo.test` | Staff of Cafe Brew |
| M | `mall.admin@demo.test` | Mall administrator |
| P | `platform@demo.test` | Platform administrator |

### Create one review of another shop

1. Sign in as `customer@demo.test`, open <http://127.0.0.1:8000/malls/niagara-city-mall/shops/fresh-mart/reviews>, choose 3 stars, body `Fresh Mart is fine.`, **Publish review**. Sign out.

### Collect the ids

Run `php artisan tinker guide/TestByYourself/scripts/show-links.php` and keep these full URLs:

| Name | Where in the output |
|---|---|
| `CAFE_WS` / `CAFE_INBOX` | **SHOPS**, Cafe Brew: `workspace` and `review inbox` |
| `FRESH_WS` / `FRESH_INBOX` | **SHOPS**, Fresh Mart: `workspace` and `review inbox` |
| `FRESH_LISTING_ID` | **LISTINGS**, any Fresh Mart line (the id column) |
| `FRESH_REVIEW_ID` | **REVIEWS THAT EXIST**, the Fresh Mart line (the id column) |
| `DESK` | **MALL**, `Moderation desk` |
| `LISTING_PAGE` | **LISTINGS**, the review-page URL of any Cafe Brew line |

## How abilities are decided

| Ability | Who passes |
|---|---|
| `administer-platform` | Platform admin |
| `administer-mall` (for one mall) | Platform admin, or an active mall admin of that mall |
| `manage-shop` (for one shop) | Platform admin, or an active owner of that shop |
| `work-in-shop` (for one shop) | Platform admin, or an active owner or staff member of that shop |

A suspended or deactivated account passes nothing and is signed out on its next request.

## The matrix

Cells: ✔ page opens, 403 forbidden, → redirect to <http://127.0.0.1:8000/login>.

| URL | G | C | O | S | M | P |
|---|---|---|---|---|---|---|
| <http://127.0.0.1:8000/>, <http://127.0.0.1:8000/malls/niagara-city-mall>, <http://127.0.0.1:8000/malls/niagara-city-mall/shops>, <http://127.0.0.1:8000/malls/niagara-city-mall/products> | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `LISTING_PAGE` (read reviews) | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| <http://127.0.0.1:8000/login>, <http://127.0.0.1:8000/register> | ✔ | → account | → account | → account | → account | → account |
| <http://127.0.0.1:8000/account>, <http://127.0.0.1:8000/account/profile>, <http://127.0.0.1:8000/account/password>, <http://127.0.0.1:8000/account/preferences>, <http://127.0.0.1:8000/account/reviews> | → | ✔ | ✔ | ✔ | ✔ | ✔ |
| `CAFE_WS` and `CAFE_INBOX` | → | 403 | ✔ | ✔ | 403 | ✔ |
| `FRESH_WS` and `FRESH_INBOX` | → | 403 | 403 | 403 | 403 | ✔ |
| `DESK` | → | 403 | 403 | 403 | ✔ | ✔ |

("→ account" means you are redirected to <http://127.0.0.1:8000/account> because you are already signed in.)

## Cross-tenant checks

Signed in as `owner@demo.test`:

| Try | Expect |
|---|---|
| `CAFE_WS/<FRESH_LISTING_ID>/edit` | 404 |
| `POST CAFE_INBOX/<FRESH_REVIEW_ID>/reply` (edit the action of a reply form in F12, or use curl with a session cookie and CSRF token) | 404 |
| `CAFE_WS/not-a-uuid/edit` | 404 |
| <http://127.0.0.1:8000/workspace/shops/00000000-0000-0000-0000-000000000000/catalogue> (an id that does not exist) | 404 |

## Other protective behaviour

| Try | Expect |
|---|---|
| Submit any form after leaving the page open for a long time, or with the `_token` field removed (F12) | HTTP 419 |
| Sign-in attempts (6 per minute), registration (11 per hour), password reset (4 per minute), password change (6 per minute), review writes (11 per minute) | HTTP 429 |
| Draft shop <http://127.0.0.1:8000/malls/niagara-city-mall/shops/tech-galaxy> or <http://127.0.0.1:8000/malls/no-such-mall> | 404 |

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____

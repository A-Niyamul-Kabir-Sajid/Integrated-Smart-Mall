# 14 Automatic tests: what exists and how to run it

**Covers:** the whole project
**Needs:** nothing else; this file is self-contained
**Time:** about 5 minutes; the full suite takes a few minutes

Manual checks prove what a person sees. The automatic suite proves the same rules on every change. Run both.

## Setup

1. `composer install` (once).
2. PHP 8.3 or newer must run the commands. If `php artisan` says it needs PHP >= 8.4.1, prefix every command with the right PHP: `C:\Users\Shomik\.php\8.5\php.exe artisan test --compact`.
3. Nothing else: the suite uses an in-memory SQLite database and never touches your development database. For the PostgreSQL run only, start Docker (`docker compose up -d`).

## Commands

| Goal | Command |
|---|---|
| Whole suite, in-memory SQLite (fast) | `php artisan test --compact` |
| One folder or file | `php artisan test --compact tests/Feature/Modules/ReviewsModeration` |
| One test | `php artisan test --compact --filter=test_a_wrong_purchase_code_publishes_nothing` |
| Same suite on PostgreSQL (needs Docker running) | `composer test:pgsql` |
| Code style (fix) | `vendor/bin/pint --dirty --format agent` |
| Code style check plus suite | `composer ci` |
| Dependency advisories | `composer audit` |
| Documentation links | `python DOCS/maintenance/validate_docs.py` |

Expected: all pass; two tests are skipped (recorded in `DOCS/PROGRESS.md`). The docs validator reports one known hash mismatch for `archive/project-guide/legacy-inventory/CURRENT_DIRECTORY_INVENTORY.txt`.

## Where tests live and what each area covers

| Area | Folder / file | Proves | Manual guide |
|---|---|---|---|
| Boot, health, routes | `tests/Feature/Smoke/` | App starts, `/up` works, database ready | 01 |
| Core | `tests/Feature/Core/` | Request ids, security headers, module registration and event flow, rule violations | 01 |
| Architecture rules | `tests/Unit/Architecture/` | Modules do not reach into each other; Core stays independent | 01 |
| Shared platform | `tests/Feature/Modules/SharedPlatform/` | Audit log, rate limiters, locale, notifications, SMS channel, secure defaults | 01, 04 |
| Accounts | `tests/Feature/Modules/AccountsAccess/` | Registration, sign-in, password flows, profile, roles, memberships, abilities | 02, 03, 13 |
| Preferences and favourites | `tests/Feature/Modules/FavouritesPreferences/` | Own preferences only, language switch, preference lookup, Bangla messages, saved shops and offers, shopping lists, ownership | 04, 15, 16, 17 |
| Directory | `tests/Feature/Modules/MallShopDirectory/` | Models, opening hours, search, filters, shop pages, query counts | 05 |
| Products | `tests/Feature/Modules/ProductsCatalogue/` | Catalogue rules, search, product pages, comparison | 06 |
| Shop workspace | `tests/Feature/Modules/ShopOwnerOperations/` | Catalogue screens, CSV import, review inbox and replies | 07, 08, 11 |
| Purchases | `tests/Feature/Modules/PurchaseVerification/` | Sale records, credential lifecycle, redemption rules | 09 |
| Reviews | `tests/Feature/Modules/ReviewsModeration/` | Data layer, review service, rating maths, pages, reports, moderation | 10, 12 |
| Facilities and parking | `tests/Feature/Modules/ParkingFacilities/`, `tests/Feature/Modules/MallShopDirectory/Services/FacilityServiceTest.php`, `tests/Unit/ParkingFacilities/` | Facility rules and finder, admin screens, parking zones, saved spot privacy, directions back, simulated occupancy | 18, 19 |
| Route protection | `tests/Feature/Security/RouteProtectionTest.php` | Every route that needs sign-in or an ability is protected | 13 |
| Seeders | `tests/Feature/Seeders/` | Demo mall and accounts are seeded consistently | 00 |

The numbers are the files in `guide/TestByYourself/` (for example `10_verified_reviews_customer.md`).

## Conventions for new tests

- Name tests `test_<result>_<condition>`.
- Use factories, never the demo seeders (only the seeder tests use them).
- Feature tests extend `Tests\TestCase`; pure logic uses `PHPUnit\Framework\TestCase`.
- Cover success, validation failure, authorization and tenant isolation.
- Every new slice also adds a self-contained manual file here (see the folder README).
- Details: `DOCS/09_TESTING.md`.

## Known gaps

- `composer test:pgsql` passed on 2026-11-12 (527 tests, including EPIC-06 to EPIC-09); true parallel redemption of one code is still unproven.
- No automatic browser tests exist; the manual files in this folder cover the visible behaviour.

## Result

Date: ____  Tester: ____  Command: ____  Passed / failed / skipped: ____

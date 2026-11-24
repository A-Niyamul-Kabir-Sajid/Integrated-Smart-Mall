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
| Map pipeline, inside Blender (needs Blender 4.2+, about 15 s) | `& "C:\Program Files\Blender Foundation\Blender 5.3\blender.exe" -b --factory-startup --python-exit-code 1 -P tests/Blender/test_ism_map.py` |
| Map service (separate app, run inside `map-service/`) | `php artisan test --compact` and `node --test tests/js/annotations.test.mjs` |

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
| Shop workspace | `tests/Feature/Modules/ShopOwnerOperations/` | Catalogue screens, CSV import, review inbox and replies, shop applications and approval, suspension, profile and hours, staff invitations and work-function access, stock ledger and low-stock alerts, product options, orders and pickup, counter sales, receipts, returns and exchanges, promotions and coupons, customer support, maintenance requests | 07, 08, 11, 20, 21, 22, 23, 24, 25, 26 |
| Mall administration | `tests/Feature/Modules/MallOwnerAdmin/` | Tenant (shop) creation, editing, activation and archiving, owners and units, floors, mall details, shop categories, activity log, announcements and events, complaints, maintenance desk and work orders, access by role and by mall, admin route protection | 27, 28, 29, 30 |
| Visitor activity analytics | `tests/Feature/Modules/AnalyticsReports/`, `tests/Unit/AnalyticsReports/` | Search and view capture, masking, de-duplication, crawler and staff exclusion, daily roll-ups, retention, demonstration traffic, mall and shop dashboards, funnel reconciliation, access by role | 31, 32, 33 |
| Analytics and reports | `tests/Feature/Modules/AnalyticsReports/` (dashboards, forecast, monthly reports, catalogue, integration), `tests/Unit/AnalyticsReports/` (algorithms) | Hand-calculated reconciliation, period boundaries, access by role, cross-shop and cross-mall isolation, forecast evaluation without leakage, report snapshots, CSV, catalogue completeness, regression, Holt-Winters, k-means, shrinkage against published values | 34, 35, 36 |
| Purchases | `tests/Feature/Modules/PurchaseVerification/` | Sale records, credential lifecycle, redemption rules | 09 |
| Reviews | `tests/Feature/Modules/ReviewsModeration/` | Data layer, review service, rating maths, pages, reports, moderation | 10, 12 |
| Facilities and parking | `tests/Feature/Modules/ParkingFacilities/`, `tests/Feature/Modules/MallShopDirectory/Services/FacilityServiceTest.php`, `tests/Unit/ParkingFacilities/` | Facility rules and finder, admin screens, parking zones, saved spot privacy, directions back, simulated occupancy | 18, 19 |
| Assistant (chatbot and retrieval) | `tests/Feature/Modules/ChatbotRag/` (services, HTTP), `tests/Unit/ChatbotRag/` (text analysis, parsing, matching), `tests/Feature/Seeders/DemoKnowledgeSeederTest.php` (the 37 evaluation cases), `tests/Fixtures/Assistant/FakeAnswerGenerator.php` (fake model driver) | Immutable revisions and chunking, the single retrieval access filter (mall, status, scope, current revision), PostgreSQL full-text and trigram search, capture of public mall records, grounded answers and "I will not guess", product filters from plain words, directions links, English and Bangla, the Gemini driver against a faked API with fallback to the templates, prompt-injection and private-data refusals, administrators' figures and report drafts, audit and interaction logging, retention | 37, 38, 39 |
| Map pipeline | `tests/Blender/test_ism_map.py` (runs in Blender, not in `php artisan test`), `tests/Unit/MapsNavigation/` | Scan levelling and squaring to the layout within centimetres, draft graph shape (junction, doorways, no lines in pillars or wall gaps), marker validation refusals, QR headings, escalator direction, routes across floors, plan image pixels; the PHP test checks the exported package against the ERD rules | 40 |
| Map service and its API | `map-service/tests/Feature/` (own suite), `map-service/tests/js/`, main app `tests/Feature/Modules/MapsNavigation/MapServiceGatewayTest.php` | API keys (missing, unknown, revoked, expired, wrong ability, hashed storage, rate limit, checked before the map lookup), published-only maps, signed file links (tampered, expired, outside the package), package import and rollback, annotation create/update/delete with images, admin sign-in, viewer save, key create once and revoke; viewer coordinate and yaw maths; the main app's client (bearer key, filters, clear errors, check command) | 41 |
| Map graph, QR codes, routes and the embedded map | `map-service/tests/Feature/GraphImportTest.php`, `CheckpointTest.php`, `RoutePlannerTest.php`, `RouteApiTest.php`, `EmbedTest.php` (own suite), main app `tests/Feature/Modules/MapsNavigation/MallMapPageTest.php` | Graph refusals and composite keys, one published map per mall, QR slugs kept across versions and refused when unknown, retired or not on the map, fastest, shortest, step-free and multi-stop routes, escalator direction, nearest facility, closures, step wording, signed embed links, allowed origins, session tokens, the public docs page, the Map and QR pages | 42, 43 |
| Map end points | `map-service/tests/Feature/EndPointTest.php` | Keys, joining the nearest walkway with or without a junction, refusals (too far, unknown floor), routes to `point:<key>`, closures through the parent walkway, delete leaving the graph as it was, rebuild, keys kept by new versions, API places, embed labels, admin page and JSON, guests refused | 45 |
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

- `composer test:pgsql` passed on 2026-11-17 (1169 tests, including EPIC-13's PostgreSQL search); true parallel redemption of one code is still unproven.
- The Gemini driver is tested only against a faked API (`Http::fake`); no test calls the real model (a manual real call is step 10 of guide 38).
- No automatic browser tests exist; the manual files in this folder cover the visible behaviour.

## Result

Date: ____  Tester: ____  Command: ____  Passed / failed / skipped: ____

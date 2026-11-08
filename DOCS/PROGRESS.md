# Progress

## 2026-10-06: folder architecture

Laravel exists at the project root. Thirteen `app/Modules` directories and the requested `app/Core` folders were created and verified during the earlier folder-only task. Module READMEs are placeholders. This does not establish implemented business functionality, registered module routes or providers.

## 2026-10-06: documentation organization

Inspected the three documentation packages and their subfolders, compared duplicate content, mapped current module names to requirements and ERD domains, and separated active guidance from references and archived templates. No application code, database configuration, ERD relationship or business-rule content was changed.

See [reorganization findings](maintenance/REORGANIZATION.md) and [validation](maintenance/VALIDATION.md) for evidence. No Laravel tests or business workflow tests were run for this documentation-only change.

## 2026-11-03: EPIC-00 architecture and project setup (ISM-1)

Branch `feature/epic-00-architecture-setup`, stories ISM-2 to ISM-8.

- Baseline: Laravel 13.34.0 on PHP 8.5.12, Composer 2.8.12, Node 22.16.0. symfony/mime upgraded to 8.1.10 (CVE-2026-45070); `composer audit` clean. Laravel Boost guidelines installed for AI-assisted work.
- Repository: CONTRIBUTING.md (branch/commit/review rules), ignores verified, clean clone + `composer run setup` succeeded.
- Modules: 13 folders documented with namespace `App\Modules\<Module>` and the layout/coupling rules in `app/Modules/README.md`.
- Core: `app/Core` with `BusinessRuleViolation` and `PhoneNumberNormalizer`; boundary documented.
- Database: PostgreSQL 17 via `compose.yaml` (localhost only, `ism` + `ism_testing`), `.env.example` defaults to pgsql, `composer test:pgsql`.
- Documentation entry points link setup, conventions, module/Core rules and the ERD.

Evidence: `php artisan test` 25 passed (SQLite); `composer test:pgsql` 25 passed (PostgreSQL 17.11); architecture tests mutation-checked; `composer validate` valid.

Known structural issues: module service providers and route loading are not implemented yet (EPIC-01 story 1.1); `validate_docs.py` reports a preserved-source hash mismatch for `archive/project-guide/legacy-inventory/CURRENT_DIRECTORY_INVENTORY.txt` that already exists on `main` (line-ending normalisation), left for a team decision.

## 2026-11-03: EPIC-01 shared platform foundation (ISM-9)

Branch `feature/epic-01-shared-platform`, stories ISM-10 to ISM-16.

- Module bootstrapping: `ModuleServiceProvider` base (routes, `$listen` listeners) and the `config/modules.php` manifest; discovery tests.
- Contracts and dependency rules: `DOCS/11_MODULE_CONTRACTS.md` with architecture tests for the HTTP-layer and orchestrator rules.
- Security baseline: security headers, named rate limiters, password and Eloquent strictness defaults, production guards; conventions in `08_SECURITY.md`.
- Events: listener registration per module and an after-commit example flow.
- Notifications: `notifications` table, type enum, preferences contract, SMS adapter boundary, `SystemNotice`.
- Logging and audit: request ids on every request and log line; append-only `audit_logs` with `AuditLogger`.
- Testing: layout and database strategy, `composer ci`, GitHub Actions on SQLite and PostgreSQL, Definition of Done.

Evidence: `composer ci` passed (Pint clean, 70 tests on SQLite); `composer test:pgsql` passed (70 tests on PostgreSQL 17).

Decisions applied (approved by the user, to be recorded in DECISIONS.md with EPIC-02): the `notifications` and `audit_logs` tables are SharedPlatform additions outside the ERD; notifiable and actor ids are strings so they hold the UUID account keys planned for APP_USER.

## 2026-11-03: EPIC-02 accounts and access (ISM-17)

Branches `feature/epic-02-accounts-core` and `feature/epic-02-access-control`, stories ISM-18 to ISM-26.

- Identity (D-008): `users` is the physical APP_USER with UUID keys, normalised email/phone and `password_hash`; Laravel database sessions replace AUTH_SESSION.
- Roles and memberships per the ERD; four abilities (`administer-platform`, `administer-mall`, `manage-shop`, `work-in-shop`) defined as gates; deactivated accounts are signed out.
- Registration, sign-in/out with email or mobile, own profile, password change and email reset with session invalidation and throttles.
- Preferences (D-010) in FavouritesPreferences: language (EN/BN switch), usual mall, route mode, AR, notification switches feeding the notification channels.
- Demo accounts per role (`DemoAccountsSeeder`).

Evidence: full suite on SQLite and PostgreSQL green; role-by-ability matrix test; route-protection guard test; screenshots of registration and sign-in checked.

Known limitations: phone-only accounts have no self-service password reset yet (information desk); email/phone verification flows are not built (fields exist); EPIC-08 ISM-89 overlaps D-010.

## 2026-11-03: EPIC-03 mall and shop directory (ISM-27)

Branches `feature/epic-03-directory-data` and `feature/epic-03-directory-customer`, stories ISM-28 to ISM-37.

- Data layer per ERD domain 02: malls, floors, shop categories (acyclic), shops, units (floor must be in the shop's mall), weekly opening hours (no overlaps, overnight and 24-hour intervals); services with audited lifecycles; Demo City Mall seed (2 floors, 7 categories, 14 shops).
- Customer pages: mall home, paginated directory with keyword search, category/floor/open-now filters and sorting, shop detail with weekly hours in the mall timezone.
- `DirectoryLookup` contract for ProductsCatalogue, MapsNavigation and the workspaces.

Evidence: suites on SQLite and PostgreSQL green; opening-hour boundary cases; search escaping; query-count guard (directory and mall home run a constant number of queries); route-protection guard; screenshots of the directory and detail pages.

Review notes: keyword search uses `lower(column) like` and cannot use an index, acceptable at mall scale (tens to hundreds of shops); add a trigram or full-text index if a mall grows far beyond that. The ERD needed no change. FACILITY (washrooms, prayer rooms, exits) is not created yet: it is built with the facility directory stories of EPIC-09 (ISM-92/ISM-93).

## 2026-11-03: EPIC-04 products catalogue (ISM-38)

Branch `feature/epic-04-products-catalogue`, stories ISM-48, ISM-51 and ISM-54.

- Decision D-011 resolves C-06: shared products and variants, shop-owned listings, shared details editable by the sole lister or the mall administrator.
- Data layer per ERD domain 03 (categories, products, variants, images, listings; PostgreSQL price check) and a demo catalogue of 19 products across the demo shops.
- Shop workspace (`work-in-shop`): add, edit, archive and restore listings, shared details and photos as sole lister, CSV import with row-level preview and all-or-nothing commit.
- Customer pages: product search (keyword, category, shop, price range, in stock, sort), product offers across shops, comparison of up to four offers; `CatalogueLookup` contract for favourites, navigation and the assistant.

Evidence: suites on SQLite and PostgreSQL green; cross-shop 403/404 tests; import error and token tests; visibility tests (archived, closed or draft shops, other malls); screenshot of a product page with four offers.

Limitations: no inventory ledger (availability is owner-maintained); mall-administrator curation of shared products has no screen yet (EPIC-11); images use the public disk without resizing; workspace pages are English only.

## 2026-11-08: EPIC-06 purchase verification (ISM-40)

Branch `feature/epic-06-purchase-verification`, stories ISM-66, ISM-68 and ISM-71 (one commit per story).

- ISM-66 purchase records: `purchases` and `purchase_items` (ERD domain 05) with `PurchaseService`. Authorised shop staff (`work-in-shop`) record completed sales; the service computes amounts, validates a supplied total, snapshots product labels and prices, and keeps header and lines in one transaction. Recorded sales and lines are immutable (only completed to voided or refunded is allowed); lists are scoped per shop for staff and per buyer for customers.
- ISM-68 credentials: `verification_tokens` with `VerificationCredentialService` and `CredentialSecrets`. One credential per purchase line from 256 random bits; only the SHA-256 digest is stored; 90 day default expiry (at most one year); reissue rotates the secret; revoke; all audited. The raw secret is returned once and kept out of serialisation, dumps, logs and audit metadata.
- ISM-71 redemption: `token_redemptions` (primary key is the token id) and the `PurchaseVerifier` contract (`checkEligibility`, `redeem`) bound in the new `PurchaseVerificationServiceProvider`. Enforces buyer ownership, listing scope, expiry, revocation, completed sale and replay; unknown, malformed and foreign secrets are indistinguishable.

Evidence: `php artisan test` 375 tests (373 passed, 2 skipped, none failed) on SQLite after Pint; 46 new tests in `tests/Feature/Modules/PurchaseVerification` cover authorisation, scoping, immutability, secret leakage and the lost-race path.

Limitations: the PostgreSQL run (`composer test:pgsql`) was not done because Docker was not running, so the PostgreSQL CHECK constraints (non-negative totals, line arithmetic, expiry after issue) are written but unverified; true parallel redemption needs the PostgreSQL run (the lock plus the primary key are the guards, and the single-connection test only simulates the lost race). No routes or screens yet (EPIC-07, EPIC-10); no redemption rate limiter until the HTTP entry point exists; refunds are a status change only (C-02); only product-listing reviews can be verified (C-03).

## Next implementation handoff

Select a requested vertical slice using [build order](10_BUILD_ORDER.md), resolve its relevant [open questions](CONFLICTS.md), and complete [the checklist](IMPLEMENTATION_CHECKLIST.md). No feature is automatically authorized by its presence in the catalogue.

Future entries should record date, module, requirement/task, changed files/contracts, exact checks/results, evidence, limitations and next work.

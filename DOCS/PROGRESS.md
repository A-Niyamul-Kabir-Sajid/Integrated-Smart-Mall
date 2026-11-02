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

## Next implementation handoff

Select a requested vertical slice using [build order](10_BUILD_ORDER.md), resolve its relevant [open questions](CONFLICTS.md), and complete [the checklist](IMPLEMENTATION_CHECKLIST.md). No feature is automatically authorized by its presence in the catalogue.

Future entries should record date, module, requirement/task, changed files/contracts, exact checks/results, evidence, limitations and next work.

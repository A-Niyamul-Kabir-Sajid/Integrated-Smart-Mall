# Testing and evidence

Read each affected feature guide's completion checks and the [integration scenarios](references/functional/DOC/integration/SCENARIOS.md). Add focused unit, HTTP feature, integration and device/browser checks according to the changed behavior.

Critical cases include cross-shop/mall denial, concurrent token redemption, review eligibility, graph version consistency, unreachable routes, timezone boundaries, honest analytics coverage, retrieval authorization and AR tracking loss. The [constraint plan](references/erd/DOC/03-database/CONSTRAINT-ENFORCEMENT.md) provides database concurrency cases.

For each implemented slice record requirement ID, test path, command/result, evidence date and limitations in [PROGRESS.md](PROGRESS.md). A screenshot, empty folder or mocked integration is not proof of a completed workflow.

Typical application checks, when application code changes:

```text
php artisan --version
php artisan route:list
php artisan test
composer validate
```

For documentation changes use `python DOCS/maintenance/validate_docs.py`: it checks local Markdown link targets, module coverage and preserved reference hashes. It is a structural check, not a substitute for approving requirements or visually testing the diagram viewer.

## Test layout (EPIC-01, ISM-150)

| Folder | Contains | Base class |
|---|---|---|
| `tests/Unit/` | Logic that needs no framework: architecture rules, pure utilities (`Unit/Architecture`, `Unit/Core`) | `PHPUnit\Framework\TestCase` |
| `tests/Feature/Core/` | Core infrastructure exercised through the framework | `Tests\TestCase` |
| `tests/Feature/Modules/<Module>/` | A module's HTTP endpoints, services and models; mirror the class path (`Http/Controllers/...`, `Services/...`) | `Tests\TestCase` |
| `tests/Feature/Smoke/` | Boot, health check, routes, database connectivity | `Tests\TestCase` |
| `tests/Fixtures/` | Test-only modules, events, notifications and data files | — |

- Name tests after the behaviour: `test_<result>_<condition>`, e.g. `test_draft_shop_profile_returns_404`.
- Most tests are feature tests. Write a unit test only for code that does not use the framework.
- Use model factories (with states such as `draft()`) for test data, never the demo seeders.
- `Tests\TestCase` disables Vite, so tests never need built assets.

## Test databases

| Command | Database | When |
|---|---|---|
| `php artisan test` / `composer ci` | In-memory SQLite (`phpunit.xml`) | Every change; fast |
| `composer test:pgsql` | PostgreSQL `ism_testing` (compose service) | Before pushing schema or query changes; always in CI |

Tests that touch the database use `RefreshDatabase`. Never point tests at the development database `ism`.

## Continuous integration

`.github/workflows/tests.yml` runs on every push to `main` or `feature/**` and on pull requests: `composer ci` (Pint check plus the suite on SQLite) and `composer test:pgsql` against a PostgreSQL 17 service. A red pipeline blocks the merge.

## Definition of Done

A Jira story is Done only when all of these hold:

1. Its acceptance criteria are met and, for user-facing work, checked in a browser.
2. Every changed decision has a test: success path, validation failures, authorization and tenant isolation where relevant.
3. `composer ci` and `composer test:pgsql` pass locally, and the pipeline is green.
4. `vendor/bin/pint` leaves no changes.
5. Affected documentation is updated: module README, `DOCS/PROGRESS.md`, contracts or decisions.
6. Commits carry the Jira key, and the story has a comment listing them.
7. The branch is reviewed by the other developer and merged into `main`.

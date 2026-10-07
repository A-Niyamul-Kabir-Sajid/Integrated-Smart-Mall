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

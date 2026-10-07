# Start here

## Before implementing a feature

1. Read [project context](01_PROJECT_CONTEXT.md), [requirements index](02_MASTER_PRD.md), and [architecture](03_ARCHITECTURE.md).
2. Read [module map](05_MODULE_MAP.md), [decisions](DECISIONS.md), and [open conflicts](CONFLICTS.md).
3. Open the affected module's `modules/<ExactAppModuleName>/README.md`. Follow its links to the detailed requirements, business rules, dictionary and ERD; read affected consumers too.
4. Read [contracts](07_API_CONTRACTS.md), [security](08_SECURITY.md), and [testing](09_TESTING.md). For data work also read [database guidance](06_DATABASE_GUIDE.md).
5. Inspect actual implementation and tests. Use the [task checklist](IMPLEMENTATION_CHECKLIST.md) before changing code.
6. Implement only the requested slice and record evidence in [PROGRESS.md](PROGRESS.md).

## Source authority

Latest explicit user decisions take precedence. Within documentation use: approved decisions, product requirements, architecture, API contracts, module requirements/business rules, database specification, existing implementation, historical notes. An incomplete PRD or placeholder never supplies a missing requirement or overrides a detailed rule by silence.

There are three distinct classifications: 13 application folders, 12 feature-catalogue sections, and nine ERD data domains. Their numeric IDs are not interchangeable. The module map connects them.

The reference ERD is a **proposed logical baseline**, not an implemented or fully approved schema. Resolve relevant open decisions before migrations. Material contradictions must be recorded and settled explicitly; do not silently rewrite the ERD.

## Where to look

| Question | Starting point |
|---|---|
| Which module owns this feature? | [Module map](05_MODULE_MAP.md) |
| What must it do? | [Requirements index](02_MASTER_PRD.md) and module source links |
| Which tables/relationships already exist in the design? | [Database guide](06_DATABASE_GUIDE.md) |
| Where should classes/patterns go? | [Architecture](03_ARCHITECTURE.md), [patterns](04_DESIGN_PATTERNS.md) |
| What is implemented? | [Progress](PROGRESS.md), then source/tests |
| What should be built first? | [Build order](10_BUILD_ORDER.md) |
| Why was a document moved? | [Reorganization audit](maintenance/REORGANIZATION.md) |

Do not run the archived merge script: this tree links to preserved sources instead of copying their definitions into competing documents.

# 00 — START HERE

This is the documentation router and single starting point.

| Need to know | Read |
|---|---|
| What the product must do | `02_MASTER_PRD.md` |
| Overall code/system structure | `03_ARCHITECTURE.md` |
| When to use MVC/services/patterns | `04_DESIGN_PATTERNS.md` |
| Which application module owns a feature | `05_MODULE_MAP.md` |
| Tables, ownership, ERDs | `06_DATABASE_GUIDE.md` + module ERD |
| How modules talk to each other | `07_API_CONTRACTS.md` |
| Security and authorization | `08_SECURITY.md` |
| Test/evidence expectations | `09_TESTING.md` |
| Implementation sequence | `10_BUILD_ORDER.md` |
| Why a decision was made | `DECISIONS.md` |
| Current completion state | `PROGRESS.md` |

## Golden rule

Each concern has exactly one authoritative source. Other files should link to it rather than duplicate it.

## Existing package import

Your older generated ERD and module-scaffold folders remain valuable source material. Run `tools/merge-existing.ps1` from the new project root to import them into this canonical structure. The tool defaults to safe/no-overwrite behavior.

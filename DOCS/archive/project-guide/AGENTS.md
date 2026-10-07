# AGENTS.md — 3 2 SyStem / Integrated Smart Mall

This file is the mandatory entry point for any coding agent or developer.

## Mandatory reading order before implementation

1. `/docs/00_START_HERE.md`
2. `/docs/01_PROJECT_CONTEXT.md`
3. `/docs/02_MASTER_PRD.md`
4. `/docs/03_ARCHITECTURE.md`
5. `/docs/04_DESIGN_PATTERNS.md`
6. `/docs/05_MODULE_MAP.md`
7. `/docs/DECISIONS.md`
8. Identify the affected module and read its complete `/docs/modules/<module>/` folder.
9. If another module is involved, read `/docs/07_API_CONTRACTS.md` and both affected module guides.
10. For database changes, also read `/docs/06_DATABASE_GUIDE.md`, the module ERD, data dictionary and business rules.
11. Inspect existing implementation and tests before changing code.

## Architecture rules

- Use a **Laravel modular monolith** with **MVC + Service Layer**.
- Controllers must stay thin: validate/authorize, call a service/facade, return a response/view.
- Business rules belong in Services/Domain classes, not Controllers or Blade templates.
- Use Eloquent Models for persistence; introduce Repositories only where query complexity, testing isolation, or multiple data sources justify them.
- Modules communicate through documented services/contracts/events. Avoid directly reaching into another module's internal implementation.
- Views contain presentation logic only.
- Keep customer, shop-owner and mall-owner presentation separated while sharing the same backend modules.

## Pattern rule

Use a design pattern only when the problem matches the documented trigger in `/docs/04_DESIGN_PATTERNS.md`. Never add a pattern merely to demonstrate it.

## Before coding, explicitly identify

- affected module(s)
- controller / endpoint / view
- service or domain class
- models/tables
- business rules
- cross-module contracts
- design pattern (if any)
- tests that must be added or updated

## Conflict priority

When documents conflict, resolve in this order:

1. latest approved `/docs/DECISIONS.md`
2. `/docs/02_MASTER_PRD.md`
3. architecture + API contracts
4. module requirements/business rules
5. database specification
6. existing implementation
7. comments / obsolete notes

Do not silently resolve a material conflict. Record the resolution in `DECISIONS.md`.

# SharedPlatform

## Responsibility

Notifications, audit, integrations, localization and shared infrastructure. Source folder: [app/Modules/SharedPlatform](../../../app/Modules/SharedPlatform/).

Status: folder architecture only; this page routes to existing requirements, not implemented APIs.

## Read before implementation

Start with [the reading order](../../00_START_HERE.md) and [task checklist](../../IMPLEMENTATION_CHECKLIST.md).

### Detailed functional requirements

- [Catalogue section 12: shared platform requirements](../../references/functional/DOC/modules/12_shared_platform_requirements/GUIDE.md), including its completion checks.

Historical feature guides may assign data to a dashboard module. Translate their ownership through the current module map and ERD table ownership; their old source-layout instructions are not the current code layout.

### Data and business rules

- 01-accounts-access: [overview](../../references/erd/DOC/05-modules/01-accounts-access/README.md), [business rules](../../references/erd/DOC/05-modules/01-accounts-access/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/01-accounts-access/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/01-accounts-access/ERD.md).

These are collaborating data domains, not a new dedicated schema for this module. Some requested capabilities have no table design yet. Do not invent missing entities or duplicate existing records.

Read [table ownership](../../references/erd/DOC/03-database/TABLE-OWNERSHIP.md) and [database guidance](../../06_DATABASE_GUIDE.md).

## Dependencies and checks

Related modules: AccountsAccess and all capability consumers. Review their contracts and authorization boundaries before changing shared behavior.

Relevant [open questions](../../CONFLICTS.md): C-01, C-04, C-09. [API guidance](../../07_API_CONTRACTS.md) applies before defining cross-module inputs, outputs and events.

Use the source guides' completion checks and [test guidance](../../09_TESTING.md); record actual results in [progress](../../PROGRESS.md). Keep business logic in Services and add patterns only when justified.

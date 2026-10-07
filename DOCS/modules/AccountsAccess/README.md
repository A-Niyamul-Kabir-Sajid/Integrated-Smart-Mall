# AccountsAccess

## Responsibility

Identity, customer accounts, profiles and scoped access. Source folder: [app/Modules/AccountsAccess](../../../app/Modules/AccountsAccess/).

Status: folder architecture only; this page routes to existing requirements, not implemented APIs.

## Read before implementation

Start with [the reading order](../../00_START_HERE.md) and [task checklist](../../IMPLEMENTATION_CHECKLIST.md).

### Detailed functional requirements

- [Catalogue section 01: customer accounts preferences](../../references/functional/DOC/modules/01_customer_accounts_preferences/GUIDE.md), including its completion checks.
- [Catalogue section 12: shared platform requirements](../../references/functional/DOC/modules/12_shared_platform_requirements/GUIDE.md), including its completion checks.

Historical feature guides may assign data to a dashboard module. Translate their ownership through the current module map and ERD table ownership; their old source-layout instructions are not the current code layout.

### Data and business rules

- 01-accounts-access: [overview](../../references/erd/DOC/05-modules/01-accounts-access/README.md), [business rules](../../references/erd/DOC/05-modules/01-accounts-access/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/01-accounts-access/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/01-accounts-access/ERD.md).

Read [table ownership](../../references/erd/DOC/03-database/TABLE-OWNERSHIP.md) and [database guidance](../../06_DATABASE_GUIDE.md).

## Dependencies and checks

Related modules: FavouritesPreferences, SharedPlatform, ShopOwnerOperations, MallOwnerAdmin. Review their contracts and authorization boundaries before changing shared behavior.

Relevant [open questions](../../CONFLICTS.md): C-01, C-05. [API guidance](../../07_API_CONTRACTS.md) applies before defining cross-module inputs, outputs and events.

Use the source guides' completion checks and [test guidance](../../09_TESTING.md); record actual results in [progress](../../PROGRESS.md). Keep business logic in Services and add patterns only when justified.

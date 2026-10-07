# FavouritesPreferences

## Responsibility

Saved shop/listing favourites and user preferences. Source folder: [app/Modules/FavouritesPreferences](../../../app/Modules/FavouritesPreferences/).

Status: folder architecture only; this page routes to existing requirements, not implemented APIs.

## Read before implementation

Start with [the reading order](../../00_START_HERE.md) and [task checklist](../../IMPLEMENTATION_CHECKLIST.md).

### Detailed functional requirements

- [Catalogue section 01: customer accounts preferences](../../references/functional/DOC/modules/01_customer_accounts_preferences/GUIDE.md), including its completion checks.

Historical feature guides may assign data to a dashboard module. Translate their ownership through the current module map and ERD table ownership; their old source-layout instructions are not the current code layout.

### Data and business rules

- 07-favourites-preferences: [overview](../../references/erd/DOC/05-modules/07-favourites-preferences/README.md), [business rules](../../references/erd/DOC/05-modules/07-favourites-preferences/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/07-favourites-preferences/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/07-favourites-preferences/ERD.md).

Read [table ownership](../../references/erd/DOC/03-database/TABLE-OWNERSHIP.md) and [database guidance](../../06_DATABASE_GUIDE.md).

## Dependencies and checks

Related modules: AccountsAccess, ProductsCatalogue, MallShopDirectory, MapsNavigation. Review their contracts and authorization boundaries before changing shared behavior.

Relevant [open questions](../../CONFLICTS.md): C-01. [API guidance](../../07_API_CONTRACTS.md) applies before defining cross-module inputs, outputs and events.

Use the source guides' completion checks and [test guidance](../../09_TESTING.md); record actual results in [progress](../../PROGRESS.md). Keep business logic in Services and add patterns only when justified.

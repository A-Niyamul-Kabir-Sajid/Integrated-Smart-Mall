# ParkingFacilities

## Responsibility

Saved parking, visitor support, events and facility-facing flows. Source folder: [app/Modules/ParkingFacilities](../../../app/Modules/ParkingFacilities/).

Status: folder architecture only; this page routes to existing requirements, not implemented APIs.

## Read before implementation

Start with [the reading order](../../00_START_HERE.md) and [task checklist](../../IMPLEMENTATION_CHECKLIST.md).

### Detailed functional requirements

- [Catalogue section 07: parking facilities visitor support](../../references/functional/DOC/modules/07_parking_facilities_visitor_support/GUIDE.md), including its completion checks.

Historical feature guides may assign data to a dashboard module. Translate their ownership through the current module map and ERD table ownership; their old source-layout instructions are not the current code layout.

### Data and business rules

- 02-mall-shop-directory: [overview](../../references/erd/DOC/05-modules/02-mall-shop-directory/README.md), [business rules](../../references/erd/DOC/05-modules/02-mall-shop-directory/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/02-mall-shop-directory/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/02-mall-shop-directory/ERD.md).
- 04-maps-navigation: [overview](../../references/erd/DOC/05-modules/04-maps-navigation/README.md), [business rules](../../references/erd/DOC/05-modules/04-maps-navigation/BUSINESS-RULES.md), [dictionary](../../references/erd/DOC/05-modules/04-maps-navigation/DATA-DICTIONARY.md), [ERD](../../references/erd/DOC/05-modules/04-maps-navigation/ERD.md).

These are collaborating data domains, not a new dedicated schema for this module. Some requested capabilities have no table design yet. Do not invent missing entities or duplicate existing records.

Read [table ownership](../../references/erd/DOC/03-database/TABLE-OWNERSHIP.md) and [database guidance](../../06_DATABASE_GUIDE.md).

## Dependencies and checks

Related modules: MallShopDirectory, MapsNavigation, MallOwnerAdmin, SharedPlatform. Review their contracts and authorization boundaries before changing shared behavior.

Relevant [open questions](../../CONFLICTS.md): C-04, C-09. [API guidance](../../07_API_CONTRACTS.md) applies before defining cross-module inputs, outputs and events.

Use the source guides' completion checks and [test guidance](../../09_TESTING.md); record actual results in [progress](../../PROGRESS.md). Keep business logic in Services and add patterns only when justified.
